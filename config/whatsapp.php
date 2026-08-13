<?php

return [
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
    'verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
    'app_secret'   => env('WHATSAPP_APP_SECRET'),
    'api_version' => 'v20.0',
    'base_url' => 'https://graph.facebook.com',

    'ai_enabled' => true,
    'ai_provider' => 'anthropic',

    'auto_reply' => [
        'enabled' => true,
        'business_hours' => [
            'enabled' => false,
            'start' => '09:00',
            'end' => '18:00',
            'timezone' => 'UTC',
        ],
    ],
];
