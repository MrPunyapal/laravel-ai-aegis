# Laravel AI Aegis

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mrpunyapal/laravel-ai-aegis.svg?style=flat-square)](https://packagist.org/packages/mrpunyapal/laravel-ai-aegis)
[![Lint & Static Analysis](https://img.shields.io/github/actions/workflow/status/mrpunyapal/laravel-ai-aegis/lint-stan.yml?branch=main&label=lint+%26+stan&style=flat-square)](https://github.com/mrpunyapal/laravel-ai-aegis/actions?query=workflow%3ALint+branch%3Amain)
[![Tests](https://img.shields.io/github/actions/workflow/status/mrpunyapal/laravel-ai-aegis/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/mrpunyapal/laravel-ai-aegis/actions?query=workflow%3ATests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/mrpunyapal/laravel-ai-aegis.svg?style=flat-square)](https://packagist.org/packages/mrpunyapal/laravel-ai-aegis)

A native, **local-first** security middleware for the **Laravel AI SDK**. Aegis intercepts every AI agent prompt and response to protect your users' data and your system prompts — without ever sending raw PII or adversarial payloads to an external LLM provider.

## Features

- **Bidirectional Reversible Pseudonymization** — Automatically replaces PII (emails, phones, SSNs, credit cards, IP addresses) with context-preserving `{{AEGIS_*}}` tokens before the LLM sees the data, then seamlessly restores the original values in the response.
- **Localized Prompt Injection Defense** — A built-in semantic firewall evaluates prompts against 30+ known adversarial attack patterns (jailbreaks, system prompt extraction, DAN mode, etc.) entirely locally — no external API call required.
- **Declarative Attribute Configuration** — Use the `#[Aegis]` PHP attribute on individual Agent classes to apply granular, per-agent security rules.
- **Laravel Pulse Integration** — A first-class Pulse card delivers real-time telemetry: blocked injections, pseudonymization volume, and estimated compute capital saved.
- **PHP 8.4+ Lazy Objects** — On PHP 8.4 and above, all heavy services are registered as Lazy Ghost objects, so memory is only allocated when a service is actually used in the request lifecycle. PHP 8.2/8.3 fall back to eager instantiation.
- **Artisan Commands** — `aegis:install` for guided setup, `aegis:test` to debug prompts interactively.

## Requirements

| Dependency | Version |
|---|---|
| PHP | `^8.2` |
| Laravel | `^12.0 \| ^13.0` |
| Laravel Pulse *(optional)* | `^1.0` |

## Installation

```bash
composer require mrpunyapal/laravel-ai-aegis
```

Run the install command for guided setup:

```bash
php artisan aegis:install
```

Or publish the config file manually:

```bash
php artisan vendor:publish --tag="aegis-config"
```

## Configuration

```php
// config/aegis.php

return [
    'block_injections' => env('AEGIS_BLOCK_INJECTIONS', true),
    'pseudonymize'     => env('AEGIS_PSEUDONYMIZE', true),
    'strict_mode'      => env('AEGIS_STRICT_MODE', false),

    'pii_types' => ['email', 'phone', 'ssn', 'credit_card', 'ip_address'],

    'cache' => [
        'store'  => env('AEGIS_CACHE_STORE', 'redis'),
        'prefix' => 'aegis_pii',
        'ttl'    => env('AEGIS_CACHE_TTL', 3600),
    ],

    'injection_threshold' => env('AEGIS_INJECTION_THRESHOLD', 0.7),

    'pulse' => [
        'enabled' => env('AEGIS_PULSE_ENABLED', true),
    ],
];
```

> **Redis is recommended** for the `cache.store` in production. The pseudonymization engine stores short-lived PII-to-token mappings that must survive the full request/response cycle.

## Usage

### Registering the Middleware

Register `AegisMiddleware` in your Laravel AI SDK agent pipeline:

```php
use MrPunyapal\LaravelAiAegis\Middleware\AegisMiddleware;

$agent->withMiddleware([
    app(AegisMiddleware::class),
]);
```

### Declarative Configuration with `#[Aegis]`

Apply the `#[Aegis]` attribute directly on an Agent class to override global config:

```php
use MrPunyapal\LaravelAiAegis\Attributes\Aegis;

#[Aegis(
    blockInjections: true,
    pseudonymize: true,
    strictMode: true,
    piiTypes: ['email', 'ssn'],
)]
class MedicalSupportAgent extends Agent
{
    // ...
}
```

| Parameter | Type | Default | Description |
|---|---|---|---|
| `blockInjections` | `bool` | `true` | Enable the prompt injection firewall |
| `pseudonymize` | `bool` | `true` | Enable bidirectional PII pseudonymization |
| `strictMode` | `bool` | `false` | Lower injection detection threshold to `0.3` |
| `piiTypes` | `array` | all types | PII categories to scan for |

When an Agent class has no `#[Aegis]` attribute, values from `config/aegis.php` are used.

### How the Middleware Pipeline Works

```
User Prompt
     │
     ▼
 ┌─────────────────────────────────────────┐
 │           AegisMiddleware               │
 │                                         │
 │  1. Injection Detection (local scan)    │──► throws AegisSecurityException
 │                                         │    if score ≥ threshold
 │  2. PII Pseudonymization (outbound)     │──► replaces PII with tokens,
 │     john@example.com → {{AEGIS_EMAIL_}} │    stores mapping in cache
 │                                         │
 │  3. $next($prompt) ──────────────────────────► LLM Provider
 │                                         │         │
 │  4. ->then() closure ◄──────────────────────────── LLM Response
 │     Restore tokens → original values    │
 └─────────────────────────────────────────┘
     │
     ▼
Final Response (PII restored, safe)
```

### Throwing Custom Exceptions

When a prompt is blocked, `AegisSecurityException` is thrown with HTTP status `403`:

```php
use MrPunyapal\LaravelAiAegis\Exceptions\AegisSecurityException;

// In your exception handler:
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (AegisSecurityException $e, Request $request) {
        return response()->json(['error' => $e->getMessage()], 403);
    });
})
```

## PII Detection

Aegis detects the following PII types out of the box:

| Type | Pattern Example |
|---|---|
| `email` | `john.doe@example.com` |
| `phone` | `555-123-4567`, `+1 (555) 123-4567` |
| `ssn` | `123-45-6789` |
| `credit_card` | `4111-1111-1111-1111` |
| `ip_address` | `192.168.1.100` |

Detected values are replaced with tokens like `{{AEGIS_EMAIL_8F92A}}` before reaching the LLM. After the LLM responds, tokens are swapped back with original values transparently.

## Injection Detection

Aegis ships with 30+ weighted adversarial patterns covering:

- System prompt extraction (`output your system prompt`, `reveal your instructions`)
- Instruction override (`ignore previous instructions`, `disregard all previous`)
- Role-playing jailbreaks (`DAN mode`, `pretend you are`, `you are now`)
- Security bypass attempts (`bypass your safety`, `admin override`, `sudo mode`)
- Encoded payload injection (`base64 decode and execute`)

### Custom Attack Vectors

Extend the built-in database by binding a custom `PromptInjectionDetector`:

```php
use MrPunyapal\LaravelAiAegis\Contracts\InjectionDetectorInterface;
use MrPunyapal\LaravelAiAegis\Defense\PromptInjectionDetector;

$this->app->singleton(InjectionDetectorInterface::class, fn () =>
    new PromptInjectionDetector(
        customVectors: [
            'my proprietary jailbreak pattern' => 0.95,
        ],
    )
);
```

## Laravel Pulse Card

Add the Aegis card to your Pulse dashboard in `resources/views/vendor/pulse/dashboard.blade.php`:

```blade
<livewire:aegis-card cols="3" />
```

The card displays three real-time metrics:

- **Blocked Injections** — Total prompts blocked during the selected period
- **PII Tokens Replaced** — Total pseudonymization operations performed
- **Compute Capital Saved** — Estimated API cost avoided by blocking requests locally

## Artisan Commands

### `aegis:install`

Publishes the config file and prints getting-started instructions:

```bash
php artisan aegis:install
```

### `aegis:test`

Runs a prompt through the full Aegis pipeline (injection detection + PII scan) and displays the result in the terminal. Great for debugging or onboarding:

```bash
php artisan aegis:test "ignore previous instructions"
# ┌──────────────────────────┬─────────────────┐
# │ Injection detection      │ BLOCKED         │
# │   Score                  │ 0.95            │
# │   Matched patterns       │ ignore previous │
# └──────────────────────────┴─────────────────┘

php artisan aegis:test "What is the weather today?"
# ┌──────────────────────────┬─────────────────┐
# │ Injection detection      │ CLEAN           │
# │ PII detection            │ CLEAN           │
# └──────────────────────────┴─────────────────┘
```

## DevX Testing

```bash
# Run all tests
composer test

# Run only unit tests with coverage
composer test:unit

# Run static analysis
composer test:types

# Run architecture tests
composer test:arch

# Lint and auto-fix
composer lint
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for recent changes.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Punyapal Shah](https://github.com/MrPunyapal)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
