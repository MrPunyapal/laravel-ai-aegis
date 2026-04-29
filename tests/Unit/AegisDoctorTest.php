<?php

declare(strict_types=1);

use Laravel\Pulse\Facades\Pulse;
use MrPunyapal\LaravelAiAegis\Contracts\ApprovalHandlerInterface;
use MrPunyapal\LaravelAiAegis\Contracts\PiiTypeInterface;
use MrPunyapal\LaravelAiAegis\Pii\PiiRuleParser;
use MrPunyapal\LaravelAiAegis\Support\AegisDoctor;
use MrPunyapal\LaravelAiAegis\Tests\TestCase;

pest()->extend(TestCase::class);

function createAegisDoctor(?string $pulseFacade = null): AegisDoctor
{
    return new AegisDoctor(
        parser: app(PiiRuleParser::class),
        app: app(),
        pulseFacade: $pulseFacade ?? Pulse::class,
    );
}

/**
 * @param  array<int, array{label: string, status: string, message: string}>  $checks
 * @return array{label: string, status: string, message: string}
 */
function aegisDoctorCheck(array $checks, string $label): array
{
    foreach ($checks as $check) {
        if ($check['label'] === $label) {
            return $check;
        }
    }

    throw new RuntimeException(sprintf('Doctor check [%s] was not found.', $label));
}

/**
 * @return array{label: string, status: string, message: string}
 */
function invokeAegisDoctorCheck(AegisDoctor $doctor, string $method): array
{
    $reflection = new ReflectionMethod($doctor, $method);

    /** @var array{label: string, status: string, message: string} $result */
    $result = $reflection->invoke($doctor);

    return $result;
}

it('summarizes the current config without failures', function (): void {
    $doctor = createAegisDoctor();

    $checks = $doctor->inspect();
    $summary = $doctor->summarize($checks);

    expect($doctor->hasFailures($checks))->toBeFalse()
        ->and($summary['pass'])->toBeGreaterThanOrEqual(1)
        ->and($summary['warn'])->toBeGreaterThanOrEqual(1)
        ->and($summary['fail'])->toBe(0);
});

it('reports failures when a failing check is present', function (): void {
    expect(createAegisDoctor()->hasFailures([
        ['label' => 'PII rules', 'status' => 'fail', 'message' => 'Broken config.'],
    ]))->toBeTrue();
});

it('fails when pii rules cannot be parsed', function (): void {
    config(['aegis.pii.rules' => ['unknown_type:tokenize']]);

    $checks = createAegisDoctor()->inspect();

    expect(aegisDoctorCheck($checks, 'PII rules')['status'])->toBe('fail');
});

it('warns when no pii rules are configured', function (): void {
    config(['aegis.pii.rules' => []]);

    $check = invokeAegisDoctorCheck(createAegisDoctor(), 'checkPiiRules');

    expect($check['status'])->toBe('warn');
});

it('fails when a custom detector class is missing', function (): void {
    config(['aegis.pii.custom_detectors' => ['Tests\\MissingDetector']]);

    $checks = createAegisDoctor()->inspect();

    expect(aegisDoctorCheck($checks, 'Custom detectors')['status'])->toBe('fail');
});

it('passes when a valid custom detector is configured', function (): void {
    config(['aegis.pii.custom_detectors' => [AegisDoctorPiiType::class]]);

    $checks = createAegisDoctor()->inspect();

    expect(aegisDoctorCheck($checks, 'Custom detectors')['status'])->toBe('pass');
});

it('fails when a custom detector class string is empty', function (): void {
    config(['aegis.pii.custom_detectors' => ['   ']]);

    $check = invokeAegisDoctorCheck(createAegisDoctor(), 'checkCustomDetectors');

    expect($check['status'])->toBe('fail');
});

it('fails when a custom detector does not implement the interface', function (): void {
    $doctor = createAegisDoctor();

    config(['aegis.pii.custom_detectors' => [stdClass::class]]);

    $check = invokeAegisDoctorCheck($doctor, 'checkCustomDetectors');

    expect($check['status'])->toBe('fail');
});

