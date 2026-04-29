<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Support;

use Illuminate\Contracts\Foundation\Application;
use Laravel\Pulse\Facades\Pulse;
use MrPunyapal\LaravelAiAegis\Contracts\ApprovalHandlerInterface;
use MrPunyapal\LaravelAiAegis\Contracts\PiiTypeInterface;
use MrPunyapal\LaravelAiAegis\Pii\PiiRuleParser;
use Throwable;

final readonly class AegisDoctor
{
    private const string STATUS_PASS = 'pass';

    private const string STATUS_WARN = 'warn';

    private const string STATUS_FAIL = 'fail';

    public function __construct(
        private PiiRuleParser $parser,
        private Application $app,
        private string $pulseFacade = Pulse::class,
    ) {}

    /**
     * Inspect the current Aegis configuration and optional integrations.
     *
     * @return array<int, array{label: string, status: string, message: string}>
     */
    public function inspect(): array
    {
        return [
            $this->checkPiiRules(),
            $this->checkCustomDetectors(),
            $this->checkApprovalHandler(),
            $this->checkCacheStore(),
            $this->checkPulse(),
        ];
    }

    /**
     * Count check results by status.
     *
     * @param  array<int, array{label: string, status: string, message: string}>  $checks
     * @return array{pass: int, warn: int, fail: int}
     */
    public function summarize(array $checks): array
    {
        $summary = ['pass' => 0, 'warn' => 0, 'fail' => 0];

        foreach ($checks as $check) {
            $summary[$check['status']]++;
        }

        return $summary;
    }

    /**
     * Determine whether any doctor checks failed.
     *
     * @param  array<int, array{label: string, status: string, message: string}>  $checks
     */
    public function hasFailures(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['status'] === self::STATUS_FAIL) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate configured PII rules.
     *
     * @return array{label: string, status: string, message: string}
     */
    private function checkPiiRules(): array
    {
        /** @var array<int, string|array<string, mixed>> $rawRules */
        $rawRules = (array) config('aegis.pii.rules', []);

        if ($rawRules === []) {
            return $this->warn('PII rules', 'No pii.rules are configured. Add at least one rule before protecting prompts.');
        }

        try {
            $rules = $this->parser->parseAll($rawRules);
        } catch (Throwable $throwable) {
            return $this->fail('PII rules', $throwable->getMessage());
        }

        return $this->pass('PII rules', sprintf('Parsed %d configured PII rule(s).', count($rules)));
    }

    /**
     * Validate configured custom PII detector classes.
     *
     * @return array{label: string, status: string, message: string}
     */
    private function checkCustomDetectors(): array
    {
        /** @var array<int, mixed> $customDetectors */
        $customDetectors = (array) config('aegis.pii.custom_detectors', []);

        if ($customDetectors === []) {
            return $this->pass('Custom detectors', 'No custom detectors are configured.');
        }

        foreach ($customDetectors as $detectorClass) {
            if (! is_string($detectorClass) || trim($detectorClass) === '') {
                return $this->fail('Custom detectors', 'Each custom detector must be a non-empty class string.');
            }

            if (! class_exists($detectorClass)) {
                return $this->fail('Custom detectors', sprintf('Configured detector [%s] does not exist.', $detectorClass));
            }

            $instance = $this->app->make($detectorClass);

            if (! $instance instanceof PiiTypeInterface) {
                return $this->fail('Custom detectors', sprintf('Configured detector [%s] must implement %s.', $detectorClass, PiiTypeInterface::class));
            }
        }

        return $this->pass('Custom detectors', sprintf('Validated %d custom detector(s).', count($customDetectors)));
    }

    /**
     * Validate the approval handler configuration.
     *
     * @return array{label: string, status: string, message: string}
     */
    private function checkApprovalHandler(): array
    {
        $approvalEnabled = (bool) config('aegis.guard_rails.approval.enabled', false);
        $approvalHandler = config('aegis.guard_rails.approval.handler');

        if ($approvalHandler === null || $approvalHandler === '') {
            return $approvalEnabled
                ? $this->fail('Approval handler', 'Approval is enabled but no approval handler is configured.')
                : $this->pass('Approval handler', 'Approval guard rail is disabled.');
        }

        if (! is_string($approvalHandler) || ! class_exists($approvalHandler)) {
            return $this->fail('Approval handler', sprintf('Configured approval handler [%s] does not exist.', (string) $approvalHandler));
        }

        $instance = $this->app->make($approvalHandler);

        if (! $instance instanceof ApprovalHandlerInterface) {
            return $this->fail('Approval handler', sprintf('Configured approval handler [%s] must implement %s.', $approvalHandler, ApprovalHandlerInterface::class));
        }

        if (! $approvalEnabled) {
            return $this->warn('Approval handler', sprintf('Approval handler [%s] is configured but the approval guard rail is disabled.', $approvalHandler));
        }

        return $this->pass('Approval handler', sprintf('Resolved approval handler [%s].', $approvalHandler));
    }

    /**
     * Validate the configured cache store.
     *
     * @return array{label: string, status: string, message: string}
     */
    private function checkCacheStore(): array
    {
        $store = trim((string) config('aegis.cache.store', ''));

        if ($store === '') {
            return $this->fail('Cache store', 'aegis.cache.store must be configured.');
        }

        $storeConfig = config('cache.stores.'.$store);

        if (! is_array($storeConfig)) {
            return $this->fail('Cache store', sprintf('Cache store [%s] is not defined in cache.stores.', $store));
        }

        if ($store === 'array') {
            return $this->warn('Cache store', 'Array cache works for local testing, but Redis is recommended in production.');
        }

        return $this->pass('Cache store', sprintf('Cache store [%s] is available.', $store));
    }

    /**
     * Validate the optional Laravel Pulse integration.
     *
     * @return array{label: string, status: string, message: string}
     */
    private function checkPulse(): array
    {
        if (! (bool) config('aegis.pulse.enabled', true)) {
            return $this->pass('Pulse', 'Pulse integration is disabled.');
        }

        if (! class_exists($this->pulseFacade)) {
            return $this->warn('Pulse', 'Pulse integration is enabled, but Laravel Pulse is not installed.');
        }

        return $this->pass('Pulse', 'Pulse integration is available.');
    }

    /**
     * Build a passing doctor result.
     *
     * @return array{label: string, status: string, message: string}
     */
    private function pass(string $label, string $message): array
    {
        return ['label' => $label, 'status' => self::STATUS_PASS, 'message' => $message];
    }

    /**
     * Build a warning doctor result.
     *
     * @return array{label: string, status: string, message: string}
     */
    private function warn(string $label, string $message): array
    {
        return ['label' => $label, 'status' => self::STATUS_WARN, 'message' => $message];
    }

    /**
     * Build a failing doctor result.
     *
     * @return array{label: string, status: string, message: string}
     */
    private function fail(string $label, string $message): array
    {
        return ['label' => $label, 'status' => self::STATUS_FAIL, 'message' => $message];
    }
}
