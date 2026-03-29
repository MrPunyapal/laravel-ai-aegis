<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use MrPunyapal\LaravelAiAegis\Pseudonymization\PseudonymizationEngine;

beforeEach(function () {
    $this->cache = new Repository(new ArrayStore);
    $this->engine = new PseudonymizationEngine(
        cache: $this->cache,
        prefix: 'test_aegis',
        ttl: 3600,
    );
});

it('replaces email addresses with tokens', function () {
    $result = $this->engine->pseudonymize(
        'Contact john@example.com for details.',
        ['email'],
    );

    expect($result['text'])
        ->not->toContain('john@example.com')
        ->toContain('{{AEGIS_EMAIL_');
});

it('replaces phone numbers with tokens', function () {
    $result = $this->engine->pseudonymize(
        'Call me at 555-123-4567.',
        ['phone'],
    );

    expect($result['text'])
        ->not->toContain('555-123-4567')
        ->toContain('{{AEGIS_PHONE_');
});

it('replaces SSN with tokens', function () {
    $result = $this->engine->pseudonymize(
        'My SSN is 123-45-6789.',
        ['ssn'],
    );

    expect($result['text'])
        ->not->toContain('123-45-6789')
        ->toContain('{{AEGIS_SSN_');
});

it('replaces credit card numbers with tokens', function () {
    $result = $this->engine->pseudonymize(
        'Card: 4111-1111-1111-1111.',
        ['credit_card'],
    );

    expect($result['text'])
        ->not->toContain('4111-1111-1111-1111')
        ->toContain('{{AEGIS_CREDIT_CARD_');
});

it('replaces IP addresses with tokens', function () {
    $result = $this->engine->pseudonymize(
        'Server IP: 192.168.1.100.',
        ['ip_address'],
    );

    expect($result['text'])
        ->not->toContain('192.168.1.100')
        ->toContain('{{AEGIS_IP_ADDRESS_');
});

it('restores tokens back to original values', function () {
    $original = 'Contact john@example.com for details.';
    $result = $this->engine->pseudonymize($original, ['email']);

    $restored = $this->engine->depseudonymize($result['text'], $result['session_id']);

    expect($restored)->toBe($original);
});

it('handles text with no PII', function () {
    $text = 'This is a normal sentence with no PII.';
    $result = $this->engine->pseudonymize($text);

    expect($result['text'])->toBe($text);
});

it('handles multiple PII types in one text', function () {
    $text = 'Email: user@test.com, Phone: 555-888-9999, SSN: 111-22-3333.';
    $result = $this->engine->pseudonymize($text, ['email', 'phone', 'ssn']);

    expect($result['text'])
        ->not->toContain('user@test.com')
        ->not->toContain('555-888-9999')
        ->not->toContain('111-22-3333');

    $restored = $this->engine->depseudonymize($result['text'], $result['session_id']);

    expect($restored)->toBe($text);
});

it('returns original text when session not found', function () {
    $text = 'Text with {{AEGIS_EMAIL_ABC12}} token.';

    $restored = $this->engine->depseudonymize($text, 'nonexistent-session');

    expect($restored)->toBe($text);
});

it('ignores unknown PII types', function () {
    $text = 'Contact john@example.com.';
    $result = $this->engine->pseudonymize($text, ['unknown_type']);

    expect($result['text'])->toBe($text);
});

it('generates unique session IDs for each pseudonymization', function () {
    $result1 = $this->engine->pseudonymize('john@example.com', ['email']);
    $result2 = $this->engine->pseudonymize('jane@example.com', ['email']);

    expect($result1['session_id'])->not->toBe($result2['session_id']);
});
