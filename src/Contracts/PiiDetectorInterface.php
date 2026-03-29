<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Contracts;

interface PiiDetectorInterface
{
    /**
     * Scan text and replace PII with context-preserving tokens.
     *
     * @param  array<int, string>  $piiTypes
     * @return array{text: string, session_id: string}
     */
    public function pseudonymize(string $text, array $piiTypes = []): array;

    /**
     * Restore tokens back to their original PII values.
     */
    public function depseudonymize(string $text, string $sessionId): string;
}
