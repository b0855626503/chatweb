<?php

namespace Gametech\FacebookOA\Models;

use Gametech\FacebookOA\Contracts\FacebookRegisterSession as FacebookRegisterSessionContract;
use Gametech\Member\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacebookRegisterSession extends Model implements FacebookRegisterSessionContract
{
    protected $table = 'facebook_register_sessions';

    protected $fillable = [
        'facebook_contact_id',
        'facebook_conversation_id',

        'status',         // waiting / in_progress / completed / cancelled / failed / expired
        'current_step',   // phone / name / surname / bank / account

        'data',           // JSON: { phone, name, surname, bank_code, account_no }
        'member_id',      // members.code (หรือ id แล้วแต่ระบบ)
        'error_message',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    // --------------------------------------------------------
    //  Scopes
    // --------------------------------------------------------

    /**
     * session ที่อยู่ระหว่างดำเนินการ (ตาม default migration)
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * session ที่ยังไม่จบของ contact นั้น ๆ
     */
    public function scopeForContact($query, $contactId)
    {
        return $query->where('facebook_contact_id', $contactId);
    }

    // --------------------------------------------------------
    //  Relations
    // --------------------------------------------------------

    public function contact(): BelongsTo
    {
        return $this->belongsTo(FacebookContact::class, 'facebook_contact_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(FacebookConversation::class, 'facebook_conversation_id');
    }

    /**
     * สมาชิกจริงที่ถูกสร้าง (ถ้าสมัครสำเร็จ)
     * ปกติ Gametech ใช้ primary = code
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'code');
    }
}
