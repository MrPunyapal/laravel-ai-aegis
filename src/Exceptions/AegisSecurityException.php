<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Exceptions;

use RuntimeException;

final class AegisSecurityException extends RuntimeException
{
    public static function promptInjectionDetected(float $score): self
    {
        return new self(
            message: "Aegis: Prompt injection detected (confidence: {$score}). Request blocked.",
            code: 403,
        );
    }

    public static function piiLeakageDetected(string $type): self
    {
        return new self(
            message: "Aegis: PII leakage detected in response ({$type}). Response blocked.",
            code: 403,
        );
    }

    public static function guardRailViolation(string $stage, string $reason): self
    {
        return new self(
            message: "Aegis: Guard rail violation at stage [{$stage}]: {$reason}",
            code: 403,
        );
    }

    public static function toolDenied(string $tool): self
    {
        return new self(
            message: "Aegis: Tool call denied: \"{$tool}\" is not permitted in this context.",
            code: 403,
        );
    }

    public static function approvalRequired(string $reason = 'Human approval required before proceeding.'): self
    {
        return new self(
            message: "Aegis: {$reason}",
            code: 403,
        );
    }

    public static function approvalDenied(): self
    {
        return new self(
            message: 'Aegis: Request denied by approval handler.',
            code: 403,
        );
    }

    public static function maxInputLengthExceeded(int $length, int $max): self
    {
        return new self(
            message: "Aegis: Input exceeds maximum allowed length ({$length} > {$max}).",
            code: 422,
        );
    }
}
