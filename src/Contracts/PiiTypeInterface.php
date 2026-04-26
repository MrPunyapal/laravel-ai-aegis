<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Contracts;

interface PiiTypeInterface
{
    /**
     * Unique name identifying this PII type, e.g. "email" or "api_key".
     */
    public function type(): string;

    /**
     * The regex pattern used to detect this PII type in text.
     */
    public function pattern(): string;
}
