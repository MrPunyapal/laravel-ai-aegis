<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Pulse;

use Illuminate\Support\Facades\Config;
use MrPunyapal\LaravelAiAegis\Contracts\RecorderInterface;

final class AegisRecorder implements RecorderInterface
{
    public function recordBlockedInjection(float $score): void
    {
        if (! $this->pulseAvailable()) {
            return;
        }

        \Laravel\Pulse\Facades\Pulse::record('aegis_blocked_injection', 'injection', $score)->count();
    }

    public function recordPseudonymization(int $tokenCount = 1): void
    {
        if (! $this->pulseAvailable()) {
            return;
        }

        \Laravel\Pulse\Facades\Pulse::record('aegis_pseudonymization', 'pii', $tokenCount)->count();
    }

    public function recordComputeSaved(float $estimatedCost): void
    {
        if (! $this->pulseAvailable()) {
            return;
        }

        \Laravel\Pulse\Facades\Pulse::record('aegis_compute_saved', 'cost', $estimatedCost)->sum();
    }

    private function pulseAvailable(): bool
    {
        return (bool) Config::get('aegis.pulse.enabled', true)
            && class_exists(\Laravel\Pulse\Facades\Pulse::class);
    }
}
