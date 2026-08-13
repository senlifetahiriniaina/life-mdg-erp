<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Google Calendar OAuth2
    |--------------------------------------------------------------------------
    */
    'google' => [
        'client_id'     => env('GOOGLE_CALENDAR_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET'),
        'redirect_uri'  => env('GOOGLE_CALENDAR_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Microsoft Outlook / Graph API
    |--------------------------------------------------------------------------
    */
    'outlook' => [
        'client_id'     => env('OUTLOOK_CALENDAR_CLIENT_ID'),
        'client_secret' => env('OUTLOOK_CALENDAR_CLIENT_SECRET'),
        'tenant_id'     => env('OUTLOOK_TENANT_ID', 'common'),
        'redirect_uri'  => env('OUTLOOK_CALENDAR_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Apple Calendar (iCloud CalDAV)
    |--------------------------------------------------------------------------
    */
    'apple' => [
        'caldav_server' => env('APPLE_CALDAV_SERVER', 'https://caldav.icloud.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Settings
    |--------------------------------------------------------------------------
    */
    'sync_interval_minutes' => (int) env('CALENDAR_SYNC_INTERVAL', 15),

    /*
    |--------------------------------------------------------------------------
    | Africa First — Timezone defaults for African countries
    |--------------------------------------------------------------------------
    | Africa First: default timezones per country ISO code for calendar events.
    | XOF (CFA Franc UEMOA), XAF (CFA Franc CEMAC) countries default to Africa/* TZ.
    */
    'africa_timezones' => [
        'SN' => 'Africa/Dakar',      // Sénégal (XOF)
        'CI' => 'Africa/Abidjan',    // Côte d'Ivoire (XOF)
        'CM' => 'Africa/Douala',     // Cameroun (XAF)
        'KE' => 'Africa/Nairobi',    // Kenya (KES)
        'GH' => 'Africa/Accra',      // Ghana (GHS)
        'NG' => 'Africa/Lagos',      // Nigeria (NGN)
        'MG' => 'Indian/Antananarivo', // Madagascar (MGA)
        'MA' => 'Africa/Casablanca', // Maroc (MAD)
        'TN' => 'Africa/Tunis',      // Tunisie (TND)
        'EG' => 'Africa/Cairo',      // Égypte (EGP)
        'ET' => 'Africa/Addis_Ababa', // Éthiopie (ETB)
        'TZ' => 'Africa/Dar_es_Salaam', // Tanzanie (TZS)
        'ZA' => 'Africa/Johannesburg', // Afrique du Sud (ZAR)
        'ML' => 'Africa/Bamako',     // Mali (XOF)
        'BF' => 'Africa/Ouagadougou', // Burkina Faso (XOF)
        'NE' => 'Africa/Niamey',     // Niger (XOF)
        'TG' => 'Africa/Lome',       // Togo (XOF)
        'BJ' => 'Africa/Porto-Novo', // Bénin (XOF)
    ],

    /*
    |--------------------------------------------------------------------------
    | Asia First — Timezone defaults for Asian countries
    |--------------------------------------------------------------------------
    */
    'asia_timezones' => [
        'CN' => 'Asia/Shanghai',     // Chine (CNY)
        'IN' => 'Asia/Kolkata',      // Inde (INR)
        'JP' => 'Asia/Tokyo',        // Japon (JPY)
        'KR' => 'Asia/Seoul',        // Corée du Sud (KRW)
        'SG' => 'Asia/Singapore',    // Singapour (SGD)
        'TH' => 'Asia/Bangkok',      // Thaïlande (THB)
        'VN' => 'Asia/Ho_Chi_Minh',  // Vietnam (VND)
        'ID' => 'Asia/Jakarta',      // Indonésie (IDR)
        'MY' => 'Asia/Kuala_Lumpur', // Malaisie (MYR)
        'PH' => 'Asia/Manila',       // Philippines (PHP)
    ],

];
