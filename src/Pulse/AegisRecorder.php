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

    public function recordGuardRailViolation(string $stage): void
    {
        if (! $this->pulseAvailable()) {
            return;
        }

        Pulse::record('aegis_guard_rail_violation', $stage, 1)->count();
    }

    public function recordToolDenied(): void
    {
        if (! $this->pulseAvailable()) {
            return;
        }

        Pulse::record('aegis_tool_denied', 'tool', 1)->count();
    }

    public function recordApprovalEvent(bool $approved): void
    {
        if (! $this->pulseAvailable()) {
            return;
        }

        Pulse::record('aegis_approval', $approved ? 'approved' : 'denied', 1)->count();
    }

    private function pulseAvailable(): bool
    {
        return (bool) Config::get('aegis.pulse.enabled', true)
            && class_exists(Pulse::class);
    }
}
