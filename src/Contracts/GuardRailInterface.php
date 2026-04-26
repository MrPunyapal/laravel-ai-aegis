<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Contracts;

use MrPunyapal\LaravelAiAegis\Data\GuardRailResult;
use MrPunyapal\LaravelAiAegis\Enums\GuardRailStage;

interface GuardRailInterface
{
    public function stage(): GuardRailStage;

    public function check(string $content, mixed $context): GuardRailResult;
}
