<?php

namespace Gametech\FacebookOA\Models;

use Gametech\FacebookOA\Contracts\FacebookContact as FacebookContactContract;
use Gametech\Member\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacebookContact extends Model implements FacebookContactContract
{
    protected $table = 'facebook_contacts';

    protected $fillable = [
        'facebook_account_id',
        'psid',
        'name',
        'first_name',
        'last_name',
        'avatar_url',
        'locale',
        'timezone',
        'gender',
        'preferred_language',
        'member_id',
        'member_username',
        'member_mobile',
        'last_seen_at',
        'blocked_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'blocked_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(FacebookAccount::class, 'facebook_account_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(FacebookConversation::class, 'facebook_contact_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(FacebookMessage::class, 'facebook_contact_id');
    }

    public function member(): BelongsTo
    {
        // สมมติ member_id อ้างไปยัง members.code
        return $this->belongsTo(Member::class, 'member_id', 'code');
    }
}
