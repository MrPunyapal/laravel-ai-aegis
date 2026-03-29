<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis;

use Illuminate\Contracts\Cache\Repository;
use MrPunyapal\LaravelAiAegis\Contracts\InjectionDetectorInterface;
use MrPunyapal\LaravelAiAegis\Contracts\PiiDetectorInterface;
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
        if (class_exists(\Livewire\Livewire::class)) {
            \Livewire\Livewire::component('aegis-card', AegisCard::class);
        }
    }

    /**
     * Register the PseudonymizationEngine as a PHP 8.5 Lazy Ghost for memory efficiency.
     * The engine is only initialized when first accessed, avoiding cache connections on every request.
     */
    private function registerPseudonymizationEngine(): void
    {
        $this->app->singleton(PiiDetectorInterface::class, function ($app): PseudonymizationEngine {
            $reflector = new ReflectionClass(PseudonymizationEngine::class);

            return $reflector->newLazyGhost(function (PseudonymizationEngine $engine) use ($app): void {
                /** @var array{store: string, prefix: string, ttl: int} $config */
                $config = $app['config']['aegis.cache'];

                $engine->__construct(
                    cache: $app->make(Repository::class),
                    prefix: $config['prefix'] ?? 'aegis_pii',
                    ttl: $config['ttl'] ?? 3600,
                );
            });
        });
    }

    /**
     * Register the PromptInjectionDetector as a PHP 8.5 Lazy Ghost for memory efficiency.
     * The detector's attack vector database is only loaded into memory when a prompt is evaluated.
     */
    private function registerInjectionDetector(): void
    {
        $this->app->singleton(InjectionDetectorInterface::class, function (): PromptInjectionDetector {
            $reflector = new ReflectionClass(PromptInjectionDetector::class);

            return $reflector->newLazyGhost(function (PromptInjectionDetector $detector): void {
                $detector->__construct();
            });
        });
    }

    private function registerRecorder(): void
    {
        $this->app->singleton(AegisRecorder::class);
    }
}
