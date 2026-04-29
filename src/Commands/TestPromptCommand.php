<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use MrPunyapal\LaravelAiAegis\Attributes\Aegis;
use MrPunyapal\LaravelAiAegis\Contracts\InjectionDetectorInterface;
use MrPunyapal\LaravelAiAegis\Contracts\PiiTransformerInterface;
use MrPunyapal\LaravelAiAegis\Data\AegisConfig;
use MrPunyapal\LaravelAiAegis\Data\PiiRuleConfig;
use MrPunyapal\LaravelAiAegis\Enums\PiiAction;
use MrPunyapal\LaravelAiAegis\Support\AegisConfigResolver;
use ReflectionClass;

#[Signature('aegis:test {prompt? : The prompt text to analyse} {--agent= : Agent class to resolve Aegis attribute overrides from} {--response= : Response text to inspect for output-stage diagnostics} {--stage=full : Which stage to inspect (input, output, full)} {--explain : Show the resolved Aegis config} {--json : Output diagnostics as JSON}')]
#[Description('Run prompt and response text through Aegis diagnostics using the resolved configuration')]
final class TestPromptCommand extends Command
{
    public function __construct(
        private readonly InjectionDetectorInterface $injectionDetector,
        private readonly PiiTransformerInterface $transformer,
        private readonly AegisConfigResolver $resolver,
    ) {
        parent::__construct();
    }

