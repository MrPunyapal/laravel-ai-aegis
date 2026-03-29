<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis;

use Illuminate\Contracts\Cache\Repository;
use MrPunyapal\LaravelAiAegis\Contracts\InjectionDetectorInterface;
use MrPunyapal\LaravelAiAegis\Contracts\PiiDetectorInterface;
use MrPunyapal\LaravelAiAegis\Contracts\RecorderInterface;
use MrPunyapal\LaravelAiAegis\Defense\PromptInjectionDetector;
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
            ->hasViews();
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
            \Livewire\Livewire::component('aegis-card', AegisCard::class);
        }
    }

    /**
     * Register the PseudonymizationEngine, using a PHP 8.4+ Lazy Ghost when available
     * for memory efficiency, falling back to eager instantiation on PHP 8.3.
     */
    private function registerPseudonymizationEngine(): void
    {
        $this->app->singleton(PiiDetectorInterface::class, function ($app): PseudonymizationEngine {
            /** @var array{store: string, prefix: string, ttl: int} $config */
            $config = $app['config']['aegis.cache'];

            if (PHP_VERSION_ID >= 80400) {
                $reflector = new ReflectionClass(PseudonymizationEngine::class);

                return $reflector->newLazyGhost(function (PseudonymizationEngine $engine) use ($app, $config): void {
                    $engine->__construct(
                        cache: $app->make(Repository::class),
                        prefix: $config['prefix'] ?? 'aegis_pii',
                        ttl: $config['ttl'] ?? 3600,
                    );
                });
            }

            return new PseudonymizationEngine(
                cache: $app->make(Repository::class),
                prefix: $config['prefix'] ?? 'aegis_pii',
                ttl: $config['ttl'] ?? 3600,
            );
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

            return new PromptInjectionDetector;
        });
    }

    private function registerRecorder(): void
    {
        $this->app->singleton(RecorderInterface::class, AegisRecorder::class);
    }
}
