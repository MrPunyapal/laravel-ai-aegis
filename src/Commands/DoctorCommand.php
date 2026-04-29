<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use MrPunyapal\LaravelAiAegis\Support\AegisDoctor;

#[Signature('aegis:doctor {--json : Output the report as JSON}')]
#[Description('Validate the current Aegis configuration and optional integrations')]
final class DoctorCommand extends Command
{
    public function __construct(
        private readonly AegisDoctor $doctor,
    ) {
        parent::__construct();
    }

    /**
     * Run the doctor checks and print a report.
     */
    public function handle(): int
    {
        $checks = $this->doctor->inspect();
        $summary = $this->doctor->summarize($checks);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'summary' => $summary,
                'checks' => $checks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $this->doctor->hasFailures($checks) ? self::FAILURE : self::SUCCESS;
        }

        $this->newLine();
        $this->line(' <fg=white;options=bold>Aegis doctor report</>');
        $this->newLine();

        foreach ($checks as $check) {
            $this->components->twoColumnDetail('<fg=white>'.$check['label'].'</>', $this->statusLabel($check['status']));
            $this->line('  '.$check['message']);
            $this->newLine();
        }

        if ($summary['fail'] > 0) {
            $this->components->error(sprintf('Aegis doctor found %d failure(s) and %d warning(s).', $summary['fail'], $summary['warn']));

            return self::FAILURE;
        }

        if ($summary['warn'] > 0) {
            $this->components->warn(sprintf('Aegis doctor found %d warning(s).', $summary['warn']));

            return self::SUCCESS;
        }

        $this->components->info('Aegis is ready.');

        return self::SUCCESS;
    }

    /**
     * Format a doctor status label for console output.
     */
    private function statusLabel(string $status): string
    {
        return match ($status) {
            'pass' => '<fg=green;options=bold>PASS</>',
            'warn' => '<fg=yellow;options=bold>WARN</>',
            default => '<fg=red;options=bold>FAIL</>',
        };
    }
}
