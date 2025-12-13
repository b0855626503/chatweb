<?php

namespace Gametech\Sms\Jobs;

use Gametech\Sms\Models\SmsRecipient;
use Gametech\Sms\Services\OutboundSmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;

    public function __construct(public int $recipientId) {}

    public function handle(OutboundSmsService $service): void
    {
        $recipient = SmsRecipient::with('campaign')->find($this->recipientId);

        if (! $recipient) {
            throw new ModelNotFoundException('SmsRecipient not found');
        }

        // กันยิงซ้ำ
        if (! in_array($recipient->status, ['queued'], true)) {
            return;
        }

        $recipient->status = 'sending';
        $recipient->attempts++;
        $recipient->save();

        $result = $service->send($recipient);

        if ($result['success'] ?? false) {
            $recipient->markSent($result['provider_message_id'] ?? null);
            $recipient->save();
            return;
        }

        // failed
        $recipient->markFailed(
            $result['error_code'] ?? null,
            $result['error_message'] ?? null
        );
        $recipient->save();
    }
}
