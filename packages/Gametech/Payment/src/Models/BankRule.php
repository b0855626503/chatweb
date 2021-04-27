<?php

namespace Gametech\Payment\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Gametech\Payment\Contracts\BankRule as BankRuleContract;

class BankRule extends Model implements BankRuleContract
{
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public $table = 'banks_rule';

    const CREATED_AT = 'date_create';
    const UPDATED_AT = 'date_update';

    protected $dateFormat = 'Y-m-d H:i:s';

    protected $primaryKey = 'code';


    public $fillable = [
        'bank_code',
        'method',
        'bank_number',
        'user_create',
        'user_update'
    ];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'code' => 'integer',
        'bank_code' => 'integer',
        'method' => 'string',
        'bank_number' => 'string',
        'user_create' => 'string',
        'user_update' => 'string'
    ];

    /**
     * Validation rules
     *
     * @var array
     */
    public static $rules = [
        'bank_code' => 'nullable|integer',
        'method' => 'required|string',
        'bank_number' => 'required|string',
        'user_create' => 'required|string|max:100',
        'user_update' => 'required|string|max:100',
        'date_create' => 'nullable',
        'date_update' => 'nullable'
    ];
}
