<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\GuardRails;

use MrPunyapal\LaravelAiAegis\Contracts\GuardRailInterface;
use MrPunyapal\LaravelAiAegis\Contracts\InjectionDetectorInterface;
use MrPunyapal\LaravelAiAegis\Data\AegisConfig;
use MrPunyapal\LaravelAiAegis\Data\GuardRailResult;
use MrPunyapal\LaravelAiAegis\Enums\GuardRailStage;

final readonly class InjectionGuardRail implements GuardRailInterface
{
    public function __construct(
        private InjectionDetectorInterface $detector,
        private float $defaultThreshold = 0.7,
        private float $strictThreshold = 0.3,
    ) {}

    public function stage(): GuardRailStage
    {
        return GuardRailStage::Input;
    }

    public function check(string $content, mixed $context): GuardRailResult
    {
        if (! $context instanceof AegisConfig || ! $context->blockInjections) {
            return GuardRailResult::pass();
        }

        $result = $this->detector->evaluate($content);

        $threshold = $context->injectionThreshold
            ?? ($context->strictMode ? $this->strictThreshold : $this->defaultThreshold);

        if ($result['score'] >= $threshold) {
            return GuardRailResult::fail(
                reason: "Prompt injection detected (confidence: {$result['score']}).",
                stage: GuardRailStage::Input->value,
            );
        }

        return GuardRailResult::pass();
    }
}
