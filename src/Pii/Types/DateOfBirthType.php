<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Pii\Types;

use MrPunyapal\LaravelAiAegis\Contracts\PiiTypeInterface;

final readonly class DateOfBirthType implements PiiTypeInterface
{
    public function type(): string
    {
        return 'date_of_birth';
    }

    public function pattern(): string
    {
        return '/\b(?:0?[1-9]|[12]\d|3[01])[-\/.](?:0?[1-9]|1[0-2])[-\/.](?:19|20)\d{2}\b|\b(?:19|20)\d{2}[-\/.](?:0?[1-9]|1[0-2])[-\/.](?:0?[1-9]|[12]\d|3[01])\b/';
    }
}
