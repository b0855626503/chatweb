<?php

return [

    'default' => env('SMS_PROVIDER', 'vonage'),

    'providers' => [

        'vonage' => [
            'driver' => 'vonage',

            'credentials' => [
                'api_key' => env('VONAGE_API_KEY'),
                'api_secret' => env('VONAGE_API_SECRET'),
                'from' => env('VONAGE_SMS_FROM'),
            ],

            'webhooks' => [
                'dlr' => [
                    'url' => env('VONAGE_DLR_URL'),
                    'method' => env('VONAGE_DLR_METHOD', 'POST-JSON'),
                    'token' => env('SMS_WEBHOOK_TOKEN'),
                    'signature' => [
                        'enabled' => env('VONAGE_SIGNATURE_ENABLED', false),
                        'secret' => env('VONAGE_SIGNATURE_SECRET'),
                        'method' => env('VONAGE_SIGNATURE_METHOD', 'md5hash'),
                        'timestamp_tolerance' => (int) env('VONAGE_SIGNATURE_TOLERANCE', 300),
                    ],
                ],

                'inbound' => [
                    'enabled' => true,
                ],
            ],

            'options' => [
                'supports_dlr' => true,
                'supports_unicode' => true,
            ],
        ],

        'twilio' => [
            'driver' => 'twilio',

            'credentials' => [
                'account_sid' => env('TWILIO_ACCOUNT_SID'),
                'auth_token' => env('TWILIO_AUTH_TOKEN'),
                'from' => env('TWILIO_SMS_FROM'),
            ],

            'webhooks' => [
                'dlr' => [
                    // ตั้งให้ตรงกับ route จริง: /api/sms/webhook/twilio/dlr
                    // แนะนำให้ใส่ token ใน URL ด้วย เช่น .../dlr?token=xxxx
                    'url' => env('TWILIO_DLR_URL'),

                    // Twilio จะยิง StatusCallback เป็น POST (form-encoded)
                    'method' => env('TWILIO_DLR_METHOD', 'POST'),

                    // Shared token (เหมือน Vonage) เพื่อกันคนยิงมั่ว
                    'token' => env('SMS_WEBHOOK_TOKEN'),

                    // Signature: ยังไม่ implement ใน middleware ตัวนี้
                    // (ถ้าจะทำจริง ค่อยเพิ่ม verify แบบ X-Twilio-Signature)
                ],
            ],

            'options' => [
                'supports_dlr' => true,
                'supports_unicode' => true,
            ],
        ],
        'infobip' => [
            'driver' => 'infobip',

            'credentials' => [
                'base_url' => env('INFOBIP_BASE_URL'),
                'api_key'  => env('INFOBIP_API_KEY'),
                'from'     => env('INFOBIP_SMS_FROM'),
            ],

            'webhooks' => [
                'dlr' => [
                    'url'   => env('INFOBIP_DLR_URL'),
                    'token' => env('SMS_WEBHOOK_TOKEN'),
                ],
            ],

            'options' => [
                'supports_dlr'     => true,
                'supports_unicode' => true,
            ],
        ],
    ],

    'dlr_status_map' => [

        'vonage' => [
            'delivered' => 'delivered',
            'accepted' => 'sent',
            'buffered' => 'sent',
            'failed' => 'failed',
            'rejected' => 'failed',
            'expired' => 'failed',
            'undelivered' => 'failed',
        ],

        'twilio' => [
            'delivered' => 'delivered',
            'sent' => 'sent',
            'failed' => 'failed',
            'undelivered' => 'failed',
        ],
    ],

    'campaign' => [
        'expire_after_hours' => 24,
    ],

    'import' => [
        'max_preview_rows' => env('SMS_IMPORT_PREVIEW_ROWS', 20),
        'phone_column_candidates' => ['phone', 'tel', 'mobile', 'msisdn', 'เบอร์', 'เบอร์โทร', 'โทรศัพท์'],
    ],

    'members' => [
        'table' => env('SMS_MEMBERS_TABLE', 'members'),
        'pk' => env('SMS_MEMBERS_PK', 'code'),
        'tel_column' => env('SMS_MEMBERS_TEL_COLUMN', 'tel'),
        'consent_column' => env('SMS_MEMBERS_CONSENT_COLUMN', 'marketing_sms_consent'),
        'consent_yes_value' => env('SMS_MEMBERS_CONSENT_YES', 'Y'),
    ],

];
