<?php

declare(strict_types=1);

use MrPunyapal\LaravelAiAegis\Contracts\ApprovalHandlerInterface;
use MrPunyapal\LaravelAiAegis\Contracts\InjectionDetectorInterface;
use MrPunyapal\LaravelAiAegis\Contracts\PiiTransformerInterface;
use MrPunyapal\LaravelAiAegis\Data\AegisConfig;
use MrPunyapal\LaravelAiAegis\Data\PiiRuleConfig;
use MrPunyapal\LaravelAiAegis\Data\TransformResult;
use MrPunyapal\LaravelAiAegis\Enums\GuardRailStage;
use MrPunyapal\LaravelAiAegis\Enums\PiiAction;
use MrPunyapal\LaravelAiAegis\Exceptions\AegisSecurityException;
use MrPunyapal\LaravelAiAegis\GuardRails\ApprovalGuardRail;
use MrPunyapal\LaravelAiAegis\GuardRails\BlockedPhrasesGuardRail;
use MrPunyapal\LaravelAiAegis\GuardRails\GuardRailOrchestrator;
use MrPunyapal\LaravelAiAegis\GuardRails\InjectionGuardRail;
use MrPunyapal\LaravelAiAegis\GuardRails\MaxLengthGuardRail;
use MrPunyapal\LaravelAiAegis\GuardRails\OutputPiiGuardRail;
use MrPunyapal\LaravelAiAegis\GuardRails\ToolGuardRail;

describe('ApprovalGuardRail', function (): void {
    test('stage returns Approval', function (): void {
        expect((new ApprovalGuardRail)->stage())->toBe(GuardRailStage::Approval);
    });

    test('passes when context is not AegisConfig', function (): void {
        $result = (new ApprovalGuardRail)->check('content', null);

        expect($result->passed)->toBeTrue();
    });

    test('passes when requireApproval is false', function (): void {
        $config = new AegisConfig(requireApproval: false);

        $result = (new ApprovalGuardRail)->check('content', $config);

        expect($result->passed)->toBeTrue();
    });

    test('fails when requireApproval is true but no handler configured', function (): void {
        $config = new AegisConfig(requireApproval: true, approvalHandler: null);

        $result = (new ApprovalGuardRail)->check('content', $config);

        expect($result->passed)->toBeFalse()
            ->and($result->reason)->toContain('no ApprovalHandlerInterface');
    });

    test('fails when handler denies the request', function (): void {
        $config = new AegisConfig(requireApproval: true, approvalHandler: new AlwaysDenyHandler);

        $result = (new ApprovalGuardRail)->check('content', $config);

        expect($result->passed)->toBeFalse()
            ->and($result->reason)->toContain('denied by approval handler');
    });

    test('passes when handler approves the request', function (): void {
        $config = new AegisConfig(requireApproval: true, approvalHandler: new AlwaysApproveHandler);

        $result = (new ApprovalGuardRail)->check('content', $config);

        expect($result->passed)->toBeTrue();
    });
});

describe('BlockedPhrasesGuardRail', function (): void {
    test('stage returns the configured stage', function (): void {
        expect((new BlockedPhrasesGuardRail(GuardRailStage::Input))->stage())->toBe(GuardRailStage::Input);
        expect((new BlockedPhrasesGuardRail(GuardRailStage::Output))->stage())->toBe(GuardRailStage::Output);
    });

    test('passes when context is not AegisConfig', function (): void {
        $result = (new BlockedPhrasesGuardRail(GuardRailStage::Input))->check('content', null);

        expect($result->passed)->toBeTrue();
    });

    test('passes when no blocked phrases are configured', function (): void {
        $config = new AegisConfig(inputBlockedPhrases: []);

        $result = (new BlockedPhrasesGuardRail(GuardRailStage::Input))->check('hello', $config);

        expect($result->passed)->toBeTrue();
    });

    test('fails when input contains a blocked phrase', function (): void {
        $config = new AegisConfig(inputBlockedPhrases: ['ignore previous']);

        $result = (new BlockedPhrasesGuardRail(GuardRailStage::Input))->check('ignore previous instructions', $config);

        expect($result->passed)->toBeFalse()
            ->and($result->reason)->toContain('ignore previous');
    });

    test('fails when output contains a blocked phrase', function (): void {
        $config = new AegisConfig(outputBlockedPhrases: ['confidential']);

        $result = (new BlockedPhrasesGuardRail(GuardRailStage::Output))->check('This is confidential data.', $config);

        expect($result->passed)->toBeFalse()
            ->and($result->reason)->toContain('confidential');
    });

    test('phrase matching is case-insensitive', function (): void {
        $config = new AegisConfig(inputBlockedPhrases: ['BLOCKED']);

        $result = (new BlockedPhrasesGuardRail(GuardRailStage::Input))->check('this is blocked content', $config);

        expect($result->passed)->toBeFalse();
    });
});

