<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Contracts;

interface RecorderInterface
{
    public function recordBlockedInjection(float $score): void;

    public function recordPseudonymization(int $tokenCount = 1): void;

    public function recordComputeSaved(float $estimatedCost): void;

    public function recordGuardRailViolation(string $stage): void;

    public function recordToolDenied(): void;

    public function recordApprovalEvent(bool $approved): void;
}

