<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Pii\Types;

use MrPunyapal\LaravelAiAegis\Contracts\PiiTypeInterface;

final readonly class IpAddressType implements PiiTypeInterface
{
    public function type(): string
    {
        return 'ip_address';
    }

    public function pattern(): string
    {
        return '/\b(?:\d{1,3}\.){3}\d{1,3}\b/';
    }
}
