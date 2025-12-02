<?php

namespace Gametech\FacebookOA\Models;

use Gametech\FacebookOA\Contracts\FacebookWebhookLog as FacebookWebhookLogContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacebookWebhookLog extends Model implements FacebookWebhookLogContract
{
    protected $table = 'facebook_webhook_logs';

    protected $fillable = [
        'facebook_account_id',
        'event_type',
        'event_id',
        'request_id',
        'ip',
        'user_agent',
        'headers',
        'body',
        'http_status',
        'is_processed',
        'processed_at',
        'error_message',
    ];

    protected $casts = [
        'headers' => 'array',
        'is_processed' => 'boolean',
        'processed_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(FacebookAccount::class, 'facebook_account_id');
    }
}
