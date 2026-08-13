<?php

declare(strict_types=1);

use Modules\Setup\Services\DatabaseSourceService;

return [
    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    */
    'storage_disk' => env('SETUP_STORAGE_DISK', 'local'),
    'import_path'  => 'imports',

    /*
    |--------------------------------------------------------------------------
    | Upload limits
    |--------------------------------------------------------------------------
    */
    'max_file_size' => 50 * 1024 * 1024, // 50 MB

    /*
    |--------------------------------------------------------------------------
    | Import processing
    |--------------------------------------------------------------------------
    */
    'batch_size' => 500,

    /*
    |--------------------------------------------------------------------------
    | AI Mapping (Claude API)
    |--------------------------------------------------------------------------
    | ai_enabled is true when ANTHROPIC_API_KEY is set.
    | Simplicity First + AI Assisted First: Claude suggests field mappings,
    | reducing onboarding time for non-technical users.
    |--------------------------------------------------------------------------
    */
    'ai_enabled' => (bool) env('ANTHROPIC_API_KEY'),
    'ai_model'   => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),

    /*
    |--------------------------------------------------------------------------
    | Known source ERPs
    |--------------------------------------------------------------------------
    | Africa First / Asia First: pre-configured patterns for the ERPs most
    | commonly used in francophone, Sub-Saharan African and Asian markets.
    |--------------------------------------------------------------------------
    */
    'known_erps' => DatabaseSourceService::KNOWN_ERPS,
];
