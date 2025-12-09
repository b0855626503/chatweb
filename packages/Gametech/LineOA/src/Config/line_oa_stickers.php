<?php

return [

    /*
    |--------------------------------------------------------------------------
    | LINE OA Sticker Packs
    |--------------------------------------------------------------------------
    |
    | กำหนดชุดสติกเกอร์หลาย ๆ อันให้ UI ใช้งาน
    | - id         : ใช้ภายในระบบเราเอง (string)
    | - title      : ชื่อที่แสดงใน UI
    | - package_id : LINE sticker packageId
    | - stickers   : list ของ stickerId (string หรือ int ก็ได้)
    |
    */

    'packs' => [

        [
            'id'         => 'default1',
            'title'      => 'Default Pack #1',
            'package_id' => '1',
            'stickers'   => [
                '1', '2', '3', '4', '5', '6', '7', '8',
                '9', '10', '11', '12', '13', '14', '15', '16', '17',
            ],
        ],

        [
            'id'         => 'cony_brown',
            'title'      => 'Cony & Brown',
            'package_id' => '1070',
            'stickers'   => [
                '3', '4', '13', '450', '451', '452', '453',
            ],
        ],

        [
            'id'         => 'sally',
            'title'      => 'Sally',
            'package_id' => '6147',
            'stickers'   => [
                '13287720', '13287722', '13287725',
            ],
        ],

    ],

];
