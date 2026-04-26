<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Aegis
{
    /**
     * @param  array<int, string|array<string, mixed>>  $piiRules
     *      PII rules for this agent. Accepts string DSL or structured arrays.
     *      Examples:
     *        'email'                  → tokenize (default action)
     *        'email:replace'          → replace with [REDACTED:EMAIL]
     *        'email:mask,3,5'         → keep 3 chars at start and 5 at end
     *        ['type'=>'email', 'action'=>'mask', 'mask_start'=>3, 'mask_end'=>5]
     *
     * @param  array<int, string>  $inputBlockedPhrases  Phrases that block the input prompt.
     * @param  array<int, string>  $outputBlockedPhrases  Phrases that block the LLM response.
     * @param  array<int, string>  $allowedTools  Only these tools are permitted (empty = all allowed).
     * @param  array<int, string>  $blockedTools  These tools are always denied.
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
        public bool $requireApproval = false,
        public ?string $approvalHandler = null,
    ) {}
}

