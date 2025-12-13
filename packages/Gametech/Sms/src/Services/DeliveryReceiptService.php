<?php

namespace Gametech\Sms\Services;

use Gametech\Sms\Models\SmsCampaign;
use Gametech\Sms\Models\SmsDeliveryReceipt;
use Gametech\Sms\Models\SmsRecipient;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeliveryReceiptService
{
    /**
     * Handle Provider DLR payload (provider-agnostic entrypoint).
     *
     * - Create sms_delivery_receipts row (idempotent)
     * - Find SmsRecipient by provider_message_id
     * - Apply DLR mapping to SmsRecipient
     * - Update campaign counters
     * - Finalize campaign if all recipients are terminal
     */
    public function handleProviderDlr(string $provider, array $payload): array
    {
        $provider = strtolower(trim($provider ?: 'vonage'));

        // normalize id/status/error fields (support multiple naming styles)
        $messageId = (string) Arr::get($payload, 'messageId', Arr::get($payload, 'message-id'));
        if ($messageId === '') {
            // เผื่อ provider อื่นใช้ชื่ออื่น (ไม่เดาเยอะ แค่สำรองยอดนิยม)
            $messageId = (string) (Arr::get($payload, 'MessageSid') ?? Arr::get($payload, 'message_id') ?? '');
        }

        $status = (string) (Arr::get($payload, 'status') ?? Arr::get($payload, 'MessageStatus') ?? Arr::get($payload, 'SmsStatus') ?? '');
        $errCode = (string) (Arr::get($payload, 'err-code', Arr::get($payload, 'err_code')) ?? Arr::get($payload, 'ErrorCode') ?? Arr::get($payload, 'error_code') ?? '');

        if ($messageId === '' || $status === '') {
            Log::channel('sms')->warning('[DLR] missing required fields', [
                'provider' => $provider,
                'payload' => $payload,
            ]);

            return ['status' => 'missing_required_fields'];
        }

        // idempotent upsert (provider + message_id)
        $dlr = SmsDeliveryReceipt::updateOrCreate([
            'provider' => $provider,
            'message_id' => $messageId,
        ], [
            // เก็บสถานะล่าสุดที่ได้รับ
            'status' => $status ?: null,
            'err_code' => $errCode ?: null,
            'scts' => (string) Arr::get($payload, 'scts'),

            'msisdn' => (string) Arr::get($payload, 'msisdn'),
            'to' => (string) Arr::get($payload, 'to'),
            'network_code' => (string) Arr::get($payload, 'network-code'),
            'api_key' => (string) Arr::get($payload, 'api-key'),
            'message_timestamp' => (string) Arr::get($payload, 'message-timestamp'),
            'price' => (string) Arr::get($payload, 'price'),
            'payload' => $payload,
            'received_at' => now(),

            // process bookkeeping
            'process_status' => 'pending',
        ]);

        // ถ้าเคย processed แล้ว ให้จบแบบสุภาพ
        if ($dlr->process_status === 'processed') {
            return ['status' => 'duplicate_ignored', 'dlr_id' => $dlr->id];
        }

        try {
            return DB::transaction(function () use ($provider, $payload, $dlr, $messageId) {

                /** @var SmsRecipient|null $recipient */
                $recipient = SmsRecipient::where('provider_message_id', $messageId)->first();

                if (! $recipient) {
                    $dlr->process_status = 'ignored';
                    $dlr->process_error = 'Recipient not found by provider_message_id';
                    $dlr->processed_at = now();
                    $dlr->save();

                    Log::channel('sms')->warning('[DLR] recipient not found', [
                        'provider' => $provider,
                        'messageId' => $messageId,
                        'payload' => $payload,
                    ]);

                    return ['status' => 'recipient_not_found', 'dlr_id' => $dlr->id];
                }

                // link dlr -> recipient/campaign/team (ใช้ field ที่มีจริงใน model SmsDeliveryReceipt)
                $dlr->recipient_id = $recipient->id;
                $dlr->campaign_id = $recipient->campaign_id;
                $dlr->team_id = $recipient->team_id;

                // ensure recipient provider set (source of truth for mapping)
                if (! $recipient->provider) {
                    $recipient->provider = $provider;
                }

                $before = (string) $recipient->status;

                // apply mapping + store raw payload
                $recipient->applyDeliveryReceipt($payload);
                $recipient->save();

                $after = (string) $recipient->status;

                // update campaign counters (เฉพาะ transitions สำคัญ)
                if ($recipient->campaign_id) {
                    $this->updateCampaignCountersOnTransition((int) $recipient->campaign_id, $before, $after);

                    // ✅ finalize campaign if done
                    $this->finalizeCampaignIfDone((int) $recipient->campaign_id);
                }

                $dlr->process_status = 'processed';
                $dlr->processed_at = now();
                $dlr->save();

                return [
                    'status' => 'processed',
                    'dlr_id' => $dlr->id,
                    'recipient_id' => $recipient->id,
                    'before' => $before,
                    'after' => $after,
                ];
            });
        } catch (\Throwable $e) {
            $dlr->process_status = 'failed';
            $dlr->process_error = $e->getMessage();
            $dlr->processed_at = now();
            $dlr->save();

            Log::channel('sms')->error('[DLR] processing failed', [
                'provider' => $provider,
                'messageId' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return ['status' => 'failed', 'dlr_id' => $dlr->id];
        }
    }

    private function updateCampaignCountersOnTransition(int $campaignId, string $before, string $after): void
    {
        if ($before === $after) {
            return;
        }

        SmsCampaign::whereKey($campaignId)->update([
            'delivered_count' => DB::raw($after === 'delivered' ? 'delivered_count + 1' : 'delivered_count'),
            'failed_count' => DB::raw($after === 'failed' ? 'failed_count + 1' : 'failed_count'),
            'sent_count' => DB::raw($after === 'sent' ? 'sent_count + 1' : 'sent_count'),
        ]);
    }

    /**
     * Finalize campaign when all recipients are in terminal states (delivered/failed)
     * Uses ONLY fields that exist in SmsCampaign: status, finished_at, total_recipients, delivered_count, failed_count
     */
    private function finalizeCampaignIfDone(int $campaignId): void
    {
        /** @var SmsCampaign|null $campaign */
        $campaign = SmsCampaign::query()->lockForUpdate()->find($campaignId);
        if (! $campaign) {
            return;
        }

        // ถ้าจบแล้วไม่ต้องทำอะไร
        if ($campaign->status === 'completed') {
            return;
        }

        // total_recipients อาจยังไม่ set ในบาง flow → fallback จาก count จริง
        $total = (int) ($campaign->total_recipients ?: $campaign->recipients()->count());

        $done = (int) ($campaign->delivered_count + $campaign->failed_count);

        // ถ้ามี recipient และ done ครบ → ปิด
        if ($total > 0 && $done >= $total) {
            $campaign->status = 'completed';
            $campaign->finished_at = $campaign->finished_at ?: now();
            $campaign->save();
        }
    }

    /*
     * Backward compatibility:
     * ถ้าโค้ดเดิมยังเรียก handleVonageDlr อยู่ ให้คง method นี้ไว้
     */
    public function handleVonageDlr(array $payload): array
    {
        return $this->handleProviderDlr('vonage', $payload);
    }
}
