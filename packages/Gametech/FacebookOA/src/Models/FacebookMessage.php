<?php

namespace Gametech\FacebookOA\Models;

use Gametech\FacebookOA\Contracts\FacebookMessage as FacebookMessageContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacebookMessage extends Model implements FacebookMessageContract
{
    protected $table = 'facebook_messages';

    protected $fillable = [
        'facebook_account_id',
        'facebook_contact_id',
        'facebook_conversation_id',
        'direction',
        'sender_type',
        'sender_id',
        'sender_employee_id',
        'message_mid',
        'seq',
        'type',
        'text',
        'payload',
        'is_echo',
        'is_read',
        'read_at',
        'delivery_status',
        'error_code',
        'error_message',
        'language',
        'sent_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'is_echo' => 'boolean',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'sent_at' => 'datetime',
        'sender_employee_id' => 'integer',
        'seq' => 'integer',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(FacebookAccount::class, 'facebook_account_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(FacebookContact::class, 'facebook_contact_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(FacebookConversation::class, 'facebook_conversation_id');
    }
}
