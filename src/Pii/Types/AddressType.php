<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Pii\Types;

use MrPunyapal\LaravelAiAegis\Contracts\PiiTypeInterface;

final readonly class AddressType implements PiiTypeInterface
{
    public function type(): string
    {
        return 'address';
    }

    public function pattern(): string
    {
        return '/\b\d{1,5}\s+(?:[A-Za-z0-9.]+\s+){1,4}(?:St(?:reet)?|Ave(?:nue)?|Blvd|Rd|Dr(?:ive)?|Ln|Ct|Way|Pl(?:ace)?)\b/i';
    }
}
