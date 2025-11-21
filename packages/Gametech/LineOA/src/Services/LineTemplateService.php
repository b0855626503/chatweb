<?php

namespace Gametech\LineOA\Services;

use Gametech\LineOA\Models\LineTemplate;
use Illuminate\Support\Facades\Cache;

/**
 * LineTemplateService
 *
 * หน้าที่:
 *  - ดึงข้อความ template ตาม key ที่กำหนด
 *  - รองรับการแก้ไขข้อความจากหลังบ้าน (ผ่านตาราง line_templates)
 *  - ถ้าไม่เจอใน DB → ใช้ default จาก config/line_oa.php
 *  - แทนค่า {variable} ต่าง ๆ ลงในข้อความ (เช่น {username}, {phone})
 *
 * การใช้งานหลัก:
 *   $text = $templateService->render('register.ask_phone', [
 *       'contact_name' => 'ลูกค้า A',
 *   ]);
 */
class LineTemplateService
{
    /**
     * prefix cache สำหรับ template (กัน key ชนของที่อื่น)
     */
    protected string $cachePrefix = 'line_oa_template_';

    /**
     * เวลา cache template (วินาที)
     * - 0 หรือ null = ไม่ cache
     */
    protected int $cacheSeconds;

    public function __construct(?int $cacheSeconds = 60)
    {
        $this->cacheSeconds = $cacheSeconds ?? 0;
    }

    /**
     * render ข้อความ template ตาม key
     *
     * @param  string  $key  เช่น 'register.ask_phone'
     * @param  array  $vars  ตัวแปรสำหรับแทนค่าใน template เช่น ['username' => 'demo01']
     */
    public function render(string $key, array $vars = []): string
    {
        $template = $this->getMessage($key);

        if ($template === null || $template === '') {
            // กันเคสสุดขอบ ไม่ให้ return ค่าว่าง
            $template = $key;
        }

        return $this->replaceVariables($template, $vars);
    }

    /**
     * ดึงข้อความ template (raw string) ตาม key
     *  - ลองจาก DB ก่อน
     *  - ถ้าไม่มี → ใช้ config('line_oa.templates')
     */
    public function getMessage(string $key): ?string
    {
        // 1) ลองจาก cache ก่อน
        if ($this->cacheSeconds > 0) {
            $cacheKey = $this->cachePrefix.$key;

            return Cache::remember($cacheKey, $this->cacheSeconds, function () use ($key) {
                return $this->resolveMessage($key);
            });
        }

        // ไม่ใช้ cache
        return $this->resolveMessage($key);
    }

    /**
     * clear cache ของ template บางตัว
     */
    public function forget(string $key): void
    {
        if ($this->cacheSeconds > 0) {
            Cache::forget($this->cachePrefix.$key);
        }
    }

    /**
     * clear cache ของ template ทั้งหมด (เฉพาะที่ใช้ prefix นี้)
     * NOTE: ถ้าต้องแบบ global จริง ๆ อาจใช้ cache tags หรือวิธีอื่นตาม driver
     */
    public function clearAllCache(): void
    {
        // ถ้า cache driver รองรับ tag สามารถใช้ tag ได้
        // ที่นี่ให้เป็น placeholder ไว้ก่อน
        // ตัวจริงโบ๊ทจะเลือกใช้ตาม infra ที่ใช้
    }

    /**
     * ใช้ตอนหลังบ้าน save/อัปเดต template เสร็จ
     * เพื่อให้ค่าใหม่ถูกโหลดโดยไม่ต้องรอหมดเวลา cache
     */
    public function onTemplateUpdated(string $key): void
    {
        $this->forget($key);
    }

    /**
     * แกนจริงในการ "หา message จาก key"
     * 1) DB: line_templates
     * 2) config: line_oa.templates
     */
    protected function resolveMessage(string $key): ?string
    {
        // 1) ลองหาใน DB ก่อน
        $dbTemplate = LineTemplate::query()
            ->where('key', $key)
            ->where('enabled', true)
            ->orderByDesc('id')
            ->first();

        if ($dbTemplate && $dbTemplate->message !== null && $dbTemplate->message !== '') {
            return $dbTemplate->message;
        }

        // 2) ถ้าไม่เจอใน DB → ใช้ default จาก config
        $default = config('line_oa.templates.'.$key);

        if (is_string($default)) {
            return $default;
        }

        // 3) ถ้าไม่เจอจริง ๆ → null
        return null;
    }

    /**
     * แทนค่า {variable} ต่าง ๆ ใน message
     *
     * ตัวอย่าง:
     *   template: "ยินดีต้อนรับ {username} เข้าสู่ {web_name}"
     *   vars:     ['username' => 'demo01', 'web_name' => 'THEGRAND789']
     *   ผลลัพธ์: "ยินดีต้อนรับ demo01 เข้าสู่ THEGRAND789"
     */
    protected function replaceVariables(string $message, array $vars): string
    {
        if ($message === '' || empty($vars)) {
            return $message;
        }

        // เตรียม map สำหรับ strtr
        $replace = [];
        foreach ($vars as $key => $value) {
            // รองรับทั้ง {key} และ :key ถ้าอยากใช้แบบ Laravel-ish
            $replace['{'.$key.'}'] = (string) $value;
            $replace[':'.$key] = (string) $value;
        }

        return strtr($message, $replace);
    }

    /**
     * helper: ดึงค่า default ตาม key พร้อม fallback
     * (ถ้าวันหลังอยากใช้ที่อื่น)
     */
    public function getDefaultFromConfig(string $key, ?string $fallback = null): ?string
    {
        $value = config('line_oa.templates.'.$key);

        if (is_string($value)) {
            return $value;
        }

        return $fallback;
    }

    /**
     * ลิสต์ template ทั้งหมดที่เริ่มต้นด้วย prefix ที่กำหนด
     * เช่น 'register.' → จะได้ทุก key ที่เกี่ยวกับสมัครสมาชิก
     *
     * ใช้ประโยชน์ในหน้าหลังบ้าน เวลาอยากโชว์เป็นกลุ่ม
     */
    public function listByPrefix(string $prefix): array
    {
        $prefix = rtrim($prefix, '.').'.';

        $items = LineTemplate::query()
            ->where('key', 'like', $prefix.'%')
            ->orderBy('key')
            ->get(['key', 'message', 'enabled']);

        $result = [];

        foreach ($items as $item) {
            $result[] = [
                'key' => $item->key,
                'message' => $item->message,
                'enabled' => (bool) $item->enabled,
            ];
        }

        return $result;
    }
}
