<?php

namespace Gametech\Payment\Models;

use Alexmg86\LaravelSubQuery\Traits\LaravelSubQueryTrait;
use DateTimeInterface;
use Gametech\Payment\Contracts\BankAccount as BankAccountContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model implements BankAccountContract
{

    use LaravelSubQueryTrait;

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected $table = 'banks_account';

    const CREATED_AT = 'date_create';
    const UPDATED_AT = 'date_update';

    protected $dateFormat = 'Y-m-d H:i:s';

    protected $primaryKey = 'code';

    protected $fillable = [
        'acc_name',
        'acc_no',
        'banks',
        'user_name',
        'user_pass',
        'balance',
        'status_auto',
        'status_topup',
        'bank_type',
        'smestatus',
        'device_id',
        'api_refresh',
        'checktime',
        'display_wallet',
        'sort',
        'enable',
        'user_create',
        'user_update',
        'date_create',
        'date_update'
    ];

    protected $casts = [
        'code' => 'integer',
        'acc_name' => 'string',
        'acc_no' => 'string',
        'banks' => 'integer',
        'user_name' => 'string',
        'user_pass' => 'string',
        'balance' => 'decimal:2',
        'status_auto' => 'string',
        'status_topup' => 'string',
        'bank_type' => 'integer',
        'smestatus' => 'string',
        'device_id' => 'string',
        'api_refresh' => 'string',
        'checktime' => 'datetime',
        'display_wallet' => 'string',
        'sort' => 'integer',
        'enable' => 'string',
        'user_create' => 'string',
        'user_update' => 'string',
        'date_create' => 'datetime:Y-m-d H:i',
        'date_update' => 'datetime:Y-m-d H:i',
    ];

    public static $rules = [
        'acc_name' => 'required|string|max:255',
        'acc_no' => 'required|numeric|max:10',
        'banks' => 'nullable|integer',
        'user_name' => 'required|string|max:100',
        'user_pass' => 'required|string|max:100',
        'balance' => 'required|numeric',
        'status_auto' => 'required|string',
        'status_topup' => 'required|string',
        'bank_type' => 'required|integer',
        'smestatus' => 'required|string',
        'device_id' => 'nullable|string|max:150',
        'api_refresh' => 'nullable|string|max:150',
        'checktime' => 'nullable',
        'display_wallet' => 'required|string',
        'sort' => 'required|integer',
        'enable' => 'nullable|string',
        'user_create' => 'required|string|max:100',
        'user_update' => 'required|string|max:100',
        'date_create' => 'nullable',
        'date_update' => 'nullable',
        'created_at' => 'nullable',
        'updated_at' => 'nullable'
    ];

    protected $hidden = [
        'user_name', 'user_pass'
    ];

    protected static function booted()
    {
        static::addGlobalScope('code', function (Builder $builder) {
            $builder->where('code', '<>', 0);
        });
    }

    public function scopeIn($query)
    {
        return $query->where('bank_type', 1);
    }

    public function scopeOut($query)
    {
        return $query->where('bank_type', 2);
    }

    public function scopeActive($query)
    {
        return $query->where('enable', 'Y');
    }

    public function scopeInactive($query)
    {
        return $query->where('enable', 'N');
    }

    public function scopeShow($query)
    {
        return $query->where('display_wallet', 'Y');
    }

    public function scopeHide($query)
    {
        return $query->where('display_wallet', 'N');
    }

    public function scopeTopup($query)
    {
        return $query->where('status_topup', 'Y');
    }

    public function scopeUntopup($query)
    {
        return $query->where('status_topup', 'N');
    }


    public function bank_payment()
    {
        return $this->hasMany(BankPaymentProxy::modelClass(), 'account_code');
    }

    public function bank()
    {
        return $this->belongsTo(BankProxy::modelClass(), 'banks');
    }
}
