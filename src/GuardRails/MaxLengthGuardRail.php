<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\GuardRails;

use MrPunyapal\LaravelAiAegis\Contracts\GuardRailInterface;
use MrPunyapal\LaravelAiAegis\Data\AegisConfig;
use MrPunyapal\LaravelAiAegis\Data\GuardRailResult;
use MrPunyapal\LaravelAiAegis\Enums\GuardRailStage;

final readonly class MaxLengthGuardRail implements GuardRailInterface
{
    public function stage(): GuardRailStage
    {
        return GuardRailStage::Input;
    }

    public function check(string $content, mixed $context): GuardRailResult
    {
        if (! $context instanceof AegisConfig || $context->maxInputLength === null) {
            return GuardRailResult::pass();
        }

        $length = mb_strlen($content);

        if ($length > $context->maxInputLength) {
            return GuardRailResult::fail(
                reason: "Input length {$length} exceeds maximum {$context->maxInputLength}.",
                stage: GuardRailStage::Input->value,
            );
        }

        return GuardRailResult::pass();
    }
}
