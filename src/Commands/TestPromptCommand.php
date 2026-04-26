<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Commands;

use Illuminate\Console\Command;
use MrPunyapal\LaravelAiAegis\Contracts\InjectionDetectorInterface;
use MrPunyapal\LaravelAiAegis\Contracts\PiiTransformerInterface;
use MrPunyapal\LaravelAiAegis\Pii\PiiRuleParser;

final class TestPromptCommand extends Command
{
    protected $signature = 'aegis:test {prompt : The prompt text to analyse}';

    protected $description = 'Run a prompt through Aegis injection detection and PII scanning';

    public function __construct(
        private readonly InjectionDetectorInterface $injectionDetector,
        private readonly PiiTransformerInterface $transformer,
        private readonly PiiRuleParser $parser,
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
        /** @var array<int, string|array<string, mixed>> $rawRules */
        $rawRules = (array) config('aegis.pii.rules', []);

        $rules = $this->parser->parseAll($rawRules);

        if ($rules === []) {
            $this->components->twoColumnDetail('<fg=white>PII detection</>', '<fg=gray>no rules configured</>');
            $this->newLine();

            return;
        }

        $result = $this->transformer->transform($prompt, $rules);

        $hasPii = $result->tokenCount > 0;
        $statusLabel = $hasPii
            ? '<fg=yellow;options=bold>PII DETECTED</>'
            : '<fg=green;options=bold>CLEAN</>';

        $this->components->twoColumnDetail('<fg=white>PII detection</>', $statusLabel);

        foreach ($rules as $rule) {
            $this->components->twoColumnDetail(
                '  Rule <fg=cyan>'.$rule->type.'</> ('.$rule->action->value.')',
                '',
            );
        }

        if ($hasPii) {
            $this->newLine();
            $this->components->twoColumnDetail('  Transformed text', $result->text);
            $this->components->twoColumnDetail('  Tokens replaced', (string) $result->tokenCount);
        }

        $this->newLine();
    }
}
