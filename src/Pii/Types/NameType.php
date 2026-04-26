<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Pii\Types;

use MrPunyapal\LaravelAiAegis\Contracts\PiiTypeInterface;

final readonly class NameType implements PiiTypeInterface
{
    public function type(): string
    {
        return 'name';
    }

    public function pattern(): string
    {
        return '/\b[A-Z][a-z]+(?: [A-Z][a-z]+)+\b/';
    }
}
