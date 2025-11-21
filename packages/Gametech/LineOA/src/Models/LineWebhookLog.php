<?php

namespace Gametech\LineOA\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Gametech\LineOA\Contracts\LineWebhookLog as LineWebhookLogContract;

class LineWebhookLog extends Model implements LineWebhookLogContract
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        // Add your fillable attributes here
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        // Add your attribute casts here
    ];
}