    /**
     * Run diagnostics for the requested Aegis stage.
     */
    public function handle(): int
    {
        $stage = $this->resolveStage();

        if ($stage === null) {
            return self::FAILURE;
        }

        $agentClass = $this->resolveAgentClass();

        if ($agentClass === false) {
            return self::FAILURE;
        }

        $prompt = trim((string) $this->argument('prompt'));
        $response = trim((string) $this->option('response'));

        if (in_array($stage, ['input', 'full'], true) && $prompt === '') {
            $this->components->error('A prompt is required when inspecting the input or full pipeline stages.');

            return self::FAILURE;
        }

        if ($stage === 'output' && $response === '') {
            $this->components->error('A response is required when inspecting the output stage.');

            return self::FAILURE;
        }

        $config = $this->resolveConfig($agentClass ?: null);
        $configSource = $this->resolveConfigSource($agentClass ?: null);

        $report = [
            'stage' => $stage,
            'agent' => $agentClass,
            'config_source' => $configSource,
            'config' => $this->serializeConfig($config),
        ];

        if ($stage !== 'output') {
            $report['input'] = $this->buildInputReport($prompt, $config);
        }

        if ($stage !== 'input') {
            $report['output'] = $response === ''
                ? [
                    'status' => 'skipped',
                    'message' => 'No response text provided. Output diagnostics were skipped.',
                ]
                : $this->buildOutputReport($response, $config);
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('  <fg=white;options=bold>Analysing stage:</> '.$stage);

        if ($prompt !== '') {
            $this->line('  <fg=white;options=bold>Prompt:</> '.$prompt);
        }

        if ($response !== '') {
            $this->line('  <fg=white;options=bold>Response:</> '.$response);
        }

        $this->newLine();

        if ((bool) $this->option('explain')) {
            $this->renderConfigExplanation($configSource, $agentClass ?: null, $report['config']);
        }

        if (isset($report['input'])) {
            /** @var array{injection: array<string, mixed>, pii: array<string, mixed>} $input */
            $input = $report['input'];
            $this->renderInjectionSection($input['injection']);
            $this->renderInputPiiSection($input['pii']);
        }

        if (isset($report['output'])) {
            /** @var array<string, mixed> $output */
            $output = $report['output'];
            $this->renderOutputPiiSection($output);
        }

        return self::SUCCESS;
    }

    /**
     * Resolve and validate the requested stage option.
     */
    private function resolveStage(): ?string
    {
        $stage = strtolower(trim((string) $this->option('stage')));

        if ($stage === '') {
            return 'full';
        }

        if (! in_array($stage, ['input', 'output', 'full'], true)) {
            $this->components->error(sprintf('Unsupported stage [%s]. Expected input, output, or full.', $stage));

            return null;
        }

        return $stage;
    }

    /**
     * Resolve and validate the requested agent class option.
     */
    private function resolveAgentClass(): string|false|null
    {
        $agentClass = trim((string) $this->option('agent'));

        if ($agentClass === '') {
            return null;
        }

        if (! class_exists($agentClass)) {
            $this->components->error(sprintf('Agent class [%s] does not exist.', $agentClass));

            return false;
        }

        return $agentClass;
    }

    /**
     * Resolve the Aegis config for the current command invocation.
     */
    private function resolveConfig(?string $agentClass): AegisConfig
    {
        return $this->resolver->resolve(new readonly class($agentClass)
        {
            public function __construct(
                private ?string $agentClass,
            ) {}

            public function agentClass(): ?string
            {
                return $this->agentClass;
            }
        });
    }

    /**
     * Determine whether the resolved config came from global config or an agent attribute.
     */
    private function resolveConfigSource(?string $agentClass): string
    {
        if ($agentClass === null) {
            return 'config';
        }

        return (new ReflectionClass($agentClass))->getAttributes(Aegis::class) !== []
            ? 'agent_attribute'
            : 'config';
    }

    /**
     * Build the diagnostic report for input-stage checks.
     *
     * @return array{injection: array<string, mixed>, pii: array<string, mixed>}
     */
    private function buildInputReport(string $prompt, AegisConfig $config): array
    {
        return [
            'injection' => $this->evaluateInjection($prompt, $config),
            'pii' => $this->evaluateInputPii($prompt, $config),
        ];
    }

    /**
     * Evaluate injection detection for the input prompt.
     *
     * @return array{status: string, score: float|null, matched_patterns: array<int, string>, threshold: float|null}
     */
    private function evaluateInjection(string $prompt, AegisConfig $config): array
    {
        if (! $config->blockInjections) {
            return [
                'status' => 'disabled',
                'score' => null,
                'matched_patterns' => [],
                'threshold' => null,
            ];
        }

        /** @var array{is_malicious: bool, score: float|int, matched_patterns: array<int, string>} $result */
        $result = $this->injectionDetector->evaluate($prompt);

        return [
            'status' => $result['is_malicious'] ? 'blocked' : 'clean',
            'score' => (float) $result['score'],
            'matched_patterns' => array_values($result['matched_patterns']),
            'threshold' => $config->injectionThreshold,
        ];
    }

    /**
     * Evaluate input-side PII handling for the prompt.
     *
     * @return array{status: string, token_count: int|null, transformed_text: string|null, rules: array<int, string>}
     */
    private function evaluateInputPii(string $prompt, AegisConfig $config): array
    {
        $rules = $this->serializeRules($config->piiRules);

        if (! $config->piiEnabled) {
            return [
                'status' => 'disabled',
                'token_count' => null,
                'transformed_text' => null,
                'rules' => $rules,
            ];
        }

        if ($config->piiRules === []) {
            return [
                'status' => 'no_rules',
                'token_count' => null,
                'transformed_text' => null,
                'rules' => [],
            ];
        }

        $result = $this->transformer->transform($prompt, $config->piiRules);

        return [
            'status' => $result->tokenCount > 0 ? 'pii_detected' : 'clean',
            'token_count' => $result->tokenCount,
            'transformed_text' => $result->tokenCount > 0 ? $result->text : null,
            'rules' => $rules,
        ];
    }

    /**
     * Build the diagnostic report for output-stage PII checks.
     *
     * @return array{status: string, token_count: int|null, transformed_text: string|null, rules: array<int, string>, detected_types: array<int, string>}
     */
    private function buildOutputReport(string $response, AegisConfig $config): array
    {
        if (! $config->blockOutputPii) {
            return [
                'status' => 'disabled',
                'token_count' => null,
                'transformed_text' => null,
                'rules' => $this->serializeRules($config->piiRules),
                'detected_types' => [],
            ];
        }

        if ($config->piiRules === []) {
            return [
                'status' => 'no_rules',
                'token_count' => null,
                'transformed_text' => null,
                'rules' => [],
                'detected_types' => [],
            ];
        }

        $scanRules = array_map(
            static fn (PiiRuleConfig $rule): PiiRuleConfig => new PiiRuleConfig(
                type: $rule->type,
                action: PiiAction::Tokenize,
            ),
            $config->piiRules,
        );

        $result = $this->transformer->transform($response, $scanRules);

        return [
            'status' => $result->tokenCount > 0 ? 'pii_detected' : 'clean',
            'token_count' => $result->tokenCount,
            'transformed_text' => $result->tokenCount > 0 ? $result->text : null,
            'rules' => $this->serializeRules($config->piiRules),
            'detected_types' => array_values(array_unique(array_map(
                static fn (PiiRuleConfig $rule): string => $rule->type,
                $config->piiRules,
            ))),
        ];
    }

    /**
     * Render the resolved-config explanation block.
     *
     * @param  array<string, mixed>  $config
     */
    private function renderConfigExplanation(string $configSource, ?string $agentClass, array $config): void
    {
        $this->line('  <fg=white;options=bold>Resolved configuration</>');
        $this->newLine();
        $this->components->twoColumnDetail('  Config source', $configSource === 'agent_attribute' ? 'agent attribute' : 'config');

        if ($agentClass !== null) {
            $this->components->twoColumnDetail('  Agent class', $agentClass);
        }

        $this->components->twoColumnDetail('  PII enabled', $config['pii_enabled'] ? 'yes' : 'no');
        $this->components->twoColumnDetail('  PII rules', $config['pii_rules'] === [] ? 'none' : implode(', ', $config['pii_rules']));
        $this->components->twoColumnDetail('  Block injections', $config['block_injections'] ? 'yes' : 'no');
        $this->components->twoColumnDetail('  Strict mode', $config['strict_mode'] ? 'yes' : 'no');
        $this->components->twoColumnDetail('  Injection threshold', $config['injection_threshold'] === null ? 'default' : (string) $config['injection_threshold']);
        $this->components->twoColumnDetail('  Block output PII', $config['block_output_pii'] ? 'yes' : 'no');
        $this->components->twoColumnDetail('  Require approval', $config['require_approval'] ? 'yes' : 'no');
        $this->newLine();
    }

    /**
     * Render the injection section of the console diagnostics.
     *
     * @param  array{status: string, score: float|null, matched_patterns: array<int, string>, threshold: float|null}  $report
     */
    private function renderInjectionSection(array $report): void
    {
        $statusLabel = match ($report['status']) {
            'blocked' => '<fg=red;options=bold>BLOCKED</>',
            'disabled' => '<fg=gray>disabled</>',
            default => '<fg=green;options=bold>CLEAN</>',
        };

        $this->components->twoColumnDetail('<fg=white>Injection detection</>', $statusLabel);

        if ($report['score'] !== null) {
            $this->components->twoColumnDetail('  Score', (string) $report['score']);
        }

        if ($report['threshold'] !== null) {
            $this->components->twoColumnDetail('  Threshold override', (string) $report['threshold']);
        }

        if ($report['matched_patterns'] !== []) {
            $this->components->twoColumnDetail('  Matched patterns', implode(', ', $report['matched_patterns']));
        }

        $this->newLine();
    }

    /**
     * Render the input-side PII diagnostics.
     *
     * @param  array{status: string, token_count: int|null, transformed_text: string|null, rules: array<int, string>}  $report
     */
    private function renderInputPiiSection(array $report): void
    {
        $statusLabel = match ($report['status']) {
            'disabled' => '<fg=gray>disabled</>',
            'no_rules' => '<fg=gray>no rules configured</>',
            'pii_detected' => '<fg=yellow;options=bold>PII DETECTED</>',
            default => '<fg=green;options=bold>CLEAN</>',
        };

        $this->components->twoColumnDetail('<fg=white>PII detection</>', $statusLabel);

        foreach ($report['rules'] as $rule) {
            $this->components->twoColumnDetail('  Rule', $rule);
        }

        if ($report['transformed_text'] !== null) {
            $this->components->twoColumnDetail('  Transformed text', $report['transformed_text']);
            $this->components->twoColumnDetail('  Tokens replaced', (string) $report['token_count']);
        }

        $this->newLine();
    }

    /**
     * Render the output-side PII diagnostics.
     *
     * @param  array<string, mixed>  $report
     */
    private function renderOutputPiiSection(array $report): void
    {
        if (($report['status'] ?? null) === 'skipped') {
            $this->components->twoColumnDetail('<fg=white>Output PII detection</>', '<fg=gray>skipped</>');
            $this->components->twoColumnDetail('  Reason', (string) $report['message']);
            $this->newLine();

            return;
        }

        $statusLabel = match ($report['status']) {
            'disabled' => '<fg=gray>disabled</>',
            'no_rules' => '<fg=gray>no rules configured</>',
            'pii_detected' => '<fg=yellow;options=bold>PII DETECTED</>',
            default => '<fg=green;options=bold>CLEAN</>',
        };

        $this->components->twoColumnDetail('<fg=white>Output PII detection</>', $statusLabel);

        /** @var array<int, string> $rules */
        $rules = $report['rules'];
        foreach ($rules as $rule) {
            $this->components->twoColumnDetail('  Rule', $rule);
        }

        /** @var array<int, string> $detectedTypes */
        $detectedTypes = $report['detected_types'];
        if ($detectedTypes !== [] && $report['status'] === 'pii_detected') {
            $this->components->twoColumnDetail('  Detected types', implode(', ', $detectedTypes));
        }

        if (($report['transformed_text'] ?? null) !== null) {
            $this->components->twoColumnDetail('  Transformed text', (string) $report['transformed_text']);
            $this->components->twoColumnDetail('  Tokens replaced', (string) $report['token_count']);
        }

        $this->newLine();
    }

    /**
     * Serialize the resolved config for explain and JSON output.
     *
     * @return array{pii_enabled: bool, pii_rules: array<int, string>, block_injections: bool, strict_mode: bool, injection_threshold: float|null, block_output_pii: bool, require_approval: bool}
     */
    private function serializeConfig(AegisConfig $config): array
    {
        return [
            'pii_enabled' => $config->piiEnabled,
            'pii_rules' => $this->serializeRules($config->piiRules),
            'block_injections' => $config->blockInjections,
            'strict_mode' => $config->strictMode,
            'injection_threshold' => $config->injectionThreshold,
            'block_output_pii' => $config->blockOutputPii,
            'require_approval' => $config->requireApproval,
        ];
    }

    /**
     * Serialize PII rules into a readable DSL-like format.
     *
     * @param  array<int, PiiRuleConfig>  $rules
     * @return array<int, string>
     */
    private function serializeRules(array $rules): array
    {
        return array_values(array_map(fn (PiiRuleConfig $rule): string => match ($rule->action) {
            PiiAction::Tokenize => $rule->type.':tokenize',
            PiiAction::Replace => $rule->replacement === ''
                ? $rule->type.':replace'
                : $rule->type.':replace,'.$rule->replacement,
            PiiAction::Mask => $rule->maskStart === 0 && $rule->maskEnd === 0
                ? $rule->type.':mask'
                : $rule->type.':mask,'.$rule->maskStart.','.$rule->maskEnd,
        }, $rules));
    }
}
