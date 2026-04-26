<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Pii\Types;

use MrPunyapal\LaravelAiAegis\Contracts\PiiTypeInterface;

final readonly class UrlType implements PiiTypeInterface
{
    public function type(): string
    {
        return 'url';
    }

    public function pattern(): string
    {
        return '/\bhttps?:\/\/[^\s<>"\']+/i';
    }
}
