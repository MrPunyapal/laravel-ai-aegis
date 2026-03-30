<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Pseudonymization;

use Illuminate\Contracts\Cache\Repository;
use MrPunyapal\LaravelAiAegis\Contracts\PiiDetectorInterface;

final readonly class PseudonymizationEngine implements PiiDetectorInterface
{
    /**
     * Regex patterns for detecting common PII types.
     *
     * @var array<string, string>
     */
    private const PATTERNS = [
        'email' => '/\b[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}\b/',
        'phone' => '/\b(?:\+?1[-.\s]?)?(?:\(?\d{3}\)?[-.\s]?)?\d{3}[-.\s]?\d{4}\b/',
        'ssn' => '/\b\d{3}-\d{2}-\d{4}\b/',
        'credit_card' => '/\b(?:\d{4}[-\s]?){3}\d{4}\b/',
        'ip_address' => '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',
    ];

    public function __construct(
        private Repository $cache,
        private string $prefix = 'aegis_pii',
        private int $ttl = 3600,
    ) {}

    /**
     * @param  array<int, string>  $piiTypes
     * @return array{text: string, session_id: string}
     */
    public function pseudonymize(string $text, array $piiTypes = []): array
    {
        $sessionId = bin2hex(random_bytes(16));
        $mappings = [];
        $activeTypes = $piiTypes !== [] ? $piiTypes : array_keys(self::PATTERNS);

        foreach ($activeTypes as $type) {
            if (! isset(self::PATTERNS[$type])) {
                continue;
            }

            $text = (string) preg_replace_callback(
                self::PATTERNS[$type],
                function (array $matches) use ($type, &$mappings): string {
                    $token = $this->generateToken($type);
                    $mappings[$token] = $matches[0];

                    return $token;
                },
                $text,
            );
        }

        if ($mappings !== []) {
            $this->cache->put(
                "{$this->prefix}:{$sessionId}",
                $mappings,
                $this->ttl,
            );
        }

        return ['text' => $text, 'session_id' => $sessionId];
    }

    public function depseudonymize(string $text, string $sessionId): string
    {
        /** @var array<string, string>|null $mappings */
        $mappings = $this->cache->get("{$this->prefix}:{$sessionId}");

        if ($mappings === null) {
            return $text;
        }

        return str_replace(
            array_keys($mappings),
            array_values($mappings),
            $text,
        );
    }

    private function generateToken(string $type): string
    {
        $hash = strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

        return '{{AEGIS_'.strtoupper($type).'_'.$hash.'}}';
    }
}
