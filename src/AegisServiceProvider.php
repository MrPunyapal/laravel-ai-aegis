<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Livewire\Livewire;
use MrPunyapal\LaravelAiAegis\Commands\InstallCommand;
use MrPunyapal\LaravelAiAegis\Commands\TestPromptCommand;
use MrPunyapal\LaravelAiAegis\Contracts\InjectionDetectorInterface;
use MrPunyapal\LaravelAiAegis\Contracts\PiiDetectorInterface;
use MrPunyapal\LaravelAiAegis\Contracts\RecorderInterface;
use MrPunyapal\LaravelAiAegis\Defense\PromptInjectionDetector;
use MrPunyapal\LaravelAiAegis\Middleware\AegisMiddleware;
use MrPunyapal\LaravelAiAegis\Pseudonymization\PseudonymizationEngine;
use MrPunyapal\LaravelAiAegis\Pulse\AegisCard;
use MrPunyapal\LaravelAiAegis\Pulse\AegisRecorder;
use ReflectionClass;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class AegisServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('aegis')
            ->hasConfigFile()
            ->hasViews()
            ->hasCommands([
                InstallCommand::class,
                TestPromptCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->registerPseudonymizationEngine();
        $this->registerInjectionDetector();
        $this->registerRecorder();
    }

    public function packageBooted(): void
    {
        if ($this->app->bound('livewire')) {
            Livewire::component('aegis-card', AegisCard::class); // @codeCoverageIgnore
        }

        if ($this->app->bound('router')) {
            /** @var Router $router */
            $router = $this->app->make('router');
            $router->aliasMiddleware('aegis', AegisMiddleware::class);
        }
    }

    /**
     * Register the PseudonymizationEngine, using a PHP 8.4+ Lazy Ghost when available
     * for memory efficiency, falling back to eager instantiation on PHP 8.3.
     */
    private function registerPseudonymizationEngine(): void
    {
        $this->app->singleton(PiiDetectorInterface::class, function (Application $app): PseudonymizationEngine {
            /** @var array{store: string, prefix: string, ttl: int} $config */
            $config = $app['config']['aegis.cache'];

            if (PHP_VERSION_ID >= 80400) {
                $reflector = new ReflectionClass(PseudonymizationEngine::class);

                return $reflector->newLazyGhost(function (PseudonymizationEngine $engine) use ($app, $config): void {
                    $engine->__construct(
                        cache: $app->make(Repository::class),
                        prefix: $config['prefix'],
                        ttl: $config['ttl'],
                    );
                });
            }

            // @codeCoverageIgnoreStart
            return new PseudonymizationEngine(
                cache: $app->make(Repository::class),
                prefix: $config['prefix'],
                ttl: $config['ttl'],
            );
            // @codeCoverageIgnoreEnd
        });
    }

    /**
     * Register the PromptInjectionDetector, using a PHP 8.4+ Lazy Ghost when available
     * for memory efficiency, falling back to eager instantiation on PHP 8.3.
     */
    private function registerInjectionDetector(): void
    {
        $this->app->singleton(InjectionDetectorInterface::class, function (): PromptInjectionDetector {
            if (PHP_VERSION_ID >= 80400) {
                $reflector = new ReflectionClass(PromptInjectionDetector::class);

                return $reflector->newLazyGhost(function (PromptInjectionDetector $detector): void {
                    $detector->__construct();
                });
            }

            // @codeCoverageIgnoreStart
            return new PromptInjectionDetector;
            // @codeCoverageIgnoreEnd
        });
    }

    private function registerRecorder(): void
    {
        $this->app->singleton(RecorderInterface::class, AegisRecorder::class);
    }
}
