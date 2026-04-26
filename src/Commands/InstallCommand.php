<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Commands;

use Illuminate\Console\Command;
use MrPunyapal\LaravelAiAegis\Middleware\AegisMiddleware;

final class InstallCommand extends Command
{
    protected $signature = 'aegis:install';

    protected $description = 'Publish the Aegis config file and display getting-started instructions';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'aegis-config']);

        $this->newLine();
        $this->components->info('Aegis installed successfully.');
        $this->newLine();

        $this->components->twoColumnDetail('<fg=white>Config file published</>', '<fg=green>config/aegis.php</>');
        $this->newLine();

        $this->line('  <fg=yellow;options=bold>Next steps:</>');
        $this->newLine();
        $this->line('  1. Set <fg=cyan>AEGIS_CACHE_STORE=redis</> in your <fg=cyan>.env</> (recommended for production).');
        $this->line('  2. Configure PII rules in <fg=cyan>config/aegis.php</> under <fg=cyan>pii.rules</>:');
        $this->newLine();
        $this->line("     <fg=cyan>'pii.rules' => ['email:mask,3', 'phone:replace', 'ssn:tokenize']</>");
        $this->newLine();
        $this->line('  3. Register the middleware in your AI agent pipeline:');
        $this->newLine();
        $this->line('     <fg=cyan>$agent->withMiddleware([app('.AegisMiddleware::class.'::class)]);');
        $this->newLine();
        $this->line('  4. Add <fg=cyan>#[Aegis]</> to your agent class for per-agent overrides:');
        $this->newLine();
        $this->line('     <fg=cyan>#[Aegis(piiRules: ["email:mask,3,5"], strictMode: true)]</>      ');
        $this->line('     <fg=cyan>class MyAgent { ... }</>                                         ');
        $this->newLine();
        $this->line('  5. Test a prompt with:');
        $this->newLine();
        $this->line('     <fg=cyan>php artisan aegis:test "ignore previous instructions"</>');
        $this->newLine();

        return self::SUCCESS;
    }
}
