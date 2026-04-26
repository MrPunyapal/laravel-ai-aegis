<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Pii;

use InvalidArgumentException;
use MrPunyapal\LaravelAiAegis\Contracts\PiiTypeRegistryInterface;
use MrPunyapal\LaravelAiAegis\Data\PiiRuleConfig;
use MrPunyapal\LaravelAiAegis\Enums\PiiAction;

/**
 * Parses PII rule definitions (string DSL or structured arrays) into PiiRuleConfig DTOs.
 *
 * Supported DSL forms:
 *   "email"                  → tokenize (default)
 *   "email:tokenize"         → tokenize
 *   "email:replace"          → replace with [REDACTED:email]
 *   "email:replace,MY TEXT"  → replace with static text
 *   "email:mask"             → full mask  (no chars preserved)
 *   "email:mask,3"           → keep 3 at start, 0 at end
 *   "email:mask,3,5"         → keep 3 at start, 5 at end
 *
 * Supported structured form:
 *   ['type' => 'email', 'action' => 'mask', 'mask_start' => 3, 'mask_end' => 5]
 */
final readonly class PiiRuleParser
{
    public function __construct(
        private PiiTypeRegistryInterface $registry,
    ) {}

    /**
     * @param  array<int, string|array<string, mixed>>  $rules
     * @return array<int, PiiRuleConfig>
     */
    public function parseAll(array $rules): array
    {
        return array_values(array_map(fn (string|array $rule): PiiRuleConfig => $this->parse($rule), $rules));
    }

    /**
     * @param  string|array<string, mixed>  $rule
     */
    public function parse(string|array $rule): PiiRuleConfig
    {
        return is_string($rule)
            ? $this->parseString($rule)
            : $this->parseArray($rule);
    }

    private function parseString(string $rule): PiiRuleConfig
    {
        $parts = explode(':', $rule, 2);
        $type = trim($parts[0]);

        if (! $this->registry->has($type)) {
            throw new InvalidArgumentException("Unknown PII type: \"{$type}\". Register it via the aegis.pii.custom_detectors config or PiiTypeRegistry::register().");
        }

        if (! isset($parts[1])) {
            return new PiiRuleConfig(type: $type, action: PiiAction::Tokenize);
        }

        $actionParts = explode(',', $parts[1]);
        $action = PiiAction::from(strtolower(trim($actionParts[0])));

        return match ($action) {
            PiiAction::Tokenize => new PiiRuleConfig(type: $type, action: $action),
            PiiAction::Replace => new PiiRuleConfig(
                type: $type,
                action: $action,
                replacement: isset($actionParts[1]) ? trim($actionParts[1]) : '',
            ),
            PiiAction::Mask => new PiiRuleConfig(
                type: $type,
                action: $action,
                maskStart: isset($actionParts[1]) ? max(0, (int) trim($actionParts[1])) : 0,
                maskEnd: isset($actionParts[2]) ? max(0, (int) trim($actionParts[2])) : 0,
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function parseArray(array $rule): PiiRuleConfig
    {
        if (! isset($rule['type']) || ! is_string($rule['type'])) {
            throw new InvalidArgumentException('PII rule array must contain a "type" string key.');
        }

        $type = $rule['type'];

        if (! $this->registry->has($type)) {
            throw new InvalidArgumentException("Unknown PII type: \"{$type}\". Register it via the aegis.pii.custom_detectors config or PiiTypeRegistry::register().");
        }

        $action = isset($rule['action']) && is_string($rule['action'])
            ? PiiAction::from(strtolower($rule['action']))
            : PiiAction::Tokenize;

        return new PiiRuleConfig(
            type: $type,
            action: $action,
            maskStart: isset($rule['mask_start']) && is_int($rule['mask_start']) ? max(0, $rule['mask_start']) : 0,
            maskEnd: isset($rule['mask_end']) && is_int($rule['mask_end']) ? max(0, $rule['mask_end']) : 0,
            replacement: isset($rule['replacement']) && is_string($rule['replacement']) ? $rule['replacement'] : '',
        );
    }
}
