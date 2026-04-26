<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Pii\Types;

use MrPunyapal\LaravelAiAegis\Contracts\PiiTypeInterface;

final readonly class BankAccountType implements PiiTypeInterface
{
    public function type(): string
    {
        return 'bank_account';
    }

    public function pattern(): string
    {
        return '/\b\d{8,17}\b/';
    }
}
