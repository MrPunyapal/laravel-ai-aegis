<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Livewire\Livewire;
use MrPunyapal\LaravelAiAegis\Commands\InstallCommand;
use MrPunyapal\LaravelAiAegis\Commands\TestPromptCommand;
use MrPunyapal\LaravelAiAegis\Contracts\ApprovalHandlerInterface;
use MrPunyapal\LaravelAiAegis\Contracts\GuardRailOrchestratorInterface;
use MrPunyapal\LaravelAiAegis\Contracts\InjectionDetectorInterface;
use MrPunyapal\LaravelAiAegis\Contracts\PiiTransformerInterface;
use MrPunyapal\LaravelAiAegis\Contracts\PiiTypeRegistryInterface;
use MrPunyapal\LaravelAiAegis\Contracts\RecorderInterface;
use MrPunyapal\LaravelAiAegis\Defense\PromptInjectionDetector;
use MrPunyapal\LaravelAiAegis\Enums\GuardRailStage;
use MrPunyapal\LaravelAiAegis\GuardRails\ApprovalGuardRail;
use MrPunyapal\LaravelAiAegis\GuardRails\BlockedPhrasesGuardRail;
use MrPunyapal\LaravelAiAegis\GuardRails\GuardRailOrchestrator;
use MrPunyapal\LaravelAiAegis\GuardRails\InjectionGuardRail;
use MrPunyapal\LaravelAiAegis\GuardRails\MaxLengthGuardRail;
use MrPunyapal\LaravelAiAegis\GuardRails\OutputPiiGuardRail;
use MrPunyapal\LaravelAiAegis\GuardRails\ToolGuardRail;
use MrPunyapal\LaravelAiAegis\Middleware\AegisMiddleware;
use MrPunyapal\LaravelAiAegis\Pii\PiiRuleParser;
use MrPunyapal\LaravelAiAegis\Pii\PiiTypeRegistry;
use MrPunyapal\LaravelAiAegis\Pii\Types\AddressType;
use MrPunyapal\LaravelAiAegis\Pii\Types\ApiKeyType;
use MrPunyapal\LaravelAiAegis\Pii\Types\BankAccountType;
use MrPunyapal\LaravelAiAegis\Pii\Types\CreditCardType;
use MrPunyapal\LaravelAiAegis\Pii\Types\DateOfBirthType;
use MrPunyapal\LaravelAiAegis\Pii\Types\EmailType;
use MrPunyapal\LaravelAiAegis\Pii\Types\IpAddressType;
use MrPunyapal\LaravelAiAegis\Pii\Types\JwtType;
use MrPunyapal\LaravelAiAegis\Pii\Types\NameType;
use MrPunyapal\LaravelAiAegis\Pii\Types\PhoneType;
use MrPunyapal\LaravelAiAegis\Pii\Types\SsnType;
use MrPunyapal\LaravelAiAegis\Pii\Types\UrlType;
use MrPunyapal\LaravelAiAegis\Pseudonymization\PseudonymizationEngine;
use MrPunyapal\LaravelAiAegis\Pulse\AegisCard;
use MrPunyapal\LaravelAiAegis\Pulse\AegisRecorder;
use MrPunyapal\LaravelAiAegis\Support\AegisConfigResolver;
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
        $this->registerPiiTypeRegistry();
        $this->registerPiiTransformer();
        $this->registerInjectionDetector();
        $this->registerGuardRailOrchestrator();
        $this->registerConfigResolver();
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

    private function registerPiiTypeRegistry(): void
    {
        $this->app->singleton(PiiTypeRegistryInterface::class, function (Application $app): PiiTypeRegistry {
            $registry = new PiiTypeRegistry;

            foreach ($this->builtInPiiTypes() as $type) {
                $registry->register($type);
            }

            /** @var array<int, class-string> $customDetectors */
            $customDetectors = (array) $app['config']['aegis.pii.custom_detectors'];

            foreach ($customDetectors as $class) {
                if (class_exists($class)) {
                    $registry->register($app->make($class));
                }
            }

            return $registry;
        });
    }

    private function registerPiiTransformer(): void
    {
        $this->app->singleton(PiiTransformerInterface::class, function (Application $app): PseudonymizationEngine {
            /** @var array{store: string, prefix: string, ttl: int} $config */
            $config = $app['config']['aegis.cache'];

            return new PseudonymizationEngine(
                cache: $app->make(Repository::class),
                registry: $app->make(PiiTypeRegistryInterface::class),
                prefix: $config['prefix'],
                ttl: $config['ttl'],
            );
        });
    }

    private function registerInjectionDetector(): void
    {
        $this->app->singleton(InjectionDetectorInterface::class, fn (): PromptInjectionDetector => new PromptInjectionDetector);
    }

    private function registerGuardRailOrchestrator(): void
    {
        $this->app->singleton(GuardRailOrchestratorInterface::class, function (Application $app): GuardRailOrchestrator {
            $orchestrator = new GuardRailOrchestrator;

            $injectionThreshold = (float) $app['config']->get('aegis.guard_rails.input.injection.threshold', 0.7);
            $strictThreshold = (float) $app['config']->get('aegis.guard_rails.input.injection.strict_threshold', 0.3);

            $orchestrator->register(new InjectionGuardRail(
                detector: $app->make(InjectionDetectorInterface::class),
                defaultThreshold: $injectionThreshold,
                strictThreshold: $strictThreshold,
            ));

            $orchestrator->register(new MaxLengthGuardRail);

            $orchestrator->register(new BlockedPhrasesGuardRail(GuardRailStage::Input));

            $orchestrator->register(new OutputPiiGuardRail(
                transformer: $app->make(PiiTransformerInterface::class),
            ));

            $orchestrator->register(new BlockedPhrasesGuardRail(GuardRailStage::Output));

            $orchestrator->register(new ToolGuardRail);

            $orchestrator->register(new ApprovalGuardRail);

            return $orchestrator;
        });
    }

    private function registerConfigResolver(): void
    {
        $this->app->singleton(AegisConfigResolver::class, function (Application $app): AegisConfigResolver {
            return new AegisConfigResolver(
                parser: new PiiRuleParser(
                    registry: $app->make(PiiTypeRegistryInterface::class),
                ),
            );
        });
    }

    private function registerRecorder(): void
    {
        $this->app->singleton(RecorderInterface::class, AegisRecorder::class);
    }

    /**
     * @return array<int, \MrPunyapal\LaravelAiAegis\Contracts\PiiTypeInterface>
     */
    private function builtInPiiTypes(): array
    {
        return [
            new EmailType,
            new PhoneType,
            new SsnType,
            new CreditCardType,
            new IpAddressType,
            new NameType,
            new AddressType,
            new DateOfBirthType,
            new BankAccountType,
            new ApiKeyType,
            new JwtType,
            new UrlType,
        ];
    }
}
