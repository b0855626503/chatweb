<?php

namespace Gametech\LineOA\Models;

use Gametech\LineOA\Contracts\LineTemplate as LineTemplateContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
class LineTemplate extends Model implements LineTemplateContract
{
    protected $table = 'line_templates';

    protected $fillable = [
        'category',
        'key',
        'message',
        'message_type',
        'description',
        'enabled',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    /**
     * Scope: เอาเฉพาะ template ที่เปิดใช้งานอยู่
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope: filter ตาม category
     */
    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * หาด้วย key เดียว
     */
    public function scopeKey(Builder $query, string $key): Builder
    {
        return $query->where('key', $key);
    }

    /**
     * helper: หา template แรกตาม key (ใช้ใน service ได้)
     */
    public static function findByKey(string $key): ?self
    {
        return static::query()
            ->where('key', $key)
            ->first();
    }

    // ถ้าอยากผูกกับ model พนักงานจริง ๆ (เช่น Gametech\Admin\Models\Employee)
    // สามารถแก้เป็น belongsTo ได้
    //
    // public function creator()
    // {
    //     return $this->belongsTo(Employee::class, 'created_by');
    // }
    //
    // public function updater()
    // {
    //     return $this->belongsTo(Employee::class, 'updated_by');
    // }
}
