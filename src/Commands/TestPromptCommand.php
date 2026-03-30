<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Commands;

use Illuminate\Console\Command;
use MrPunyapal\LaravelAiAegis\Contracts\InjectionDetectorInterface;
use MrPunyapal\LaravelAiAegis\Contracts\PiiDetectorInterface;

final class TestPromptCommand extends Command
{
    protected $signature = 'aegis:test {prompt : The prompt text to analyse}';

    protected $description = 'Run a prompt through Aegis injection detection and PII scanning';

    public function __construct(
        private readonly InjectionDetectorInterface $injectionDetector,
        private readonly PiiDetectorInterface $piiDetector,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        /** @var string $prompt */
        $prompt = $this->argument('prompt');

        $this->newLine();
        $this->line('  <fg=white;options=bold>Analysing prompt:</> '.$prompt);
        $this->newLine();

        $this->runInjectionCheck($prompt);
        $this->runPiiCheck($prompt);

        return self::SUCCESS;
    }

    private function runInjectionCheck(string $prompt): void
    {
        $result = $this->injectionDetector->evaluate($prompt);

        $statusLabel = $result['is_malicious']
            ? '<fg=red;options=bold>BLOCKED</>'
            : '<fg=green;options=bold>CLEAN</>';

        $this->components->twoColumnDetail('<fg=white>Injection detection</>', $statusLabel);
        $this->components->twoColumnDetail('  Score', (string) $result['score']);

        if ($result['matched_patterns'] !== []) {
            $this->components->twoColumnDetail('  Matched patterns', implode(', ', $result['matched_patterns']));
        }

        $this->newLine();
    }

    private function runPiiCheck(string $prompt): void
    {
        /** @var array<int, string> $piiTypes */
        $piiTypes = (array) config('aegis.pii_types', ['email', 'phone', 'ssn', 'credit_card', 'ip_address']);

        $result = $this->piiDetector->pseudonymize($prompt, $piiTypes);

        $hasPii = $result['text'] !== $prompt;
        $statusLabel = $hasPii
            ? '<fg=yellow;options=bold>PII DETECTED</>'
            : '<fg=green;options=bold>CLEAN</>';

        $this->components->twoColumnDetail('<fg=white>PII detection</>', $statusLabel);

        if ($hasPii) {
            $this->components->twoColumnDetail('  Redacted text', $result['text']);
        }

        $this->newLine();
    }
}
