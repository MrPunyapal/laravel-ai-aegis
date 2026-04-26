<?php

declare(strict_types=1);

use MrPunyapal\LaravelAiAegis\Attributes\Aegis;
use MrPunyapal\LaravelAiAegis\Contracts\ApprovalHandlerInterface;
use MrPunyapal\LaravelAiAegis\Data\AegisConfig;
use MrPunyapal\LaravelAiAegis\Support\AegisConfigResolver;

beforeEach(function (): void {
    $this->resolver = app(AegisConfigResolver::class);
});

function createConfigResolverPrompt(string $agentClass): object
{
    return new readonly class($agentClass)
    {
        public function __construct(private string $agent) {}

        public function agentClass(): string
        {
            return $this->agent;
        }
    };
}

it('fromAttribute uses attribute piiRules when non-empty', function (): void {
    $config = $this->resolver->resolve(createConfigResolverPrompt(ConfigResolverAgentWithPiiRules::class));

    expect($config->piiRules)->not->toBeEmpty();
});

it('fromAttribute resolves approvalHandler from attribute', function (): void {
    $config = $this->resolver->resolve(createConfigResolverPrompt(ConfigResolverAgentWithApprovalHandler::class));

    expect($config->approvalHandler)->toBeInstanceOf(ApprovalHandlerInterface::class);
});

it('fromAttribute uses inputBlockedPhrases from attribute when non-empty', function (): void {
    $config = $this->resolver->resolve(createConfigResolverPrompt(ConfigResolverAgentWithInputPhrases::class));

    expect($config->inputBlockedPhrases)->toContain('bad phrase');
});

it('fromAttribute uses outputBlockedPhrases from attribute when non-empty', function (): void {
    $config = $this->resolver->resolve(createConfigResolverPrompt(ConfigResolverAgentWithOutputPhrases::class));

    expect($config->outputBlockedPhrases)->toContain('secret');
});

it('fromAttribute uses allowedTools from attribute when non-empty', function (): void {
    $config = $this->resolver->resolve(createConfigResolverPrompt(ConfigResolverAgentWithAllowedTools::class));

    expect($config->allowedTools)->toContain('safe_tool');
});

it('fromAttribute uses blockedTools from attribute when non-empty', function (): void {
    $config = $this->resolver->resolve(createConfigResolverPrompt(ConfigResolverAgentWithBlockedTools::class));

    expect($config->blockedTools)->toContain('danger_tool');
});

it('fromAttribute uses config max_length when attribute maxInputLength is null', function (): void {
    config(['aegis.guard_rails.input.max_length' => 200]);

    $config = $this->resolver->resolve(createConfigResolverPrompt(ConfigResolverAgentNoMaxLength::class));

    expect($config->maxInputLength)->toBe(200);
});

it('fromConfig resolves approvalHandler from config', function (): void {
    config(['aegis.guard_rails.approval.handler' => ConfigResolverTestApprovalHandler::class]);
    config(['aegis.guard_rails.approval.enabled' => true]);

    $prompt = new class {};

    $config = $this->resolver->resolve($prompt);

    expect($config->approvalHandler)->toBeInstanceOf(ApprovalHandlerInterface::class);
});

it('fromConfig uses max_length when configured', function (): void {
    config(['aegis.guard_rails.input.max_length' => 500]);

    $prompt = new class {};

    $config = $this->resolver->resolve($prompt);

    expect($config->maxInputLength)->toBe(500);
});

it('resolveAttribute falls back to config when agent class has no Aegis attribute', function (): void {
    $config = $this->resolver->resolve(createConfigResolverPrompt(ConfigResolverAgentWithNoAttribute::class));

    expect($config)->toBeInstanceOf(AegisConfig::class);
});

// --- Stubs ---

#[Aegis(piiRules: ['email:tokenize'])]
class ConfigResolverAgentWithPiiRules {}

#[Aegis(piiEnabled: false, requireApproval: true, approvalHandler: ConfigResolverTestApprovalHandler::class)]
class ConfigResolverAgentWithApprovalHandler {}

#[Aegis(piiEnabled: false, inputBlockedPhrases: ['bad phrase'])]
class ConfigResolverAgentWithInputPhrases {}

#[Aegis(piiEnabled: false, outputBlockedPhrases: ['secret'])]
class ConfigResolverAgentWithOutputPhrases {}

#[Aegis(piiEnabled: false, allowedTools: ['safe_tool'])]
class ConfigResolverAgentWithAllowedTools {}

#[Aegis(piiEnabled: false, blockedTools: ['danger_tool'])]
class ConfigResolverAgentWithBlockedTools {}

#[Aegis(piiEnabled: false)]
class ConfigResolverAgentNoMaxLength {}

class ConfigResolverAgentWithNoAttribute {}

class ConfigResolverTestApprovalHandler implements ApprovalHandlerInterface
{
    public function approve(string $content, mixed $context): bool
    {
        return true;
    }
}
