<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Facades\Pulse;
use MrPunyapal\LaravelAiAegis\Pulse\AegisRecorder;

beforeEach(function (): void {
    $this->recorder = new AegisRecorder;
});

describe('recordBlockedInjection', function (): void {
    test('records to Pulse when enabled', function (): void {
        Config::set('aegis.pulse.enabled', true);

        $entry = Mockery::mock(Entry::class);
        $entry->shouldReceive('count')->once();

        Pulse::shouldReceive('record')
            ->once()
            ->with('aegis_blocked_injection', 'injection', 95)
            ->andReturn($entry);

        $this->recorder->recordBlockedInjection(0.95);
    });

    test('does nothing when pulse is disabled', function (): void {
        Config::set('aegis.pulse.enabled', false);

        Pulse::shouldReceive('record')->never();

        $this->recorder->recordBlockedInjection(0.95);
    });

    test('rounds score to int correctly', function (float $score, int $expected): void {
        Config::set('aegis.pulse.enabled', true);

        $entry = Mockery::mock(Entry::class);
        $entry->shouldReceive('count')->once();

        Pulse::shouldReceive('record')
            ->once()
            ->with('aegis_blocked_injection', 'injection', $expected)
            ->andReturn($entry);

        $this->recorder->recordBlockedInjection($score);
    })->with([
        [0.95, 95],
        [0.7, 70],
        [1.0, 100],
        [0.356, 36],
    ]);
});

describe('recordPseudonymization', function (): void {
    test('records to Pulse when enabled', function (): void {
        Config::set('aegis.pulse.enabled', true);

        $entry = Mockery::mock(Entry::class);
        $entry->shouldReceive('count')->once();

        Pulse::shouldReceive('record')
            ->once()
            ->with('aegis_pseudonymization', 'pii', 1)
            ->andReturn($entry);

        $this->recorder->recordPseudonymization();
    });

    test('records custom token count', function (): void {
        Config::set('aegis.pulse.enabled', true);

        $entry = Mockery::mock(Entry::class);
        $entry->shouldReceive('count')->once();

        Pulse::shouldReceive('record')
            ->once()
            ->with('aegis_pseudonymization', 'pii', 5)
            ->andReturn($entry);

        $this->recorder->recordPseudonymization(5);
    });

    test('does nothing when pulse is disabled', function (): void {
        Config::set('aegis.pulse.enabled', false);

        Pulse::shouldReceive('record')->never();

        $this->recorder->recordPseudonymization();
    });
});

describe('recordComputeSaved', function (): void {
    test('records to Pulse when enabled', function (): void {
        Config::set('aegis.pulse.enabled', true);

        $entry = Mockery::mock(Entry::class);
        $entry->shouldReceive('sum')->once();

        Pulse::shouldReceive('record')
            ->once()
            ->with('aegis_compute_saved', 'cost', 3)
            ->andReturn($entry);

        $this->recorder->recordComputeSaved(0.03);
    });

    test('does nothing when pulse is disabled', function (): void {
        Config::set('aegis.pulse.enabled', false);

        Pulse::shouldReceive('record')->never();

        $this->recorder->recordComputeSaved(0.03);
    });
});
