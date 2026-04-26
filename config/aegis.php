<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | PII Detection & Transformation
    |--------------------------------------------------------------------------
    |
    | Configure which PII types to scan for and what action to apply.
    |
    | Rule formats (string DSL):
    |   'email'             → tokenize (reversible, default)
    |   'email:tokenize'    → tokenize
    |   'email:replace'     → replace with [REDACTED:EMAIL]
    |   'email:replace,***' → replace with static text
    |   'email:mask'        → full mask
    |   'email:mask,3'      → keep 3 chars at start
    |   'email:mask,3,5'    → keep 3 at start and 5 at end
    |
    | Rule format (structured array):
    |   ['type' => 'email', 'action' => 'mask', 'mask_start' => 3, 'mask_end' => 5]
    |
    | Built-in types: email, phone, ssn, credit_card, ip_address,
    |                 name, address, date_of_birth, bank_account,
    |                 api_key, jwt, url
    |
    | Add custom types by listing fully-qualified class names in custom_detectors.
    | Each class must implement PiiTypeInterface.
    |
    */

    'pii' => [
        'enabled' => env('AEGIS_PII_ENABLED', true),

        'rules' => array_filter(
            array_map(trim(...), explode(',', (string) env('AEGIS_PII_TYPES', 'email,phone,ssn,credit_card,ip_address'))),
        ),

        /**
         * Fully-qualified class names of custom PiiTypeInterface implementations.
         *
         * @var array<int, class-string>
         */
        'custom_detectors' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Guard Rails
    |--------------------------------------------------------------------------
    |
    | Configure which security stages are active and their policy settings.
    |
    */

    'guard_rails' => [
        'input' => [
            'injection' => [
                'enabled' => env('AEGIS_BLOCK_INJECTIONS', true),
                'threshold' => env('AEGIS_INJECTION_THRESHOLD', 0.7),
                'strict_threshold' => 0.3,
            ],
            'max_length' => env('AEGIS_MAX_INPUT_LENGTH'),
            'blocked_phrases' => [],
        ],

        'output' => [
            'pii_leakage' => [
                'enabled' => env('AEGIS_BLOCK_OUTPUT_PII', true),
            ],
            'blocked_phrases' => [],
        ],

        'tool' => [
            'allowed' => [],
            'blocked' => [],
        ],

        'approval' => [
            'enabled' => false,
            /**
             * Fully-qualified class name of an ApprovalHandlerInterface implementation.
             *
             * @var class-string|null
             */
            'handler' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Strict Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, the injection detection threshold drops to 0.3 globally.
    | Per-agent strictMode on the #[Aegis] attribute always takes precedence.
    |
    */

    'strict_mode' => env('AEGIS_STRICT_MODE', false),

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
    | Pulse Integration
    |--------------------------------------------------------------------------
    |
    | When enabled, Aegis records telemetry to Laravel Pulse.
    |
    */

    'pulse' => [
        'enabled' => env('AEGIS_PULSE_ENABLED', true),
    ],
];
