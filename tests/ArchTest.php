<?php

declare(strict_types=1);

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

arch('contracts are interfaces')
    ->expect('MrPunyapal\LaravelAiAegis\Contracts')
    ->toBeInterfaces();

arch('exceptions extend RuntimeException')
    ->expect('MrPunyapal\LaravelAiAegis\Exceptions')
    ->toExtend(RuntimeException::class);

arch('strict types are used everywhere')
    ->expect('MrPunyapal\LaravelAiAegis')
    ->toUseStrictTypes();
