<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\GuardRails;

use MrPunyapal\LaravelAiAegis\Contracts\GuardRailInterface;
use MrPunyapal\LaravelAiAegis\Data\AegisConfig;
use MrPunyapal\LaravelAiAegis\Data\GuardRailResult;
use MrPunyapal\LaravelAiAegis\Enums\GuardRailStage;

final readonly class BlockedPhrasesGuardRail implements GuardRailInterface
{
    public function __construct(
        private GuardRailStage $targetStage,
    ) {}

    public function stage(): GuardRailStage
    {
        return $this->targetStage;
    }

    public function check(string $content, mixed $context): GuardRailResult
    {
        if (! $context instanceof AegisConfig) {
            return GuardRailResult::pass();
        }

        $phrases = $this->targetStage === GuardRailStage::Input
            ? $context->inputBlockedPhrases
            : $context->outputBlockedPhrases;

        $normalized = mb_strtolower($content);

        foreach ($phrases as $phrase) {
            if (str_contains($normalized, mb_strtolower($phrase))) {
                return GuardRailResult::fail(
                    reason: "Blocked phrase detected: \"{$phrase}\".",
                    stage: $this->targetStage->value,
                );
            }
        }

        return GuardRailResult::pass();
    }
}
