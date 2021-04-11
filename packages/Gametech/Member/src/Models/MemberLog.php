<?php

namespace Gametech\Member\Models;

use Alexmg86\LaravelSubQuery\Traits\LaravelSubQueryTrait;
use DateTimeInterface;
use Gametech\Member\Contracts\MemberLog as MemberLogContract;
use Illuminate\Database\Eloquent\Model;

class MemberLog extends Model implements MemberLogContract
{
    use LaravelSubQueryTrait;

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected $table = 'members_log';

    const CREATED_AT = 'date_create';
    const UPDATED_AT = 'date_update';

    protected $dateFormat = 'Y-m-d H:i:s';


    protected $primaryKey = 'code';

    protected $fillable = [
        'member_code',
        'mode',
        'menu',
        'record',
        'remark',
        'item_before',
        'item',
        'ip',
        'enable',
        'user_create',

    ];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'code' => 'integer',
        'member_code' => 'integer',
        'mode' => 'string',
        'menu' => 'string',
        'record' => 'integer',
        'remark' => 'string',
        'item_before' => 'string',
        'item' => 'string',
        'ip' => 'string',
        'enable' => 'string',
        'user_create' => 'string',

    ];

    /**
     * Validation rules
     *
     * @var array
     */
    public static $rules = [
        'member_code' => 'nullable|integer',
        'mode' => 'required|string|max:100',
        'menu' => 'required|string|max:100',
        'record' => 'required|integer',
        'remark' => 'required|string|max:255',
        'item_before' => 'required|string',
        'item' => 'required|string',
        'ip' => 'required|string|max:100',
        'enable' => 'required|string',
        'user_create' => 'required|string|max:100'
    ];

}
