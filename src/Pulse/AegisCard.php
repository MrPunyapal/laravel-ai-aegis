<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Pulse;

use Illuminate\Contracts\View\View;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

#[Lazy]
final class AegisCard extends Card
{
    public function render(): View
    {
        $blockedInjections = Pulse::aggregate(
            'aegis_blocked_injection',
            'count',
            $this->periodAsInterval(),
        );

        $pseudonymizationVolume = Pulse::aggregate(
            'aegis_pseudonymization',
            'count',
            $this->periodAsInterval(),
        );

        $computeSaved = Pulse::aggregate(
            'aegis_compute_saved',
            'sum',
            $this->periodAsInterval(),
        );

        return view('aegis::livewire.aegis-card', [
            'blockedInjections' => $blockedInjections,
            'pseudonymizationVolume' => $pseudonymizationVolume,
            'computeSaved' => $computeSaved,
        ]);
    }
}
