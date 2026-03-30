<?php

declare(strict_types=1);

use MrPunyapal\LaravelAiAegis\Exceptions\AegisSecurityException;

describe('promptInjectionDetected', function (): void {
    test('creates exception with correct message and code', function (): void {
        $exception = AegisSecurityException::promptInjectionDetected(0.95);

        expect($exception)
            ->toBeInstanceOf(AegisSecurityException::class)
            ->and($exception->getCode())->toBe(403)
            ->and($exception->getMessage())->toContain('0.95')
            ->and($exception->getMessage())->toContain('Prompt injection detected');
    });

    test('formats confidence score in message', function (float $score, string $expected): void {
        $exception = AegisSecurityException::promptInjectionDetected($score);

        expect($exception->getMessage())->toContain($expected);
    })->with([
        [0.7, '0.7'],
        [1.0, '1'],
        [0.35, '0.35'],
    ]);
});

describe('piiLeakageDetected', function (): void {
    test('creates exception with correct message and code', function (): void {
        $exception = AegisSecurityException::piiLeakageDetected('email');

        expect($exception)
            ->toBeInstanceOf(AegisSecurityException::class)
            ->and($exception->getCode())->toBe(403)
            ->and($exception->getMessage())->toContain('email')
            ->and($exception->getMessage())->toContain('PII leakage detected');
    });

    test('includes the PII type in message', function (string $type): void {
        $exception = AegisSecurityException::piiLeakageDetected($type);

        expect($exception->getMessage())->toContain($type);
    })->with(['email', 'phone', 'ssn', 'credit_card', 'ip_address']);
});

test('extends RuntimeException', function (): void {
    $exception = AegisSecurityException::promptInjectionDetected(0.9);

    expect($exception)->toBeInstanceOf(RuntimeException::class);
});
