<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Tests;

use MrPunyapal\LaravelAiAegis\AegisServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            AegisServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('aegis.cache.store', 'array');
    }
}
