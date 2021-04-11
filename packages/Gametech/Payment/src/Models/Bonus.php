<?php

namespace Gametech\Payment\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Product\Models\ProductProxy;
use Gametech\Payment\Contracts\Bonus as BonusContract;

class Bonus extends Model implements BonusContract
{
    protected $fillable = [];
}