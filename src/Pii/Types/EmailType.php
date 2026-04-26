<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Pii\Types;

use MrPunyapal\LaravelAiAegis\Contracts\PiiTypeInterface;

final readonly class EmailType implements PiiTypeInterface
{
    public function type(): string
    {
        return 'email';
    }

    public function pattern(): string
    {
        return '/\b[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}\b/';
    }
}
