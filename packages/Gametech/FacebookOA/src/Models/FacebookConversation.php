<?php

namespace Gametech\FacebookOA\Models;

use Gametech\FacebookOA\Contracts\FacebookConversation as FacebookConversationContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacebookConversation extends Model implements FacebookConversationContract
{
    protected $table = 'facebook_conversations';

    protected $fillable = [
        'facebook_account_id',
        'facebook_contact_id',
        'fb_conversation_id',
        'status',
        'last_message_preview',
        'last_message_at',
        'unread_count',
        'assigned_employee_id',
        'assigned_employee_name',
        'assigned_at',
        'locked_by_employee_id',
        'locked_by_employee_name',
        'locked_at',
        'closed_by_employee_id',
        'closed_by_employee_name',
        'closed_at',
        'outgoing_language',
        'incoming_language',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'assigned_at'     => 'datetime',
        'locked_at'       => 'datetime',
        'closed_at'       => 'datetime',
        'unread_count'    => 'integer',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(FacebookAccount::class, 'facebook_account_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(FacebookContact::class, 'facebook_contact_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(FacebookMessage::class, 'facebook_conversation_id');
    }
}
