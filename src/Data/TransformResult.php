<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Data;

final readonly class TransformResult
{
    /**
     * @param  array<string, string>  $tokenMap  Mapping of token → original value (populated only for tokenize action).
     */
    public function __construct(
        public string $text,
        public string $sessionId,
        public int $tokenCount,
        public array $tokenMap = [],
    ) {}
}
