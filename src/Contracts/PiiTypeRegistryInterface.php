<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Contracts;

interface PiiTypeRegistryInterface
{
    public function register(PiiTypeInterface $detector): void;

    public function get(string $type): PiiTypeInterface;

    public function has(string $type): bool;

    /**
     * @return array<string, PiiTypeInterface>
     */
    public function all(): array;
}
