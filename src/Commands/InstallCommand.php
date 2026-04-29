<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use MrPunyapal\LaravelAiAegis\Middleware\AegisMiddleware;

#[Signature('aegis:install {--force : Republish the config even if it already exists} {--cache-store= : Cache store to recommend in the next steps} {--pii-rules=* : Starter PII rules to recommend in the next steps} {--with-pulse : Include Laravel Pulse guidance in the next steps}')]
#[Description('Publish the Aegis config file and display tailored getting-started instructions')]
final class InstallCommand extends Command
{
    private const string DEFAULT_CACHE_STORE = 'redis';

    /**
     * Starter PII rules offered during the install flow.
     *
     * @var array<int, string>
     */
    private const array AVAILABLE_STARTER_RULES = [
        'email:mask,3',
        'phone:replace',
        'ssn:tokenize',
        'credit_card:tokenize',
        'jwt:replace',
    ];

    /**
     * Default starter PII rules recommended for new installs.
     *
     * @var array<int, string>
     */
    private const array DEFAULT_STARTER_RULES = [
        'email:mask,3',
        'phone:replace',
        'ssn:tokenize',
    ];

    /**
     * Run the install flow and print tailored next steps.
     */
    public function handle(): int
    {
        $cacheStore = $this->resolveCacheStore();
        $starterRules = $this->resolveStarterRules();
        $includePulseGuidance = $this->shouldIncludePulseGuidance();

        $this->call('vendor:publish', $this->publishParameters());

        $this->newLine();
        $this->components->info('Aegis installed successfully.');
        $this->newLine();

        $this->components->twoColumnDetail('<fg=white>Config file published</>', '<fg=green>config/aegis.php</>');
        $this->components->twoColumnDetail('<fg=white>Recommended cache store</>', '<fg=green>'.$cacheStore.'</>');
        $this->components->twoColumnDetail('<fg=white>Starter pii.rules</>', '<fg=green>'.implode(', ', $starterRules).'</>');

        if ($includePulseGuidance) {
            $this->components->twoColumnDetail('<fg=white>Pulse guidance</>', '<fg=green>included</>');
        }

        $this->newLine();

        $this->renderNextSteps($cacheStore, $starterRules, $includePulseGuidance);

        return self::SUCCESS;
    }

    /**
     * Build the arguments passed to the vendor:publish command.
     *
     * @return array<string, bool|string>
     */
    private function publishParameters(): array
    {
        $parameters = ['--tag' => 'aegis-config'];

        if ((bool) $this->option('force')) {
            $parameters['--force'] = true;
        }

        return $parameters;
    }

    /**
     * Resolve the cache store recommendation for the next steps output.
     */
    private function resolveCacheStore(): string
    {
        $cacheStore = trim((string) $this->option('cache-store'));

        if ($cacheStore !== '') {
            return $cacheStore;
        }

        if (! $this->input->isInteractive()) {
            return self::DEFAULT_CACHE_STORE;
        }

        return $this->choice(
            question: 'Which cache store should Aegis recommend for your environment?',
            choices: ['redis', 'database', 'file', 'array'],
            default: self::DEFAULT_CACHE_STORE,
        );
    }

    /**
     * Resolve the starter PII rules shown in the next steps output.
     *
     * @return array<int, string>
     */
    private function resolveStarterRules(): array
    {
        /** @var array<int, string> $starterRules */
        $starterRules = array_values(array_filter(
            array_map(trim(...), (array) $this->option('pii-rules')),
            static fn (string $rule): bool => $rule !== '',
        ));

        if ($starterRules !== []) {
            return $starterRules;
        }

        if (! $this->input->isInteractive()) {
            return self::DEFAULT_STARTER_RULES;
        }

        /** @var array<int, string> $selectedRules */
        $selectedRules = $this->choice(
            question: 'Which starter PII rules should be shown in the next steps?',
            choices: self::AVAILABLE_STARTER_RULES,
            attempts: null,
            multiple: true,
        );

        return $selectedRules === [] ? self::DEFAULT_STARTER_RULES : array_values($selectedRules);
    }

    /**
     * Determine whether Laravel Pulse guidance should be shown in the next steps output.
     */
    private function shouldIncludePulseGuidance(): bool
    {
        if ((bool) $this->option('with-pulse')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            return false;
        }

        return $this->confirm('Include Laravel Pulse setup guidance in the next steps?', true);
    }

    /**
     * Print tailored next-step guidance after installation.
     *
     * @param  array<int, string>  $starterRules
     */
    private function renderNextSteps(string $cacheStore, array $starterRules, bool $includePulseGuidance): void
    {
        $this->line('  <fg=yellow;options=bold>Next steps:</>');
        $this->newLine();
        $this->line(sprintf(
            '  1. Set <fg=cyan>AEGIS_CACHE_STORE=%s</> in your <fg=cyan>.env</> (recommended for production).',
            $cacheStore,
        ));
        $this->line('  2. Configure PII rules in <fg=cyan>config/aegis.php</> under <fg=cyan>pii.rules</>:');
        $this->newLine();
        $this->line('     <fg=cyan>'.$this->formatStarterRulesSnippet($starterRules).'</>');
        $this->newLine();
        $this->line('  3. Register the middleware in your AI agent pipeline:');
        $this->newLine();
        $this->line(sprintf(
            '     <fg=cyan>$agent->withMiddleware([app(%s::class)]);</>',
            AegisMiddleware::class,
        ));
        $this->newLine();
        $this->line('  4. Add <fg=cyan>#[Aegis]</> to your agent class for per-agent overrides:');
        $this->newLine();
        $this->line('     <fg=cyan>#[Aegis(piiRules: ["email:mask,3,5"], strictMode: true)]</>');
        $this->line('     <fg=cyan>class MyAgent { ... }</>');
        $this->newLine();

        if ($includePulseGuidance) {
            $this->line('  5. If you use Laravel Pulse, add the Aegis card to your dashboard:');
            $this->newLine();
            $this->line('     <fg=cyan><livewire:aegis-card cols="3" /></>');
            $this->newLine();
            $this->line('  6. Test a prompt with:');
        } else {
            $this->line('  5. Test a prompt with:');
        }

        $this->newLine();
        $this->line('     <fg=cyan>php artisan aegis:test "ignore previous instructions"</>');
        $this->newLine();
    }

    /**
     * Format the starter PII rules as a config snippet.
     *
     * @param  array<int, string>  $starterRules
     */
    private function formatStarterRulesSnippet(array $starterRules): string
    {
        return sprintf(
            "'pii.rules' => [%s]",
            implode(', ', array_map(static fn (string $rule): string => "'{$rule}'", $starterRules)),
        );
    }
}
