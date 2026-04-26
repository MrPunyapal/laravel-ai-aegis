<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Data;

final readonly class GuardRailResult
{
    private function __construct(
        public bool $passed,
        public ?string $reason,
        public ?string $stage,
    ) {}

    public static function pass(): self
    {
        return new self(passed: true, reason: null, stage: null);
    }

    public static function fail(string $reason, string $stage): self
    {
        return new self(passed: false, reason: $reason, stage: $stage);
    }
}
