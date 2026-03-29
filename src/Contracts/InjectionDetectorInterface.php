<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Contracts;

interface InjectionDetectorInterface
{
    /**
     * Evaluate a prompt against known adversarial attack vectors.
     *
     * @return array{is_malicious: bool, score: float, matched_patterns: array<int, string>}
     */
    public function evaluate(string $prompt): array;
}