it('fails when approval is enabled without a handler', function (): void {
    config(['aegis.guard_rails.approval.enabled' => true]);
    config(['aegis.guard_rails.approval.handler' => null]);

    $checks = createAegisDoctor()->inspect();

    expect(aegisDoctorCheck($checks, 'Approval handler')['status'])->toBe('fail');
});

it('warns when approval handler is configured but disabled', function (): void {
    config(['aegis.guard_rails.approval.enabled' => false]);
    config(['aegis.guard_rails.approval.handler' => AegisDoctorApprovalHandler::class]);

    $checks = createAegisDoctor()->inspect();

    expect(aegisDoctorCheck($checks, 'Approval handler')['status'])->toBe('warn');
});

it('passes when approval is disabled without a handler', function (): void {
    config(['aegis.guard_rails.approval.enabled' => false]);
    config(['aegis.guard_rails.approval.handler' => null]);

    $checks = createAegisDoctor()->inspect();

    expect(aegisDoctorCheck($checks, 'Approval handler')['status'])->toBe('pass');
});

it('passes when approval is enabled with a valid handler', function (): void {
    config(['aegis.guard_rails.approval.enabled' => true]);
    config(['aegis.guard_rails.approval.handler' => AegisDoctorApprovalHandler::class]);

    $check = invokeAegisDoctorCheck(createAegisDoctor(), 'checkApprovalHandler');

    expect($check['status'])->toBe('pass');
});

it('fails when approval handler class is missing', function (): void {
    config(['aegis.guard_rails.approval.enabled' => true]);
    config(['aegis.guard_rails.approval.handler' => 'Tests\\MissingApprovalHandler']);

    $check = invokeAegisDoctorCheck(createAegisDoctor(), 'checkApprovalHandler');

    expect($check['status'])->toBe('fail');
});

it('fails when approval handler does not implement the interface', function (): void {
    config(['aegis.guard_rails.approval.enabled' => true]);
    config(['aegis.guard_rails.approval.handler' => stdClass::class]);

    $check = invokeAegisDoctorCheck(createAegisDoctor(), 'checkApprovalHandler');

    expect($check['status'])->toBe('fail');
});

it('fails when the configured cache store is missing', function (): void {
    config(['aegis.cache.store' => 'missing_store']);

    $checks = createAegisDoctor()->inspect();

    expect(aegisDoctorCheck($checks, 'Cache store')['status'])->toBe('fail');
});

it('fails when the configured cache store is empty', function (): void {
    config(['aegis.cache.store' => '   ']);

    $check = invokeAegisDoctorCheck(createAegisDoctor(), 'checkCacheStore');

    expect($check['status'])->toBe('fail');
});

it('passes when the configured cache store is available', function (): void {
    config(['aegis.cache.store' => 'redis']);

    $checks = createAegisDoctor()->inspect();

    expect(aegisDoctorCheck($checks, 'Cache store')['status'])->toBe('pass');
});

it('warns when pulse is enabled but unavailable', function (): void {
    config(['aegis.pii.custom_detectors' => [AegisDoctorPiiType::class]]);

    $checks = createAegisDoctor('Tests\\MissingPulseFacade')->inspect();

    expect(aegisDoctorCheck($checks, 'Custom detectors')['status'])->toBe('pass')
        ->and(aegisDoctorCheck($checks, 'Pulse')['status'])->toBe('warn');
});

it('passes when pulse integration is disabled', function (): void {
    config(['aegis.pulse.enabled' => false]);

    $checks = createAegisDoctor()->inspect();

    expect(aegisDoctorCheck($checks, 'Pulse')['status'])->toBe('pass');
});

class AegisDoctorApprovalHandler implements ApprovalHandlerInterface
{
    public function approve(string $content, mixed $context): bool
    {
        return true;
    }
}

class AegisDoctorPiiType implements PiiTypeInterface
{
    public function type(): string
    {
        return 'doctor_test';
    }

    public function pattern(): string
    {
        return '/doctor_test/';
    }
}
