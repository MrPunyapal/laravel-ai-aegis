<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Data;

use MrPunyapal\LaravelAiAegis\Contracts\ApprovalHandlerInterface;

final readonly class AegisConfig
{
    /**
     * @param  array<int, PiiRuleConfig>  $piiRules  Parsed PII rules to apply.
     * @param  array<int, string>  $inputBlockedPhrases  Additional phrases that trigger input blocking.
     * @param  array<int, string>  $outputBlockedPhrases  Phrases that trigger output blocking.
     * @param  array<int, string>  $allowedTools  Tool names permitted in this context (empty = all allowed).
     * @param  array<int, string>  $blockedTools  Tool names that are always denied.
     */
    public function __construct(
        public bool $piiEnabled = true,
        public array $piiRules = [],
        public bool $blockInjections = true,
        public bool $strictMode = false,
        public ?float $injectionThreshold = null,
        public array $inputBlockedPhrases = [],
        public ?int $maxInputLength = null,
        public bool $blockOutputPii = true,
        public array $outputBlockedPhrases = [],
        public array $allowedTools = [],
        public array $blockedTools = [],
        public bool $validateSchema = false,
        public bool $requireApproval = false,
        public ?ApprovalHandlerInterface $approvalHandler = null,
    ) {}
}
