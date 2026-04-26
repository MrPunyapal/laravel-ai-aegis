<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Pii\Types;

use MrPunyapal\LaravelAiAegis\Contracts\PiiTypeInterface;

final readonly class SsnType implements PiiTypeInterface
{
    public function type(): string
    {
        return 'ssn';
    }

    public function pattern(): string
    {
        return '/\b\d{3}-\d{2}-\d{4}\b/';
    }
}
