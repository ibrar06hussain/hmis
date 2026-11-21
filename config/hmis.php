<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Facility Identity
    |--------------------------------------------------------------------------
    */
    'facility_code' => env('HMIS_FACILITY_CODE', 'CHG'),
    'facility_name' => env('HMIS_FACILITY_NAME', 'Cardiac Hospital Gilgit'),
    'facility_short_name' => env('HMIS_FACILITY_SHORT_NAME', 'CHG HMIS'),
    'facility_region' => env('HMIS_FACILITY_REGION', 'Gilgit-Baltistan, Pakistan'),

    /*
    |--------------------------------------------------------------------------
    | Timezone & Presentation
    |--------------------------------------------------------------------------
    | Internal database timestamps are ALWAYS stored in UTC (config('app.timezone')).
    | Clinical UI and reports render timestamps in Asia/Karachi by default.
    */
    'display_timezone' => env('HMIS_DISPLAY_TIMEZONE', 'Asia/Karachi'),

    /*
    |--------------------------------------------------------------------------
    | Deployment & Safety Flags
    |--------------------------------------------------------------------------
    */
    'offline_lan_mode' => (bool) env('HMIS_OFFLINE_LAN_MODE', true),
    'demo_mode' => (bool) env('HMIS_DEMO_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Access Control & Break-Glass Policy
    |--------------------------------------------------------------------------
    */
    'break_glass_ttl_hours' => (int) env('HMIS_BREAK_GLASS_TTL_HOURS', 4),

    /*
    |--------------------------------------------------------------------------
    | Two-Factor Authentication (Offline RFC 6238 TOTP)
    |--------------------------------------------------------------------------
    */
    'two_factor_enforced' => (bool) env('HMIS_2FA_ENFORCED', false),
    'two_factor_privileged_roles' => [
        'it_administrator',
        'administrator',
        'medical_superintendent',
        'hospital_director',
        'consultant',
    ],

    /*
    |--------------------------------------------------------------------------
    | Localization (English default, Urdu RTL supported)
    |--------------------------------------------------------------------------
    */
    'supported_locales' => ['en', 'ur'],

    /*
    |--------------------------------------------------------------------------
    | Future Integration Feature Gates (Explicitly Disabled by Default)
    |--------------------------------------------------------------------------
    */
    'pacs' => [
        'enabled' => (bool) env('HMIS_PACS_ENABLED', false),
        'orthanc_url' => env('HMIS_PACS_ORTHANC_URL'),
        'ohif_viewer_url' => env('HMIS_PACS_OHIF_URL'),
    ],

    'ai' => [
        'enabled' => (bool) env('HMIS_AI_ENABLED', false),
        'provider' => env('HMIS_AI_PROVIDER', 'disabled'),
        'whisper_url' => env('HMIS_AI_WHISPER_URL'),
    ],

    'fhir' => [
        'mrn_system' => env('HMIS_FHIR_MRN_SYSTEM', 'urn:oid:2.16.586.1.1001.1'),
        'cnic_system' => 'https://nadra.gov.pk/cnic',
    ],
];
