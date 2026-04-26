<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Pii\Types;

use MrPunyapal\LaravelAiAegis\Contracts\PiiTypeInterface;

final readonly class CreditCardType implements PiiTypeInterface
{
    public function type(): string
    {
        return 'credit_card';
    }

    public function pattern(): string
    {
        return '/\b(?:\d{4}[-\s]?){3}\d{4}\b/';
    }
}
