<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\GuardRails;

use MrPunyapal\LaravelAiAegis\Contracts\GuardRailInterface;
use MrPunyapal\LaravelAiAegis\Contracts\PiiTransformerInterface;
use MrPunyapal\LaravelAiAegis\Data\AegisConfig;
use MrPunyapal\LaravelAiAegis\Data\GuardRailResult;
use MrPunyapal\LaravelAiAegis\Data\PiiRuleConfig;
use MrPunyapal\LaravelAiAegis\Enums\GuardRailStage;
use MrPunyapal\LaravelAiAegis\Enums\PiiAction;

final readonly class OutputPiiGuardRail implements GuardRailInterface
{
    public function __construct(
        private PiiTransformerInterface $transformer,
    ) {}

    public function stage(): GuardRailStage
    {
        return GuardRailStage::Output;
    }

    public function check(string $content, mixed $context): GuardRailResult
    {
        if (! $context instanceof AegisConfig || ! $context->blockOutputPii || $context->piiRules === []) {
            return GuardRailResult::pass();
        }

        $scanRules = array_map(
            fn (PiiRuleConfig $rule): PiiRuleConfig => new PiiRuleConfig(
                type: $rule->type,
                action: PiiAction::Tokenize,
            ),
            $context->piiRules,
        );

        $result = $this->transformer->transform($content, $scanRules);

        if ($result->tokenCount > 0) {
            $types = implode(', ', array_unique(
                array_map(
                    fn (PiiRuleConfig $r): string => $r->type,
                    $context->piiRules,
                ),
            ));

            return GuardRailResult::fail(
                reason: "PII detected in LLM output ({$types}).",
                stage: GuardRailStage::Output->value,
            );
        }

        return GuardRailResult::pass();
    }
}
