<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Pii\Types;

use MrPunyapal\LaravelAiAegis\Contracts\PiiTypeInterface;

final readonly class JwtType implements PiiTypeInterface
{
    public function type(): string
    {
        return 'jwt';
    }

    public function pattern(): string
    {
        return '/\beyJ[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+\b/';
    }
}
