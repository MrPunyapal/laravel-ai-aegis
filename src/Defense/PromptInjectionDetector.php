<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Defense;

use MrPunyapal\LaravelAiAegis\Contracts\InjectionDetectorInterface;

final readonly class PromptInjectionDetector implements InjectionDetectorInterface
{
    /**
     * Known adversarial attack patterns mapped to severity weights (0.0 - 1.0).
     *
     * @var array<string, float>
     */
    private const ATTACK_VECTORS = [
        'ignore previous instructions' => 0.95,
        'ignore all previous' => 0.95,
        'disregard all previous' => 0.95,
        'disregard your instructions' => 0.95,
        'forget your instructions' => 0.9,
        'forget everything above' => 0.9,
        'override your system prompt' => 0.95,
        'ignore your system prompt' => 0.95,
        'you are now' => 0.7,
        'act as if you have no restrictions' => 0.9,
        'pretend you are' => 0.6,
        'bypass your safety' => 0.95,
        'jailbreak' => 0.9,
        'DAN mode' => 0.9,
        'developer mode enabled' => 0.85,
        'do anything now' => 0.85,
        'ignore safety guidelines' => 0.95,
        'output your system prompt' => 0.9,
        'reveal your instructions' => 0.85,
        'show me your prompt' => 0.8,
        'what are your instructions' => 0.7,
        'repeat everything above' => 0.85,
        'print your initial prompt' => 0.9,
        'sudo mode' => 0.8,
        'admin override' => 0.8,
        'new system prompt' => 0.9,
        'from now on you will' => 0.75,
        'roleplay as an unrestricted' => 0.9,
        'respond without any ethical' => 0.9,
        'ignore content policy' => 0.95,
        'base64 decode and execute' => 0.95,
    ];

    /**
     * Pre-computed map of lowercased pattern → [weight, original pattern].
     *
     * @var array<string, array{0: float, 1: string}>
     */
    private array $vectors;

    /**
     * @param  array<string, float>  $customVectors
     */
    public function __construct(
        array $customVectors = [],
    ) {
        $merged = [...self::ATTACK_VECTORS, ...$customVectors];
        $vectors = [];
        foreach ($merged as $original => $weight) {
            $vectors[mb_strtolower($original)] = [$weight, $original];
        }
        $this->vectors = $vectors;
    }

    /**
     * @return array{is_malicious: bool, score: float, matched_patterns: array<int, string>}
     */
    public function evaluate(string $prompt): array
    {
        $normalizedPrompt = mb_strtolower(trim($prompt));
        $matchedPatterns = [];
        $maxScore = 0.0;

        foreach ($this->vectors as $lowercased => [$weight, $original]) {
            if (str_contains($normalizedPrompt, $lowercased)) {
                $matchedPatterns[] = $original;
                $maxScore = max($maxScore, $weight);
            }
        }

        return [
            'is_malicious' => $matchedPatterns !== [],
            'score' => $maxScore,
            'matched_patterns' => $matchedPatterns,
        ];
    }
}