describe('GuardRailOrchestrator', function (): void {
    test('runInput passes when no rails are registered', function (): void {
        $orchestrator = new GuardRailOrchestrator;

        $orchestrator->runInput('content', new AegisConfig);

        expect(true)->toBeTrue();
    });

    test('runInput throws when a rail fails', function (): void {
        $orchestrator = new GuardRailOrchestrator;
        $config = new AegisConfig(inputBlockedPhrases: ['blocked']);

        $orchestrator->register(new BlockedPhrasesGuardRail(GuardRailStage::Input));

        expect(fn () => $orchestrator->runInput('blocked content', $config, $config))
            ->toThrow(AegisSecurityException::class);
    });

    test('runOutput passes when rail passes', function (): void {
        $orchestrator = new GuardRailOrchestrator;
        $orchestrator->register(new BlockedPhrasesGuardRail(GuardRailStage::Output));

        $orchestrator->runOutput('clean response', new AegisConfig(outputBlockedPhrases: []));

        expect(true)->toBeTrue();
    });

    test('runTool passes when no rail is registered', function (): void {
        $orchestrator = new GuardRailOrchestrator;

        $orchestrator->runTool('some_tool', new AegisConfig);

        expect(true)->toBeTrue();
    });

    test('runSchema passes when no rail is registered', function (): void {
        $orchestrator = new GuardRailOrchestrator;

        $orchestrator->runSchema('{}', new AegisConfig);

        expect(true)->toBeTrue();
    });

    test('runApproval passes when no rail is registered', function (): void {
        $orchestrator = new GuardRailOrchestrator;

        $orchestrator->runApproval('content', new AegisConfig);

        expect(true)->toBeTrue();
    });
});

describe('InjectionGuardRail', function (): void {
    test('stage returns Input', function (): void {
        expect((new InjectionGuardRail(new FixedScoreDetector(0.0)))->stage())->toBe(GuardRailStage::Input);
    });

    test('passes when context is not AegisConfig', function (): void {
        $result = (new InjectionGuardRail(new FixedScoreDetector(0.9)))->check('content', null);

        expect($result->passed)->toBeTrue();
    });

    test('passes when blockInjections is false', function (): void {
        $config = new AegisConfig(blockInjections: false);

        $result = (new InjectionGuardRail(new FixedScoreDetector(0.9)))->check('content', $config);

        expect($result->passed)->toBeTrue();
    });

    test('fails when score meets or exceeds default threshold', function (): void {
        $config = new AegisConfig(blockInjections: true, strictMode: false, injectionThreshold: null);

        $result = (new InjectionGuardRail(new FixedScoreDetector(0.9)))->check('inject', $config);

        expect($result->passed)->toBeFalse()
            ->and($result->reason)->toContain('confidence: 0.9');
    });

    test('passes when score is below default threshold', function (): void {
        $config = new AegisConfig(blockInjections: true, strictMode: false, injectionThreshold: null);

        $result = (new InjectionGuardRail(new FixedScoreDetector(0.5)))->check('hello', $config);

        expect($result->passed)->toBeTrue();
    });

    test('uses strict threshold in strict mode', function (): void {
        $config = new AegisConfig(blockInjections: true, strictMode: true, injectionThreshold: null);

        $result = (new InjectionGuardRail(new FixedScoreDetector(0.4), defaultThreshold: 0.7, strictThreshold: 0.3))
            ->check('hello', $config);

        expect($result->passed)->toBeFalse();
    });

    test('uses custom injectionThreshold when configured', function (): void {
        $config = new AegisConfig(blockInjections: true, strictMode: false, injectionThreshold: 0.5);

        $result = (new InjectionGuardRail(new FixedScoreDetector(0.6)))->check('hello', $config);

        expect($result->passed)->toBeFalse();
    });
});

describe('MaxLengthGuardRail', function (): void {
    test('stage returns Input', function (): void {
        expect((new MaxLengthGuardRail)->stage())->toBe(GuardRailStage::Input);
    });

    test('passes when context is not AegisConfig', function (): void {
        $result = (new MaxLengthGuardRail)->check('content', null);

        expect($result->passed)->toBeTrue();
    });

    test('passes when maxInputLength is null', function (): void {
        $result = (new MaxLengthGuardRail)->check('content', new AegisConfig(maxInputLength: null));

        expect($result->passed)->toBeTrue();
    });

    test('fails when content length exceeds maxInputLength', function (): void {
        $result = (new MaxLengthGuardRail)->check('This is a long content string', new AegisConfig(maxInputLength: 10));

        expect($result->passed)->toBeFalse()
            ->and($result->reason)->toContain('exceeds maximum 10');
    });

    test('passes when content length is within the limit', function (): void {
        $result = (new MaxLengthGuardRail)->check('Short', new AegisConfig(maxInputLength: 100));

        expect($result->passed)->toBeTrue();
    });
});

