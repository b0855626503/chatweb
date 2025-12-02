<?php

namespace Gametech\FacebookOA\Models;

use Gametech\FacebookOA\Contracts\FacebookTemplate as FacebookTemplateContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FacebookTemplate extends Model implements FacebookTemplateContract
{
    use HasFactory;

    protected $table = 'facebook_templates';

    protected $fillable = [
        'category',
        'key',
        'message_type',
        'message',
        'description',
        'enabled',
        'created_by',
        'updated_by',
        // ถ้าใน migration มี field language ค่อยเติม 'language' ตรงนี้เพิ่มได้
    ];

    protected $casts = [
        'enabled'    => 'boolean',
        // ถ้า message เก็บเป็น JSON string แล้วยังไม่อยากให้ auto-cast ก็ปล่อยไว้
        // ถ้าอยากให้ auto-cast เป็น array: 'message' => 'array',
    ];
}
