<?php

declare(strict_types=1);

use MrPunyapal\LaravelAiAegis\Contracts\InjectionDetectorInterface;
use MrPunyapal\LaravelAiAegis\Contracts\GuardRailOrchestratorInterface;
use MrPunyapal\LaravelAiAegis\Contracts\PiiTransformerInterface;
use MrPunyapal\LaravelAiAegis\Contracts\PiiTypeRegistryInterface;
use MrPunyapal\LaravelAiAegis\Contracts\RecorderInterface;
use MrPunyapal\LaravelAiAegis\Defense\PromptInjectionDetector;
use MrPunyapal\LaravelAiAegis\Pii\PiiRuleParser;
use MrPunyapal\LaravelAiAegis\Pii\PiiTypeRegistry;
use MrPunyapal\LaravelAiAegis\Pseudonymization\PseudonymizationEngine;
use MrPunyapal\LaravelAiAegis\Pulse\AegisRecorder;
use MrPunyapal\LaravelAiAegis\Support\AegisConfigResolver;

describe('container bindings', function (): void {
    test('resolves PiiTypeRegistryInterface to PiiTypeRegistry', function (): void {
        $resolved = app(PiiTypeRegistryInterface::class);

        expect($resolved)->toBeInstanceOf(PiiTypeRegistry::class);
    });

    test('registry has all built-in types registered', function (): void {
        /** @var PiiTypeRegistryInterface $registry */
        $registry = app(PiiTypeRegistryInterface::class);

        foreach (['email', 'phone', 'ssn', 'credit_card', 'ip_address', 'name', 'address', 'date_of_birth', 'bank_account', 'api_key', 'jwt', 'url'] as $type) {
            expect($registry->has($type))->toBeTrue("Missing built-in type: {$type}");
        }
    });

    test('resolves PiiTransformerInterface to PseudonymizationEngine', function (): void {
        $resolved = app(PiiTransformerInterface::class);

        expect($resolved)->toBeInstanceOf(PseudonymizationEngine::class);
    });

    test('PiiTransformerInterface - transform is functional', function (): void {
        $registry = app(PiiTypeRegistryInterface::class);
        $parser = new PiiRuleParser($registry);
        $rules = $parser->parseAll(['email:tokenize']);

        /** @var PiiTransformerInterface $transformer */
        $transformer = app(PiiTransformerInterface::class);
        $result = $transformer->transform('contact john@example.com', $rules);

        expect($result->tokenCount)->toBe(1)
            ->and($result->text)->not->toContain('john@example.com');
    });

    test('resolves InjectionDetectorInterface to PromptInjectionDetector', function (): void {
        $resolved = app(InjectionDetectorInterface::class);

        expect($resolved)->toBeInstanceOf(PromptInjectionDetector::class);
    });

    test('resolves GuardRailOrchestrator singleton', function (): void {
        $a = app(GuardRailOrchestratorInterface::class);
        $b = app(GuardRailOrchestratorInterface::class);

        expect($a)->toBe($b);
    });

    test('resolves AegisConfigResolver', function (): void {
        $resolved = app(AegisConfigResolver::class);

        expect($resolved)->toBeInstanceOf(AegisConfigResolver::class);
    });

    test('resolves RecorderInterface to AegisRecorder', function (): void {
        $resolved = app(RecorderInterface::class);

        expect($resolved)->toBeInstanceOf(AegisRecorder::class);
    });

    test('PiiTypeRegistryInterface is a singleton', function (): void {
        $a = app(PiiTypeRegistryInterface::class);
        $b = app(PiiTypeRegistryInterface::class);

        expect($a)->toBe($b);
    });

    test('InjectionDetectorInterface is a singleton', function (): void {
        $a = app(InjectionDetectorInterface::class);
        $b = app(InjectionDetectorInterface::class);

        expect($a)->toBe($b);
    });

    test('RecorderInterface is a singleton', function (): void {
        $a = app(RecorderInterface::class);
        $b = app(RecorderInterface::class);

        expect($a)->toBe($b);
    });
    test('registers custom PII type from aegis.pii.custom_detectors', function (): void {
        config(['aegis.pii.custom_detectors' => [ServiceProviderTestPiiType::class]]);

        /** @var PiiTypeRegistryInterface $registry */
        $registry = app(PiiTypeRegistryInterface::class);

        expect($registry->has('service_provider_test'))->toBeTrue();
    });
});

describe('config', function (): void {
    test('aegis config is loaded', function (): void {
        expect(config('aegis'))->toBeArray()
            ->and(config('aegis.pii.enabled'))->toBeBool()
            ->and(config('aegis.pii.rules'))->toBeArray()
            ->and(config('aegis.guard_rails'))->toBeArray();
    });

    test('cache uses array store in tests', function (): void {
        expect(config('aegis.cache.store'))->toBe('array');
    });
});

// --- Stubs ---

class ServiceProviderTestPiiType implements \MrPunyapal\LaravelAiAegis\Contracts\PiiTypeInterface
{
    public function type(): string
    {
        return 'service_provider_test';
    }

    public function pattern(): string
    {
        return '/service_provider_test/';
    }
}

