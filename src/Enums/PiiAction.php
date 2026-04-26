<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Enums;

enum PiiAction: string
{
    /**
     * Replace PII with a reversible token stored in cache.
     * The original value can be restored after the LLM response.
     */
    case Tokenize = 'tokenize';

    /**
     * Replace PII with a static, irreversible placeholder string.
     * e.g.  [REDACTED:email]
     */
    case Replace = 'replace';

    /**
     * Partially obscure the PII value, preserving configurable leading/trailing characters.
     * Defaults to full masking when no counts are provided.
     */
    case Mask = 'mask';
}
