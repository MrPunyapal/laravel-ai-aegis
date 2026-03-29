<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Pulse;

use Illuminate\Support\Facades\Config;
use Laravel\Pulse\Facades\Pulse;
use MrPunyapal\LaravelAiAegis\Contracts\RecorderInterface;

final class AegisRecorder implements RecorderInterface
{
    public function recordBlockedInjection(float $score): void
    {
        if (! $this->pulseAvailable()) {
            return;
        }

        Pulse::record('aegis_blocked_injection', 'injection', (int) round($score * 100))->count();
    }

    public function recordPseudonymization(int $tokenCount = 1): void
    {
        if (! $this->pulseAvailable()) {
            return;
        }

        Pulse::record('aegis_pseudonymization', 'pii', $tokenCount)->count();
    }

    public function recordComputeSaved(float $estimatedCost): void
    {
        if (! $this->pulseAvailable()) {
            return;
        }

        Pulse::record('aegis_compute_saved', 'cost', (int) round($estimatedCost * 100))->sum();
    }

    private function pulseAvailable(): bool
    {
        return (bool) Config::get('aegis.pulse.enabled', true)
            && class_exists(Pulse::class);
    }
}
