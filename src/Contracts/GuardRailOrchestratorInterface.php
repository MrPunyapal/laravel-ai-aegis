<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Contracts;

use MrPunyapal\LaravelAiAegis\Data\AegisConfig;

interface GuardRailOrchestratorInterface
{
    public function runInput(string $content, AegisConfig $config, mixed $context = null): void;

    public function runOutput(string $content, AegisConfig $config, mixed $context = null): void;

    public function runTool(string $tool, AegisConfig $config, mixed $context = null): void;

    public function runSchema(string $content, AegisConfig $config, mixed $context = null): void;

    public function runApproval(string $content, AegisConfig $config, mixed $context = null): void;
}
