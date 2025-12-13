<?php

use Gametech\Core\Core;

if (! function_exists('core')) {
    /**
     * Global helper: core()
     *
     * แนวทาง:
     * - ปกติคืน singleton จาก container key 'core'
     * - ถ้าช่วงต้นมาก ๆ container ยังไม่พร้อม/ยังไม่ bind → fallback ไป resolve Core::class
     */
    function core()
    {
        // ใน Laravel ปกติ app() จะพร้อม แต่บาง composer hook อาจเรียกก่อน bootstrap เต็ม
        if (function_exists('app')) {
            try {
                // ถ้า bind 'core' แล้ว ใช้ตัวนี้เพื่อให้ได้ singleton เดียวเสมอ
                if (app()->bound('core')) {
                    return app('core');
                }

                // ถ้ายังไม่ bound แต่ container มีอยู่ → resolve Core::class ไปก่อน
                return app(Core::class);
            } catch (\Throwable $e) {
                // fallback สุดท้าย: พยายาม new (ถ้า Core มี constructor ง่าย)
                // ถ้า Core ต้องพึ่ง dependency เยอะ ให้โยน exception ที่ชัดเจนแทน
            }
        }

        // fallback สุดท้าย (เลือก 1 อย่าง)
        // 1) ถ้า Core สร้างได้เอง:
        // return new Core();

        // 2) ถ้า Core ต้องพึ่ง dependency: โยน error ให้อ่านง่าย (แนะนำ)
        throw new \RuntimeException('core() helper was called before the application container is available.');
    }
}

if (! function_exists('array_permutation')) {
    function array_permutation($input)
    {
        $results = [];

        foreach ($input as $key => $values) {
            if (empty($values)) {
                continue;
            }

            if (empty($results)) {
                foreach ($values as $value) {
                    $results[] = [$key => $value];
                }
            } else {
                $append = [];

                foreach ($results as &$result) {
                    $result[$key] = array_shift($values);

                    $copy = $result;

                    foreach ($values as $item) {
                        $copy[$key] = $item;
                        $append[] = $copy;
                    }

                    array_unshift($values, $result[$key]);
                }

                $results = array_merge($results, $append);
            }
        }

        return $results;
    }
}
