<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Pii\Types;

use MrPunyapal\LaravelAiAegis\Contracts\PiiTypeInterface;

final readonly class PhoneType implements PiiTypeInterface
{
    public function type(): string
    {
        return 'phone';
    }

    public function pattern(): string
    {
        return '/\b(?:\+?1[-.\s]?)?(?:\(?\d{3}\)?[-.\s]?)?\d{3}[-.\s]?\d{4}\b/';
    }
}
