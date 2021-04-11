<?php

namespace Gametech\Core\Models;

use Illuminate\Database\Eloquent\Model;

use Gametech\Core\Contracts\Log as LogContract;

class Log extends Model implements LogContract
{
    protected $fillable = [];
}
