<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Aegis
{
    /**
     * @param  array<int, string>  $piiTypes
     */
    public function __construct(
        public bool $blockInjections = true,
        public bool $pseudonymize = true,
        public bool $strictMode = false,
        public array $piiTypes = ['email', 'phone', 'ssn', 'credit_card', 'ip_address'],
    ) {}
}
