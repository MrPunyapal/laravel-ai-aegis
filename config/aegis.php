<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Security Settings
    |--------------------------------------------------------------------------
    |
    | These values serve as the fallback when an Agent class does not have
    | the #[Aegis] attribute applied. Per-agent configuration via attributes
    | always takes precedence over these defaults.
    |
    */

    'block_injections' => env('AEGIS_BLOCK_INJECTIONS', true),
    'pseudonymize' => env('AEGIS_PSEUDONYMIZE', true),
    'strict_mode' => env('AEGIS_STRICT_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | PII Types to Detect
    |--------------------------------------------------------------------------
    |
    | Supported types: email, phone, ssn, credit_card, ip_address
    |
    */

    'pii_types' => array_filter(
        array_map(trim(...), explode(',', (string) env('AEGIS_PII_TYPES', 'email,phone,ssn,credit_card,ip_address'))),
    ),

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | The pseudonymization engine stores PII-to-token mappings in cache.
    | Redis is recommended for production use.
    |
    */

    'cache' => [
        'store' => env('AEGIS_CACHE_STORE', 'redis'),
        'prefix' => 'aegis_pii',
        'ttl' => env('AEGIS_CACHE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Injection Detection Threshold
    |--------------------------------------------------------------------------
    |
    | Confidence score (0.0 - 1.0) above which a prompt is considered
    | malicious and blocked. In strict mode, the threshold drops to 0.3.
    |
    */

    'injection_threshold' => env('AEGIS_INJECTION_THRESHOLD', 0.7),

    /*
    |--------------------------------------------------------------------------
    | Pulse Integration
    |--------------------------------------------------------------------------
    |
    | When enabled, Aegis records telemetry to Laravel Pulse for
    | blocked injections, pseudonymization volume, and compute savings.
    |
    */

    'pulse' => [
        'enabled' => env('AEGIS_PULSE_ENABLED', true),
    ],
];
