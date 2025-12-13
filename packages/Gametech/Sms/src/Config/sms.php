<?php

return [

    'providers' => [
        'vonage' => [
            'api_key' => env('VONAGE_API_KEY'),
            'api_secret' => env('VONAGE_API_SECRET'),
            'from' => env('VONAGE_SMS_FROM', 'GAMETECH'),
        ],
    ],

    'webhooks' => [
        'vonage' => [
            'token' => env('VONAGE_WEBHOOK_TOKEN'),
            'signature' => [
                'enabled' => env('VONAGE_WEBHOOK_SIG_ENABLED', false),
                'secret' => env('VONAGE_API_SIGNATURE_SECRET'),
                'method' => env('VONAGE_WEBHOOK_SIG_METHOD', 'md5hash'),
                'timestamp_tolerance' => env('VONAGE_WEBHOOK_SIG_TOLERANCE', 300),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Import settings
    |--------------------------------------------------------------------------
    */
    'import' => [
        'max_preview_rows' => env('SMS_IMPORT_PREVIEW_ROWS', 20),

        // คอลัมน์เบอร์ที่พยายามเดาให้ ถ้าไฟล์มี header
        'phone_column_candidates' => ['phone', 'tel', 'mobile', 'msisdn', 'เบอร์', 'เบอร์โทร', 'โทรศัพท์'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Members source settings (ให้ยืดหยุ่นกับ schema ของคุณ)
    |--------------------------------------------------------------------------
    */
    'members' => [
        // ชื่อ table/Model ของสมาชิก (คุณปรับตามระบบจริงได้)
        'table' => env('SMS_MEMBERS_TABLE', 'members'),
        'pk' => env('SMS_MEMBERS_PK', 'code'),
        'tel_column' => env('SMS_MEMBERS_TEL_COLUMN', 'tel'),

        // ถ้ามี consent column ให้ใส่ชื่อไว้ (ไม่มีก็ null)
        'consent_column' => env('SMS_MEMBERS_CONSENT_COLUMN', 'marketing_sms_consent'),
        'consent_yes_value' => env('SMS_MEMBERS_CONSENT_YES', 'Y'),
    ],

];
