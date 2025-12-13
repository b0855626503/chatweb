<?php

use Gametech\Admin\Bouncer;

if (! function_exists('bouncer')) {
    /**
     * Global helper: bouncer()
     *
     * แนวทาง:
     * - ปกติคืนจาก container key 'bouncer' ถ้ามี (กันแตก instance และเปิดทางให้ทำ singleton ได้)
     * - ถ้ายังไม่ bind 'bouncer' ให้ resolve ด้วย Bouncer::class
     * - ถ้า container ยังไม่พร้อม (ถูกเรียกเร็วผิดปกติ) โยน error ที่อ่านง่าย
     */
    function bouncer()
    {
        if (function_exists('app')) {
            try {
                // ถ้าคุณไป bind ไว้เป็น singleton ใน AdminServiceProvider ภายหลัง → จะได้ instance เดียวทันที
                if (app()->bound('bouncer')) {
                    return app('bouncer');
                }

                // default: resolve ตาม class
                return app(Bouncer::class);
            } catch (\Throwable $e) {
                // fallthrough ไป throw ด้านล่าง
            }
        }

        throw new \RuntimeException('bouncer() helper was called before the application container is available.');
    }
}

if (! function_exists('showCleanRoutUrl')) {
    /**
     * Clean the url for the front end to display.
     *
     * @param string $link
     * @return void  (ฟังก์ชันนี้ echo ออกเหมือนเดิม)
     */
    function showCleanRoutUrl($link): void
    {
        $parsedUrl = parse_url($link);

        $routeUrl = '';

        if (isset($parsedUrl['path'])) {
            $routeUrl .= $parsedUrl['path'];
        }

        if (isset($parsedUrl['query'])) {
            $routeUrl .= '?' . $parsedUrl['query'];
        }

        echo $routeUrl;
    }
}
