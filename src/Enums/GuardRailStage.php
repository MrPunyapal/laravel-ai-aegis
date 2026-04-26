<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Enums;

enum GuardRailStage: string
{
    case Input = 'input';

    case Output = 'output';

    case Tool = 'tool';

    case Schema = 'schema';

    case Approval = 'approval';
}
