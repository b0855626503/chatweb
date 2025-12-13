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
     * Entry point สำหรับทุก provider
     */
    public function handleProviderDlr(string $provider, array $payload): array
    {
        $normalized = $this->normalizePayload($provider, $payload);

        if (! $normalized['message_id'] || ! $normalized['status']) {
            Log::channel('sms')->warning('[SmsDLR] missing required fields', [
                'provider' => $provider,
                'payload'  => $payload,
            ]);

            return ['status' => 'invalid_payload'];
        }

        return $this->processNormalizedDlr($provider, $normalized, $payload);
    }

    /**
     * =========================
     * Provider normalizers
     * =========================
     */
    protected function normalizePayload(string $provider, array $payload): array
    {
        switch ($provider) {
            case 'vonage':
                return $this->normalizeVonage($payload);

            // case 'twilio':
            //     return $this->normalizeTwilio($payload);

            default:
                return [
                    'message_id' => null,
                    'status'     => null,
                ];
        }
    }

    protected function normalizeVonage(array $payload): array
    {
        return [
            'message_id' => (string) Arr::get($payload, 'messageId', Arr::get($payload, 'message-id')),
            'status'     => (string) Arr::get($payload, 'status'),
            'err_code'   => (string) Arr::get($payload, 'err-code', Arr::get($payload, 'err_code')),
            'msisdn'     => (string) Arr::get($payload, 'msisdn'),
            'scts'       => (string) Arr::get($payload, 'scts'),
            'meta'       => [
                'to'                => Arr::get($payload, 'to'),
                'network_code'      => Arr::get($payload, 'network-code'),
                'api_key'           => Arr::get($payload, 'api-key'),
                'message_timestamp' => Arr::get($payload, 'message-timestamp'),
                'price'             => Arr::get($payload, 'price'),
            ],
        ];
    }

    /**
     * =========================
     * Core processing (provider agnostic)
     * =========================
     */
    protected function processNormalizedDlr(string $provider, array $data, array $rawPayload): array
    {
        $messageId = $data['message_id'];

        $dlr = SmsDeliveryReceipt::updateOrCreate(
            [
                'provider'   => $provider,
                'message_id' => $messageId,
            ],
            [
                'status'        => $data['status'] ?: null,
                'err_code'      => $data['err_code'] ?: null,
                'scts'          => $data['scts'] ?: null,
                'msisdn'        => $data['msisdn'] ?: null,
                'payload'       => $rawPayload,
                'received_at'   => now(),
                'process_status'=> 'pending',
            ] + $data['meta']
        );

        if ($dlr->process_status === 'processed') {
            return ['status' => 'duplicate_ignored', 'dlr_id' => $dlr->id];
        }

        try {
            return DB::transaction(function () use ($dlr, $data, $rawPayload) {

                $recipient = SmsRecipient::where(
                    'provider_message_id',
                    $data['message_id']
                )->first();

                if (! $recipient) {
                    $dlr->process_status = 'ignored';
                    $dlr->process_error  = 'Recipient not found by provider_message_id';
                    $dlr->processed_at   = now();
                    $dlr->save();

                    Log::channel('sms')->warning('[SmsDLR] recipient not found', [
                        'provider'  => $dlr->provider,
                        'messageId' => $data['message_id'],
                    ]);

                    return ['status' => 'recipient_not_found', 'dlr_id' => $dlr->id];
                }

                // link
                $dlr->recipient_id = $recipient->id;
                $dlr->campaign_id  = $recipient->campaign_id;
                $dlr->team_id      = $recipient->team_id;

                $before = $recipient->status;

                // ใช้ logic เดิมของ model (ไม่เดา ไม่ override)
                $recipient->applyDeliveryReceipt($rawPayload);
                $recipient->save();

                $after = $recipient->status;

                if ($recipient->campaign_id) {
                    $this->updateCampaignCountersOnTransition(
                        $recipient->campaign_id,
                        $before,
                        $after
                    );
                }

                $dlr->process_status = 'processed';
                $dlr->processed_at   = now();
                $dlr->save();

                return [
                    'status'       => 'processed',
                    'dlr_id'       => $dlr->id,
                    'recipient_id' => $recipient->id,
                    'before'       => $before,
                    'after'        => $after,
                ];
            });
        } catch (\Throwable $e) {
            $dlr->process_status = 'failed';
            $dlr->process_error  = $e->getMessage();
            $dlr->processed_at   = now();
            $dlr->save();

            Log::channel('sms')->error('[SmsDLR] processing failed', [
                'provider'  => $provider,
                'messageId' => $messageId,
                'error'     => $e->getMessage(),
            ]);

            return ['status' => 'failed', 'dlr_id' => $dlr->id];
        }
    }

    private function updateCampaignCountersOnTransition(
        int $campaignId,
        string $before,
        string $after
    ): void {
        if ($before === $after) {
            return;
        }

        SmsCampaign::whereKey($campaignId)->update([
            'delivered_count' => DB::raw(
                $after === 'delivered'
                    ? 'delivered_count + 1'
                    : 'delivered_count'
            ),
            'failed_count' => DB::raw(
                $after === 'failed'
                    ? 'failed_count + 1'
                    : 'failed_count'
            ),
        ]);
    }
}
