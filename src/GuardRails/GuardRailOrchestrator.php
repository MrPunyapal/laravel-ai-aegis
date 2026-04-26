<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\GuardRails;

use MrPunyapal\LaravelAiAegis\Contracts\GuardRailInterface;
use MrPunyapal\LaravelAiAegis\Contracts\GuardRailOrchestratorInterface;
use MrPunyapal\LaravelAiAegis\Data\AegisConfig;
use MrPunyapal\LaravelAiAegis\Enums\GuardRailStage;
use MrPunyapal\LaravelAiAegis\Exceptions\AegisSecurityException;

final class GuardRailOrchestrator implements GuardRailOrchestratorInterface
{
    /**
     * @var array<string, array<int, GuardRailInterface>>
     */
    private array $rails = [];

    public function register(GuardRailInterface $rail): void
    {
        $this->rails[$rail->stage()->value][] = $rail;
    }

    public function runInput(string $content, AegisConfig $config, mixed $context = null): void
    {
        $this->run(GuardRailStage::Input, $content, $config);
    }

    public function runOutput(string $content, AegisConfig $config, mixed $context = null): void
    {
        $this->run(GuardRailStage::Output, $content, $config);
    }

    public function runTool(string $content, AegisConfig $config, mixed $context = null): void
    {
        $this->run(GuardRailStage::Tool, $content, $config);
    }

    public function runSchema(string $content, AegisConfig $config, mixed $context = null): void
    {
        $this->run(GuardRailStage::Schema, $content, $config);
    }

    public function runApproval(string $content, AegisConfig $config, mixed $context = null): void
    {
        $this->run(GuardRailStage::Approval, $content, $config);
    }

    private function run(GuardRailStage $stage, string $content, AegisConfig $config): void
    {
        foreach ($this->rails[$stage->value] ?? [] as $rail) {
            $result = $rail->check($content, $config);

            if (! $result->passed) {
                throw AegisSecurityException::guardRailViolation(
                    stage: $stage->value,
                    reason: $result->reason ?? 'Guard rail blocked the request.',
                );
            }
        }
    }
}
