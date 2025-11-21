<?php

namespace Gametech\LineOA\Models;

use Gametech\LineOA\Contracts\LineConversation as LineConversationContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LineConversation extends Model implements LineConversationContract
{
    protected $table = 'line_conversations';

    protected $fillable = [
        'line_account_id',
        'line_contact_id',
        'status',
        'last_message_preview',
        'last_message_at',
        'unread_count',
        'assigned_employee_id',
        'locked_by_employee_id',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(LineAccount::class, 'line_account_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(LineContact::class, 'line_contact_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(LineMessage::class, 'line_conversation_id');
    }
}
