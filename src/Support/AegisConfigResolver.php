<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Support;

use MrPunyapal\LaravelAiAegis\Attributes\Aegis;
use MrPunyapal\LaravelAiAegis\Contracts\ApprovalHandlerInterface;
use MrPunyapal\LaravelAiAegis\Data\AegisConfig;
use MrPunyapal\LaravelAiAegis\Pii\PiiRuleParser;
use ReflectionClass;

final readonly class AegisConfigResolver
{
    public function __construct(
        private PiiRuleParser $parser,
    ) {}

    public function resolve(object $prompt): AegisConfig
    {
        $attribute = $this->resolveAttribute($prompt);

        if ($attribute instanceof Aegis) {
            return $this->fromAttribute($attribute);
        }

        return $this->fromConfig();
    }

    private function fromAttribute(Aegis $attribute): AegisConfig
    {
        /** @var array<int, string|array<string, mixed>> $rawRules */
        $rawRules = $attribute->piiRules !== []
            ? $attribute->piiRules
            : (array) config('aegis.pii.rules', []);

        $approvalHandler = null;

        if ($attribute->approvalHandler !== null && class_exists($attribute->approvalHandler)) {
            $instance = app($attribute->approvalHandler);
            if ($instance instanceof ApprovalHandlerInterface) {
                $approvalHandler = $instance;
            }
        }

        return new AegisConfig(
            piiEnabled: $attribute->piiEnabled,
            piiRules: $this->parser->parseAll($rawRules),
            blockInjections: $attribute->blockInjections,
            strictMode: $attribute->strictMode,
            injectionThreshold: $attribute->injectionThreshold,
            inputBlockedPhrases: $attribute->inputBlockedPhrases !== []
                ? $attribute->inputBlockedPhrases
                : (array) config('aegis.guard_rails.input.blocked_phrases', []),
            maxInputLength: $attribute->maxInputLength
                ?? (config('aegis.guard_rails.input.max_length') !== null
                    ? (int) config('aegis.guard_rails.input.max_length')
                    : null),
            blockOutputPii: $attribute->blockOutputPii,
            outputBlockedPhrases: $attribute->outputBlockedPhrases !== []
                ? $attribute->outputBlockedPhrases
                : (array) config('aegis.guard_rails.output.blocked_phrases', []),
            allowedTools: $attribute->allowedTools !== []
                ? $attribute->allowedTools
                : (array) config('aegis.guard_rails.tool.allowed', []),
            blockedTools: $attribute->blockedTools !== []
                ? $attribute->blockedTools
                : (array) config('aegis.guard_rails.tool.blocked', []),
            requireApproval: $attribute->requireApproval,
            approvalHandler: $approvalHandler,
        );
    }

    private function fromConfig(): AegisConfig
    {
        /** @var array<int, string|array<string, mixed>> $rawRules */
        $rawRules = (array) config('aegis.pii.rules', []);

        $approvalHandlerClass = config('aegis.guard_rails.approval.handler');
        $approvalHandler = null;

        if (is_string($approvalHandlerClass) && class_exists($approvalHandlerClass)) {
            $instance = app($approvalHandlerClass);
            if ($instance instanceof ApprovalHandlerInterface) {
                $approvalHandler = $instance;
            }
        }

        return new AegisConfig(
            piiEnabled: (bool) config('aegis.pii.enabled', true),
            piiRules: $this->parser->parseAll($rawRules),
            blockInjections: (bool) config('aegis.guard_rails.input.injection.enabled', true),
            strictMode: (bool) config('aegis.strict_mode', false),
            injectionThreshold: config('aegis.guard_rails.input.injection.threshold') !== null
                ? (float) config('aegis.guard_rails.input.injection.threshold')
                : null,
            inputBlockedPhrases: (array) config('aegis.guard_rails.input.blocked_phrases', []),
            maxInputLength: config('aegis.guard_rails.input.max_length') !== null
                ? (int) config('aegis.guard_rails.input.max_length')
                : null,
            blockOutputPii: (bool) config('aegis.guard_rails.output.pii_leakage.enabled', true),
            outputBlockedPhrases: (array) config('aegis.guard_rails.output.blocked_phrases', []),
            allowedTools: (array) config('aegis.guard_rails.tool.allowed', []),
            blockedTools: (array) config('aegis.guard_rails.tool.blocked', []),
            requireApproval: (bool) config('aegis.guard_rails.approval.enabled', false),
            approvalHandler: $approvalHandler,
        );
    }

    private function resolveAttribute(object $prompt): ?Aegis
    {
        $agentClass = method_exists($prompt, 'agentClass')
            ? $prompt->agentClass()
            : null;

        if ($agentClass === null || ! is_string($agentClass) || ! class_exists($agentClass)) {
            return null;
        }

        $attributes = (new ReflectionClass($agentClass))->getAttributes(Aegis::class);

        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance();
    }
}
