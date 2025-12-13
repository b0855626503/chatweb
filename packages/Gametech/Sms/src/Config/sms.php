<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default SMS Provider
    |--------------------------------------------------------------------------
    |
    | ใช้เป็น provider หลักในการส่ง SMS
    | (DLR จะผูกกับ provider ที่ใช้ตอนส่ง)
    |
    */

    'default' => env('SMS_PROVIDER', 'vonage'),

    /*
    |--------------------------------------------------------------------------
    | SMS Providers
    |--------------------------------------------------------------------------
    |
    | กำหนด provider แต่ละเจ้า
    | - driver      : key สำหรับ logic / normalizer
    | - credentials : ใช้ตอนส่ง
    | - webhooks    : ใช้รับ DLR / inbound
    |
    */

    'providers' => [

        'vonage' => [
            'driver' => 'vonage',

            'credentials' => [
                'api_key'    => env('VONAGE_API_KEY'),
                'api_secret' => env('VONAGE_API_SECRET'),
                'from'       => env('VONAGE_SMS_FROM'),
            ],

            'webhooks' => [
                'dlr' => [
                    'url'       => env('VONAGE_DLR_URL'),
                    'method'    => 'GET',
                    'signature' => [
                        'enabled' => true,
                        'secret'  => env('VONAGE_SIGNATURE_SECRET'),
                        'method'  => env('VONAGE_SIGNATURE_METHOD', 'md5hash'),
                        'timestamp_tolerance' => env('VONAGE_SIGNATURE_TOLERANCE', 300),
                    ],
                ],

                'inbound' => [
                    'enabled' => true,
                ],
            ],

            /*
            | Vonage-specific behavior
            */
            'options' => [
                'supports_dlr' => true,
                'supports_unicode' => true,
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Example: Twilio (ยังไม่ implement ก็ใส่โครงไว้ได้)
        |--------------------------------------------------------------------------
        */
        'twilio' => [
            'driver' => 'twilio',

            'credentials' => [
                'account_sid' => env('TWILIO_ACCOUNT_SID'),
                'auth_token'  => env('TWILIO_AUTH_TOKEN'),
                'from'        => env('TWILIO_SMS_FROM'),
            ],

            'webhooks' => [
                'dlr' => [
                    'method' => 'POST',
                ],
            ],

            'options' => [
                'supports_dlr' => true,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery Receipt (DLR) Mapping
    |--------------------------------------------------------------------------
    |
    | mapping สถานะจาก provider → status กลางของระบบ
    | ใช้โดย SmsRecipient::applyDeliveryReceipt()
    |
    */

    'dlr_status_map' => [

        'vonage' => [
            'delivered'   => 'delivered',
            'accepted'    => 'sent',
            'buffered'    => 'sent',
            'failed'      => 'failed',
            'rejected'    => 'failed',
            'expired'     => 'failed',
            'undelivered' => 'failed',
        ],

        'twilio' => [
            'delivered'   => 'delivered',
            'sent'        => 'sent',
            'failed'      => 'failed',
            'undelivered' => 'failed',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Campaign Auto Finalize
    |--------------------------------------------------------------------------
    |
    | ใช้กับ job ที่ sweep campaign
    |
    */

    'campaign' => [
        'expire_after_hours' => 24,
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