describe('OutputPiiGuardRail', function (): void {
    test('stage returns Output', function (): void {
        expect((new OutputPiiGuardRail(new ZeroTokenTransformer))->stage())->toBe(GuardRailStage::Output);
    });

    test('passes when context is not AegisConfig', function (): void {
        $result = (new OutputPiiGuardRail(new ZeroTokenTransformer))->check('content', null);

        expect($result->passed)->toBeTrue();
    });

    test('passes when blockOutputPii is false', function (): void {
        $result = (new OutputPiiGuardRail(new ZeroTokenTransformer))
            ->check('content', new AegisConfig(blockOutputPii: false));

        expect($result->passed)->toBeTrue();
    });

    test('passes when piiRules is empty', function (): void {
        $result = (new OutputPiiGuardRail(new ZeroTokenTransformer))
            ->check('content', new AegisConfig(blockOutputPii: true, piiRules: []));

        expect($result->passed)->toBeTrue();
    });

    test('fails when PII is detected in the output', function (): void {
        $rule = new PiiRuleConfig(type: 'email', action: PiiAction::Tokenize);
        $config = new AegisConfig(blockOutputPii: true, piiRules: [$rule]);

        $result = (new OutputPiiGuardRail(new OneTokenTransformer))->check('user@example.com', $config);

        expect($result->passed)->toBeFalse()
            ->and($result->reason)->toContain('email');
    });

    test('passes when no PII is detected in the output', function (): void {
        $rule = new PiiRuleConfig(type: 'email', action: PiiAction::Tokenize);
        $config = new AegisConfig(blockOutputPii: true, piiRules: [$rule]);

        $result = (new OutputPiiGuardRail(new ZeroTokenTransformer))->check('Hello there.', $config);

        expect($result->passed)->toBeTrue();
    });
});

describe('ToolGuardRail', function (): void {
    test('stage returns Tool', function (): void {
        expect((new ToolGuardRail)->stage())->toBe(GuardRailStage::Tool);
    });

    test('passes when context is not AegisConfig', function (): void {
        $result = (new ToolGuardRail)->check('some_tool', null);

        expect($result->passed)->toBeTrue();
    });

    test('passes when neither allowed nor blocked lists are configured', function (): void {
        $result = (new ToolGuardRail)->check('any_tool', new AegisConfig(allowedTools: [], blockedTools: []));

        expect($result->passed)->toBeTrue();
    });

    test('fails when tool is in the blocked list', function (): void {
        $result = (new ToolGuardRail)->check('dangerous_tool', new AegisConfig(blockedTools: ['dangerous_tool']));

        expect($result->passed)->toBeFalse()
            ->and($result->reason)->toContain('dangerous_tool');
    });

    test('fails when tool is not in the allowed list', function (): void {
        $result = (new ToolGuardRail)->check('unknown_tool', new AegisConfig(allowedTools: ['safe_tool']));

        expect($result->passed)->toBeFalse()
            ->and($result->reason)->toContain('not in the allowed-tools list');
    });

    test('passes when tool is in the allowed list', function (): void {
        $result = (new ToolGuardRail)->check('safe_tool', new AegisConfig(allowedTools: ['safe_tool', 'other_tool']));

        expect($result->passed)->toBeTrue();
    });
});

// --- Stubs ---

class AlwaysApproveHandler implements ApprovalHandlerInterface
{
    public function approve(string $content, mixed $context): bool
    {
        return true;
    }
}

class AlwaysDenyHandler implements ApprovalHandlerInterface
{
    public function approve(string $content, mixed $context): bool
    {
        return false;
    }
}

class FixedScoreDetector implements InjectionDetectorInterface
{
    public function __construct(private float $score) {}

    public function evaluate(string $prompt): array
    {
        return ['is_malicious' => $this->score >= 0.5, 'score' => $this->score, 'matched_patterns' => []];
    }
}

class ZeroTokenTransformer implements PiiTransformerInterface
{
    public function transform(string $text, array $rules): TransformResult
    {
        return new TransformResult(text: $text, sessionId: 'test', tokenCount: 0);
    }

    public function restore(string $text, string $sessionId): string
    {
        return $text;
    }
}

class OneTokenTransformer implements PiiTransformerInterface
{
    public function transform(string $text, array $rules): TransformResult
    {
        return new TransformResult(text: '***', sessionId: 'test', tokenCount: 1);
    }

    public function restore(string $text, string $sessionId): string
    {
        return $text;
    }
}
