<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Pii;

use InvalidArgumentException;
use MrPunyapal\LaravelAiAegis\Contracts\PiiTypeInterface;
use MrPunyapal\LaravelAiAegis\Contracts\PiiTypeRegistryInterface;

final class PiiTypeRegistry implements PiiTypeRegistryInterface
{
    /**
     * @var array<string, PiiTypeInterface>
     */
    private array $types = [];

    public function register(PiiTypeInterface $detector): void
    {
        $this->types[$detector->type()] = $detector;
    }

    public function get(string $type): PiiTypeInterface
    {
        if (! $this->has($type)) {
            throw new InvalidArgumentException("Unknown PII type: \"{$type}\". Register it via the aegis.pii.custom_detectors config or PiiTypeRegistry::register().");
        }

        return $this->types[$type];
    }

    public function has(string $type): bool
    {
        return isset($this->types[$type]);
    }

    /**
     * @return array<string, PiiTypeInterface>
     */
    public function all(): array
    {
        return $this->types;
    }
}
