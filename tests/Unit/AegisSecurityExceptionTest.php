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

describe('guardRailViolation', function (): void {
    test('creates exception with stage and reason', function (): void {
        $exception = AegisSecurityException::guardRailViolation('input', 'Blocked phrase detected');

        expect($exception)
            ->toBeInstanceOf(AegisSecurityException::class)
            ->and($exception->getCode())->toBe(403)
            ->and($exception->getMessage())->toContain('input')
            ->and($exception->getMessage())->toContain('Blocked phrase detected');
    });
});

describe('toolDenied', function (): void {
    test('creates exception with tool name', function (): void {
        $exception = AegisSecurityException::toolDenied('dangerous_tool');

        expect($exception)
            ->toBeInstanceOf(AegisSecurityException::class)
            ->and($exception->getCode())->toBe(403)
            ->and($exception->getMessage())->toContain('dangerous_tool');
    });
});

describe('maxInputLengthExceeded', function (): void {
    test('creates exception with 422 code', function (): void {
        $exception = AegisSecurityException::maxInputLengthExceeded(1500, 1000);

        expect($exception)
            ->toBeInstanceOf(AegisSecurityException::class)
            ->and($exception->getCode())->toBe(422)
            ->and($exception->getMessage())->toContain('1500')
            ->and($exception->getMessage())->toContain('1000');
    });
});

describe('approvalRequired', function (): void {
    test('creates exception with 403 code', function (): void {
        $exception = AegisSecurityException::approvalRequired('some content');

        expect($exception)
            ->toBeInstanceOf(AegisSecurityException::class)
            ->and($exception->getCode())->toBe(403);
    });
});

describe('approvalDenied', function (): void {
    test('creates exception with 403 code', function (): void {
        $exception = AegisSecurityException::approvalDenied();

        expect($exception)
            ->toBeInstanceOf(AegisSecurityException::class)
            ->and($exception->getCode())->toBe(403);
    });
});

