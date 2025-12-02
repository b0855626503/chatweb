<?php

namespace Gametech\FacebookOA\Models;

use Gametech\FacebookOA\Contracts\FacebookAccount as FacebookAccountContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FacebookAccount extends Model implements FacebookAccountContract
{
    protected $table = 'facebook_accounts';

    protected $fillable = [
        'name',
        'page_id',
        'app_id',
        'page_access_token',
        'webhook_verify_token',
        'status',
        'default_outgoing_language',
        'default_incoming_language',
        'timezone',
        'remark',
    ];

    protected $casts = [
        'default_outgoing_language' => 'string',
        'default_incoming_language' => 'string',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(FacebookContact::class, 'facebook_account_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(FacebookConversation::class, 'facebook_account_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(FacebookMessage::class, 'facebook_account_id');
    }

    public function webhookLogs(): HasMany
    {
        return $this->hasMany(FacebookWebhookLog::class, 'facebook_account_id');
    }

    /* ==========================
     |  Scopes
     |==========================*/

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    /* ==========================
     |  Helpers
     |==========================*/

    // ใช้เช็คว่าพร้อมใช้งานหรือไม่
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    // เพื่อความสะดวกเวลา debug
    public function shortToken(): string
    {
        if (! $this->page_access_token) {
            return '-';
        }

        return Str::limit($this->page_access_token, 10, '...'); // โชว์บางส่วน
    }
}
