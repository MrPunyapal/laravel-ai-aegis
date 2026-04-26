<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use MrPunyapal\LaravelAiAegis\Data\PiiRuleConfig;
use MrPunyapal\LaravelAiAegis\Enums\PiiAction;
use MrPunyapal\LaravelAiAegis\Pii\PiiRuleParser;
use MrPunyapal\LaravelAiAegis\Pii\PiiTypeRegistry;
use MrPunyapal\LaravelAiAegis\Pii\Types\CreditCardType;
use MrPunyapal\LaravelAiAegis\Pii\Types\EmailType;
use MrPunyapal\LaravelAiAegis\Pii\Types\IpAddressType;
use MrPunyapal\LaravelAiAegis\Pii\Types\PhoneType;
use MrPunyapal\LaravelAiAegis\Pii\Types\SsnType;
use MrPunyapal\LaravelAiAegis\Pseudonymization\PseudonymizationEngine;

beforeEach(function (): void {
    $this->cache = new Repository(new ArrayStore);

    $this->registry = new PiiTypeRegistry;
    $this->registry->register(new EmailType);
    $this->registry->register(new PhoneType);
    $this->registry->register(new SsnType);
    $this->registry->register(new CreditCardType);
    $this->registry->register(new IpAddressType);

    $this->engine = new PseudonymizationEngine(
        cache: $this->cache,
        registry: $this->registry,
        prefix: 'test_aegis',
        ttl: 3600,
    );

    $this->parser = new PiiRuleParser($this->registry);
});

// --- Tokenize action ---

it('tokenizes email addresses and restores them', function (): void {
    $rules = $this->parser->parseAll(['email:tokenize']);
    $original = 'Contact john@example.com for details.';

    $result = $this->engine->transform($original, $rules);

    expect($result->tokenCount)->toBe(1)
        ->and($result->text)->not->toContain('john@example.com');

    $restored = $this->engine->restore($result->text, $result->sessionId);

    expect($restored)->toBe($original);
});

it('tokenizes phone numbers', function (): void {
    $rules = $this->parser->parseAll(['phone:tokenize']);

    $result = $this->engine->transform('Call me at 555-123-4567.', $rules);

    expect($result->tokenCount)->toBe(1)
        ->and($result->text)->not->toContain('555-123-4567');
});

it('tokenizes SSN', function (): void {
    $rules = $this->parser->parseAll(['ssn:tokenize']);

    $result = $this->engine->transform('My SSN is 123-45-6789.', $rules);

    expect($result->tokenCount)->toBe(1)
        ->and($result->text)->not->toContain('123-45-6789');
});

it('tokenizes credit card numbers', function (): void {
    $rules = $this->parser->parseAll(['credit_card:tokenize']);

    $result = $this->engine->transform('Card: 4111-1111-1111-1111.', $rules);

    expect($result->tokenCount)->toBe(1)
        ->and($result->text)->not->toContain('4111-1111-1111-1111');
});

it('tokenizes IP addresses', function (): void {
    $rules = $this->parser->parseAll(['ip_address:tokenize']);

    $result = $this->engine->transform('Server IP: 192.168.1.100.', $rules);

    expect($result->tokenCount)->toBe(1)
        ->and($result->text)->not->toContain('192.168.1.100');
});

// --- Replace action ---

it('replaces email with default placeholder', function (): void {
    $rules = $this->parser->parseAll(['email:replace']);

    $result = $this->engine->transform('Contact john@example.com.', $rules);

    expect($result->text)->toContain('[REDACTED:EMAIL]')
        ->and($result->tokenCount)->toBe(1);
});

it('replaces email with custom placeholder', function (): void {
    $rules = $this->parser->parseAll(['email:replace,***EMAIL***']);

    $result = $this->engine->transform('Contact john@example.com.', $rules);

    expect($result->text)->toContain('***EMAIL***')
        ->and($result->text)->not->toContain('john@example.com');
});

// --- Mask action ---

it('masks email fully when no start/end provided', function (): void {
    $rules = $this->parser->parseAll(['email:mask']);

    $result = $this->engine->transform('Contact john@example.com.', $rules);

    expect($result->text)->not->toContain('john@example.com')
        ->and($result->tokenCount)->toBe(1);
});

it('masks email keeping start chars', function (): void {
    $rule = new PiiRuleConfig(
        type: 'email',
        action: PiiAction::Mask,
        maskStart: 4,
        maskEnd: 0,
    );

    $result = $this->engine->transform('Contact john@example.com.', [$rule]);

    expect($result->text)->toContain('john')
        ->and($result->text)->not->toContain('@example.com');
});

it('masks email keeping start and end chars', function (): void {
    $rule = new PiiRuleConfig(
        type: 'email',
        action: PiiAction::Mask,
        maskStart: 3,
        maskEnd: 4,
    );

    $email = 'john@example.com';
    $result = $this->engine->transform("Contact {$email}.", [$rule]);

    // First 3: 'joh', last 4: '.com'
    expect($result->text)->toContain('joh')
        ->and($result->text)->toContain('.com')
        ->and($result->text)->toContain('*');
});

it('falls back to full mask when maskStart + maskEnd >= value length', function (): void {
    $rule = new PiiRuleConfig(
        type: 'email',
        action: PiiAction::Mask,
        maskStart: 20,
        maskEnd: 20,
    );

    $result = $this->engine->transform('a@b.com.', [$rule]);

    // Should not leak any part of 'a@b.com'
    expect($result->text)->not->toContain('a@b.com')
        ->and($result->text)->toContain('*');
});

// --- Restore ---

it('returns text unchanged when sessionId has no cached tokenMap', function (): void {
    $restored = $this->engine->restore('some text', 'nonexistent-session');

    expect($restored)->toBe('some text');
});

it('handles zero token count when text has no matching PII', function (): void {
    $rules = $this->parser->parseAll(['email:tokenize']);

    $result = $this->engine->transform('Hello, no email here.', $rules);

    expect($result->tokenCount)->toBe(0)
        ->and($result->text)->toBe('Hello, no email here.');
});

it('skips rules for PII types not in the registry', function (): void {
    $rule = new PiiRuleConfig(type: 'unregistered_type', action: PiiAction::Tokenize);

    $result = $this->engine->transform('Some text here.', [$rule]);

    expect($result->tokenCount)->toBe(0)
        ->and($result->text)->toBe('Some text here.');
});
