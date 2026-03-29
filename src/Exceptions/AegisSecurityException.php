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
}
