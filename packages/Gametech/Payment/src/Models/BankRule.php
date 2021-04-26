<?php

namespace Gametech\Payment\Models;

use Illuminate\Database\Eloquent\Model;
use Gametech\Payment\Contracts\BankRule as BankRuleContract;

class BankRule extends Model implements BankRuleContract
{
    protected $fillable = [];
}