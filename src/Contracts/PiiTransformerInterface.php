<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Contracts;

use MrPunyapal\LaravelAiAegis\Data\PiiRuleConfig;
use MrPunyapal\LaravelAiAegis\Data\TransformResult;

interface PiiTransformerInterface
{
    /**
     * Scan text and apply each rule's action (tokenize / replace / mask).
     *
     * @param  array<int, PiiRuleConfig>  $rules
     */
    public function transform(string $text, array $rules): TransformResult;

    /**
     * Restore tokenized values back to their originals using the session ID.
     * Non-tokenized transforms (replace, mask) are irreversible and are left untouched.
     */
    public function restore(string $text, string $sessionId): string;
}
