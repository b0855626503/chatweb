<?php

use Gametech\Admin\Bouncer;

if (! function_exists('bouncer')) {
    /**
     * Global helper: bouncer()
     *
     * แนวทางที่นิ่งสุด:
     * - resolve ผ่าน Bouncer::class เป็นหลัก (source of truth)
     * - จะได้ไม่ผูกกับทิศทาง alias ของ 'bouncer'
     */
    function bouncer(): Bouncer
    {
        if (! function_exists('app')) {
            throw new \RuntimeException('bouncer() helper was called before the application container is available.');
        }

        return app(Bouncer::class);
    }
}

if (! function_exists('showCleanRoutUrl')) {
    /**
     * Clean the url for the front end to display.
     *
     * @param string $link
     * @return void
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
