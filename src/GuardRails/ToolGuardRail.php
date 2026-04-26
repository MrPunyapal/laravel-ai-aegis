<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\GuardRails;

use MrPunyapal\LaravelAiAegis\Contracts\GuardRailInterface;
use MrPunyapal\LaravelAiAegis\Data\AegisConfig;
use MrPunyapal\LaravelAiAegis\Data\GuardRailResult;
use MrPunyapal\LaravelAiAegis\Enums\GuardRailStage;

/**
 * Guards tool invocations against allow/deny lists.
 *
 * The $content parameter receives the tool name being invoked.
 */
final readonly class ToolGuardRail implements GuardRailInterface
{
    public function stage(): GuardRailStage
    {
        return GuardRailStage::Tool;
    }

    public function check(string $content, mixed $context): GuardRailResult
    {
        if (! $context instanceof AegisConfig) {
            return GuardRailResult::pass();
        }

        $tool = trim($content);

        if ($context->blockedTools !== [] && in_array($tool, $context->blockedTools, strict: true)) {
            return GuardRailResult::fail(
                reason: "Tool \"{$tool}\" is explicitly blocked.",
                stage: GuardRailStage::Tool->value,
            );
        }

        if ($context->allowedTools !== [] && ! in_array($tool, $context->allowedTools, strict: true)) {
            return GuardRailResult::fail(
                reason: "Tool \"{$tool}\" is not in the allowed-tools list.",
                stage: GuardRailStage::Tool->value,
            );
        }

        return GuardRailResult::pass();
    }
}
