<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Data;

use MrPunyapal\LaravelAiAegis\Enums\PiiAction;

final readonly class PiiRuleConfig
{
    public function __construct(
        public string $type,
        public PiiAction $action = PiiAction::Tokenize,
        /** @var int<0, max> Characters to keep at the start of the value (mask action only). */
        public int $maskStart = 0,
        /** @var int<0, max> Characters to keep at the end of the value (mask action only). */
        public int $maskEnd = 0,
        /** @var string Static replacement text (replace action only). Empty string = auto-generate as [REDACTED:type]. */
        public string $replacement = '',
    ) {}

    public function maskChar(): string
    {
        return '*';
    }
}
