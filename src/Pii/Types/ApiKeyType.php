<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Pii\Types;

use MrPunyapal\LaravelAiAegis\Contracts\PiiTypeInterface;

final readonly class ApiKeyType implements PiiTypeInterface
{
    public function type(): string
    {
        return 'api_key';
    }

    public function pattern(): string
    {
        return '/\b(?:sk|pk|rk|ak|api)[-_][A-Za-z0-9\-_]{16,}\b/i';
    }
}
