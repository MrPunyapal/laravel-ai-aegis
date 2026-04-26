# Laravel AI Aegis

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mrpunyapal/laravel-ai-aegis.svg?style=flat-square)](https://packagist.org/packages/mrpunyapal/laravel-ai-aegis)
[![Lint & Static Analysis](https://img.shields.io/github/actions/workflow/status/mrpunyapal/laravel-ai-aegis/lint-stan.yml?branch=main&label=lint+%26+stan&style=flat-square)](https://github.com/mrpunyapal/laravel-ai-aegis/actions?query=workflow%3ALint+branch%3Amain)
[![Tests](https://img.shields.io/github/actions/workflow/status/mrpunyapal/laravel-ai-aegis/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/mrpunyapal/laravel-ai-aegis/actions?query=workflow%3ATests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/mrpunyapal/laravel-ai-aegis.svg?style=flat-square)](https://packagist.org/packages/mrpunyapal/laravel-ai-aegis)

A native, **local-first** security middleware for the **Laravel AI SDK**. Aegis intercepts every AI agent prompt and response to protect your users' data and your system prompts — without ever sending raw PII or adversarial payloads to an external LLM provider.

## Features

- **Pluggable PII Rules with DSL** — Configure per-type actions (`tokenize`, `replace`, `mask`) directly in config or per-agent via the `#[Aegis]` attribute. Fine-tune masking depth with `email:mask,3,5`.
- **12 Built-in PII Types** — email, phone, SSN, credit card, IP address, full name, street address, date of birth, bank account, API key, JWT, URL — plus user-defined custom types.
- **Staged Guard Rail Pipeline** — Six pluggable security stages run in order: input (injection, length, blocked phrases), approval, PII transform, LLM call, output (PII leakage, blocked phrases), PII restore.
- **Localized Prompt Injection Defense** — A built-in semantic firewall evaluates prompts against 30+ known adversarial attack patterns entirely locally — no external API call required.
- **Per-Agent Declarative Configuration** — The `#[Aegis]` attribute on any Agent class overrides global defaults with fine-grained, per-route security rules.
- **Laravel Pulse Integration** — Real-time telemetry: blocked injections, guard rail violations, PII token volume, tool denials, and approval events.
- **Artisan Commands** — `aegis:install` for guided setup, `aegis:test` for interactive per-rule prompt diagnostics.

## Requirements

| Dependency | Version |
|---|---|
| PHP | `^8.3` |
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
    'pii' => [
        'enabled' => env('AEGIS_PII_ENABLED', true),

        // Rule formats — see "PII Rules DSL" section below
        'rules' => ['email:tokenize', 'phone:replace', 'ssn:mask,0,4'],

        // Custom PiiTypeInterface implementations
        'custom_detectors' => [],
    ],

    'guard_rails' => [
        'input' => [
            'injection' => [
                'enabled'          => env('AEGIS_BLOCK_INJECTIONS', true),
                'threshold'        => env('AEGIS_INJECTION_THRESHOLD', 0.7),
                'strict_threshold' => 0.3,
            ],
            'max_length'      => env('AEGIS_MAX_INPUT_LENGTH', null),
            'blocked_phrases' => [],
        ],
        'output' => [
            'pii_leakage'     => ['enabled' => env('AEGIS_BLOCK_OUTPUT_PII', true)],
            'blocked_phrases' => [],
        ],
        'tool'     => ['allowed' => [], 'blocked' => []],
        'approval' => ['enabled' => false, 'handler' => null],
    ],

    'strict_mode' => env('AEGIS_STRICT_MODE', false),

    'cache' => [
        'store'  => env('AEGIS_CACHE_STORE', 'redis'),
        'prefix' => 'aegis_pii',
        'ttl'    => env('AEGIS_CACHE_TTL', 3600),
    ],

    'pulse' => [
        'enabled' => env('AEGIS_PULSE_ENABLED', true),
    ],
];
```

> **Redis is recommended** for `cache.store` in production. Tokenized PII mappings must survive the full request/response cycle.

## Usage

### Registering the Middleware

```php
use MrPunyapal\LaravelAiAegis\Middleware\AegisMiddleware;

$agent->withMiddleware([
    app(AegisMiddleware::class),
]);
```

### How the Pipeline Works

```
User Prompt
     │
     ▼
 ┌────────────────────────────────────────────────┐
 │                AegisMiddleware                 │
 │                                                │
 │  1. runInput()  — injection, max-length,       │──► AegisSecurityException
 │                   blocked phrases              │    on any violation
 │                                                │
 │  2. runApproval() — custom approval handler    │──► AegisSecurityException
 │                     (if requireApproval=true)  │    if denied
 │                                                │
 │  3. PII transform (outbound)                   │
 │     john@example.com → {{AEGIS_EMAIL_8F92A}}   │
 │                                                │
 │  4. $next($prompt) ─────────────────────────────────► LLM Provider
 │                                                │           │
 │  5. ->then() ◄──────────────────────────────────────── LLM Response
 │     runOutput() — PII leakage, blocked phrases │
 │                                                │
 │  6. PII restore (inbound, tokenize only)       │
 │     {{AEGIS_EMAIL_8F92A}} → john@example.com   │
 └────────────────────────────────────────────────┘
     │
     ▼
Final Response (safe, PII restored)
```

## PII Rules DSL

Every rule is either a **string** (DSL) or a **structured array**. Rules are set globally in `config/aegis.php` under `pii.rules`, or per-agent via the `#[Aegis]` attribute.

### String DSL

| Rule | Action | Behaviour |
|---|---|---|
| `email` | tokenize | Reversible token — default when no action given |
| `email:tokenize` | tokenize | Explicit tokenize |
| `email:replace` | replace | Static `[REDACTED:EMAIL]` placeholder |
| `email:replace,***` | replace | Custom static text |
| `email:mask` | mask | Full mask with `*` |
| `email:mask,3` | mask | Keep 3 chars at start, mask rest |
| `email:mask,3,5` | mask | Keep 3 at start and 5 at end, mask middle |

> **Safety fallback**: when `maskStart + maskEnd ≥ value length`, the entire value is masked to prevent accidental leakage.

### Structured Array

```php
'rules' => [
    ['type' => 'email',  'action' => 'mask',    'mask_start' => 3, 'mask_end' => 5],
    ['type' => 'phone',  'action' => 'replace',  'replacement' => '[PHONE]'],
    ['type' => 'ssn',    'action' => 'tokenize'],
],
```

### Built-in PII Types

| Type | Detects |
|---|---|
| `email` | `john.doe@example.com` |
| `phone` | `555-123-4567`, `+1 (555) 123-4567` |
| `ssn` | `123-45-6789` |
| `credit_card` | `4111-1111-1111-1111` |
| `ip_address` | `192.168.1.100` |
| `name` | `John Smith`, `Mary Jane Watson` |
| `address` | `123 Main St`, `456 Oak Avenue` |
| `date_of_birth` | `01/15/1990`, `1990/01/15` |
| `bank_account` | 8–17 digit account numbers |
| `api_key` | `sk-abc123…`, `pk_live_…` |
| `jwt` | `eyJ…` (three-part JWT tokens) |
| `url` | `https://internal.company.com/…` |

### Custom PII Types

Implement `PiiTypeInterface` and register the class in config:

```php
use MrPunyapal\LaravelAiAegis\Contracts\PiiTypeInterface;

final readonly class NhsNumberType implements PiiTypeInterface
{
    public function type(): string    { return 'nhs_number'; }
    public function pattern(): string { return '/\b\d{3}\s\d{3}\s\d{4}\b/'; }
}
```

```php
// config/aegis.php
'pii' => [
    'custom_detectors' => [
        \App\Pii\NhsNumberType::class,
    ],
    'rules' => ['email:mask,3', 'nhs_number:replace'],
],
```

## Per-Agent Configuration — `#[Aegis]`

The `#[Aegis]` attribute on an Agent class overrides all global defaults for that agent. Every parameter is optional and falls back to config when omitted.

```php
use MrPunyapal\LaravelAiAegis\Attributes\Aegis;

#[Aegis(
    piiEnabled:           true,
    piiRules:             ['email:mask,3,5', 'ssn:replace', 'credit_card:tokenize'],
    blockInjections:      true,
    strictMode:           true,
    injectionThreshold:   0.4,
    inputBlockedPhrases:  ['competitor name', 'internal codename'],
    maxInputLength:       2000,
    blockOutputPii:       true,
    outputBlockedPhrases: ['confidential', 'internal only'],
    allowedTools:         ['weather_tool', 'calendar_tool'],
    blockedTools:         ['file_write_tool'],
    requireApproval:      true,
    approvalHandler:      \App\Handlers\HumanApprovalHandler::class,
)]
class MedicalSupportAgent extends Agent {}
```

| Parameter | Type | Default | Description |
|---|---|---|---|
| `piiEnabled` | `bool` | `true` | Enable PII transformation |
| `piiRules` | `array` | `[]` → config fallback | DSL strings or structured arrays |
| `blockInjections` | `bool` | `true` | Enable injection detection |
| `strictMode` | `bool` | `false` | Lower injection threshold to `0.3` |
| `injectionThreshold` | `?float` | `null` → config fallback | Override threshold for this agent |
| `inputBlockedPhrases` | `array` | `[]` → config fallback | Phrases that block the request |
| `maxInputLength` | `?int` | `null` → config fallback | Max prompt character count |
| `blockOutputPii` | `bool` | `true` | Scan LLM response for PII leakage |
| `outputBlockedPhrases` | `array` | `[]` → config fallback | Phrases blocked in responses |
| `allowedTools` | `array` | `[]` = all allowed | Allowlist of tool names |
| `blockedTools` | `array` | `[]` | Blocklist of tool names |
| `requireApproval` | `bool` | `false` | Require human approval before LLM call |
| `approvalHandler` | `?string` | `null` | FQCN of `ApprovalHandlerInterface` |

## Guard Rails

All guard rails are registered automatically by the service provider. Each implements `GuardRailInterface` and is scoped to a `GuardRailStage`.

### Built-in Guard Rails

| Guard Rail | Stage | What it does |
|---|---|---|
| `InjectionGuardRail` | `Input` | Blocks prompts above the injection threshold |
| `MaxLengthGuardRail` | `Input` | Blocks prompts exceeding `maxInputLength` |
| `BlockedPhrasesGuardRail` | `Input` / `Output` | Case-insensitive phrase blocklist |
| `OutputPiiGuardRail` | `Output` | Re-scans LLM response for PII leakage |
| `ToolGuardRail` | `Tool` | Enforces `allowedTools` / `blockedTools` |
| `ApprovalGuardRail` | `Approval` | Delegates to an `ApprovalHandlerInterface` |

### Custom Guard Rails

```php
use MrPunyapal\LaravelAiAegis\Contracts\GuardRailInterface;
use MrPunyapal\LaravelAiAegis\Data\GuardRailResult;
use MrPunyapal\LaravelAiAegis\Enums\GuardRailStage;

final readonly class ToxicityGuardRail implements GuardRailInterface
{
    public function stage(): GuardRailStage { return GuardRailStage::Input; }

    public function check(string $content, mixed $context): GuardRailResult
    {
        if ($this->isToxic($content)) {
            return GuardRailResult::fail(reason: 'Toxic content detected.', stage: 'input');
        }
        return GuardRailResult::pass();
    }
}
```

Register it in a service provider:

```php
$this->app->extend(GuardRailOrchestratorInterface::class, function ($orchestrator) {
    $orchestrator->register(new ToxicityGuardRail);
    return $orchestrator;
});
```

### Human Approval Handler

```php
use MrPunyapal\LaravelAiAegis\Contracts\ApprovalHandlerInterface;

final class SlackApprovalHandler implements ApprovalHandlerInterface
{
    public function approve(string $content, mixed $context): bool
    {
        // Send Slack notification and wait for response...
        return $this->waitForSlackApproval($content);
    }
}
```

## Exception Handling

All security violations throw `AegisSecurityException` (extends `RuntimeException`):

```php
use MrPunyapal\LaravelAiAegis\Exceptions\AegisSecurityException;

->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (AegisSecurityException $e, Request $request) {
        return response()->json(['error' => $e->getMessage()], $e->getCode());
    });
})
```

| Factory | HTTP code | When thrown |
|---|---|---|
| `promptInjectionDetected(float $score)` | 403 | Injection score ≥ threshold |
| `guardRailViolation(string $stage, string $reason)` | 403 | Any guard rail `check()` fails |
| `toolDenied(string $tool)` | 403 | Tool in blocklist or not in allowlist |
| `approvalRequired(string $content)` | 403 | No approval handler configured |
| `approvalDenied()` | 403 | Handler returned `false` |
| `maxInputLengthExceeded(int $length, int $max)` | 422 | Prompt too long |
| `piiLeakageDetected(string $type)` | 403 | PII found in LLM response |

## Injection Detection

The built-in `PromptInjectionDetector` scores prompts against 30+ weighted adversarial patterns:

- System prompt extraction (`output your system prompt`, `reveal your instructions`)
- Instruction override (`ignore previous instructions`, `disregard all previous`)
- Role-playing jailbreaks (`DAN mode`, `pretend you are`, `you are now`)
- Security bypass attempts (`bypass your safety`, `admin override`, `sudo mode`)
- Encoded payload injection (`base64 decode and execute`)

### Custom Attack Vectors

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

```blade
<livewire:aegis-card cols="3" />
```

Metrics displayed:

- **Blocked Injections** — Prompts blocked by the injection guard rail
- **Guard Rail Violations** — All violations across all stages
- **PII Tokens Replaced** — Pseudonymization operations performed
- **Tool Denials** — Tool calls blocked by the tool guard rail
- **Approval Events** — Human approval approvals and denials

## Artisan Commands

### `aegis:install`

Publishes the config and prints getting-started instructions:

```bash
php artisan aegis:install
```

### `aegis:test`

Runs a prompt through injection detection and all configured PII rules, displaying per-rule results:

```bash
php artisan aegis:test "ignore previous instructions"
# Injection detection  BLOCKED
#   Score              0.95
#   Matched patterns   ignore previous instructions

php artisan aegis:test "Contact john@example.com for info."
# Injection detection  CLEAN
# PII detection        PII DETECTED
#   Rule email (tokenize)
#   Transformed text   Contact {{AEGIS_EMAIL_8F92A}} for info.
#   Tokens replaced    1
```

## DevX Testing

```bash
composer test          # lint + types + type-coverage + unit tests
composer test:unit     # unit tests with coverage
composer test:types    # PHPStan static analysis
composer test:arch     # architecture tests
composer lint          # Rector + Pint auto-fix
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
| PHP | `^8.3` |
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
