<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Pseudonymization;

use Illuminate\Contracts\Cache\Repository;
use MrPunyapal\LaravelAiAegis\Contracts\PiiTransformerInterface;
use MrPunyapal\LaravelAiAegis\Contracts\PiiTypeRegistryInterface;
use MrPunyapal\LaravelAiAegis\Data\PiiRuleConfig;
use MrPunyapal\LaravelAiAegis\Data\TransformResult;
use MrPunyapal\LaravelAiAegis\Enums\PiiAction;

final class PseudonymizationEngine implements PiiTransformerInterface
{
    public function __construct(
        private readonly Repository $cache,
        private readonly PiiTypeRegistryInterface $registry,
        private readonly string $prefix = 'aegis_pii',
        private readonly int $ttl = 3600,
    ) {}

    /**
     * @param  array<int, PiiRuleConfig>  $rules
     */
    public function transform(string $text, array $rules): TransformResult
    {
        $sessionId = bin2hex(random_bytes(16));
        /** @var array<string, string> $tokenMap */
        $tokenMap = [];
        $tokenCount = 0;

        foreach ($rules as $rule) {
            if (! $this->registry->has($rule->type)) {
                continue;
            }

            $pattern = $this->registry->get($rule->type)->pattern();

            $text = (string) preg_replace_callback(
                $pattern,
                function (array $matches) use ($rule, &$tokenMap, &$tokenCount): string {
                    $tokenCount++;

                    return match ($rule->action) {
                        PiiAction::Tokenize => $this->applyTokenize($matches[0], $rule, $tokenMap),
                        PiiAction::Replace => $this->applyReplace($rule),
                        PiiAction::Mask => $this->applyMask($matches[0], $rule),
                    };
                },
                $text,
            );
        }

        if ($tokenMap !== []) {
            $this->cache->put(
                "{$this->prefix}:{$sessionId}",
                $tokenMap,
                $this->ttl,
            );
        }

        return new TransformResult(
            text: $text,
            sessionId: $sessionId,
            tokenCount: $tokenCount,
            tokenMap: $tokenMap,
        );
    }

    public function restore(string $text, string $sessionId): string
    {
        /** @var array<string, string>|null $tokenMap */
        $tokenMap = $this->cache->get("{$this->prefix}:{$sessionId}");

        if ($tokenMap === null) {
            return $text;
        }

        return str_replace(
            array_keys($tokenMap),
            array_values($tokenMap),
            $text,
        );
    }

    /**
     * @param  array<string, string>  $tokenMap
     */
    private function applyTokenize(string $value, PiiRuleConfig $rule, array &$tokenMap): string
    {
        $token = $this->generateToken($rule->type);
        $tokenMap[$token] = $value;

        return $token;
    }

    private function applyReplace(PiiRuleConfig $rule): string
    {
        return $rule->replacement !== ''
            ? $rule->replacement
            : '[REDACTED:'.strtoupper($rule->type).']';
    }

    private function applyMask(string $value, PiiRuleConfig $rule): string
    {
        $length = mb_strlen($value);
        $keep = $rule->maskStart + $rule->maskEnd;

        if ($keep === 0 || $keep >= $length) {
            return str_repeat($rule->maskChar(), $length);
        }

        $start = mb_substr($value, 0, $rule->maskStart);
        $end = mb_substr($value, -$rule->maskEnd);
        $masked = str_repeat($rule->maskChar(), $length - $keep);

        return $start.$masked.$end;
    }

    private function generateToken(string $type): string
    {
        $hash = strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

        return '{{AEGIS_'.strtoupper($type).'_'.$hash.'}}';
    }
}
