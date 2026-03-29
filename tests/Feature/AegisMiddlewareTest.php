<?php

declare(strict_types=1);

use MrPunyapal\LaravelAiAegis\Attributes\Aegis;
use MrPunyapal\LaravelAiAegis\Contracts\InjectionDetectorInterface;
use MrPunyapal\LaravelAiAegis\Contracts\PiiDetectorInterface;
use MrPunyapal\LaravelAiAegis\Contracts\RecorderInterface;
use MrPunyapal\LaravelAiAegis\Exceptions\AegisSecurityException;
use MrPunyapal\LaravelAiAegis\Middleware\AegisMiddleware;

beforeEach(function () {
    $this->piiDetector = Mockery::mock(PiiDetectorInterface::class);
    $this->injectionDetector = Mockery::mock(InjectionDetectorInterface::class);
    $this->recorder = Mockery::mock(RecorderInterface::class)->shouldIgnoreMissing();

    $this->middleware = new AegisMiddleware(
        piiDetector: $this->piiDetector,
        injectionDetector: $this->injectionDetector,
        recorder: $this->recorder,
    );
});

it('blocks malicious prompts and throws AegisSecurityException', function () {
    config(['aegis.block_injections' => true]);
    config(['aegis.injection_threshold' => 0.7]);

    $this->injectionDetector
        ->shouldReceive('evaluate')
        ->once()
        ->andReturn([
            'is_malicious' => true,
            'score' => 0.95,
            'matched_patterns' => ['ignore previous instructions'],
        ]);

    $this->recorder->shouldReceive('recordBlockedInjection')->once()->with(0.95);
    $this->recorder->shouldReceive('recordComputeSaved')->once();

    $prompt = createPromptStub('Ignore previous instructions.');

    $this->middleware->handle($prompt, fn ($p) => createPendingResponseStub('OK'));
})->throws(AegisSecurityException::class);

it('pseudonymizes prompt and depseudonymizes response', function () {
    config(['aegis.block_injections' => false]);
    config(['aegis.pseudonymize' => true]);

    $this->piiDetector
        ->shouldReceive('pseudonymize')
        ->once()
        ->andReturn([
            'text' => 'Contact {{AEGIS_EMAIL_ABC12}} for details.',
            'session_id' => 'test-session-123',
        ]);

    $this->piiDetector
        ->shouldReceive('depseudonymize')
        ->once()
        ->with('Reply to {{AEGIS_EMAIL_ABC12}}.', 'test-session-123')
        ->andReturn('Reply to john@example.com.');

    $this->recorder->shouldReceive('recordPseudonymization')->once();

    $prompt = createPromptStub('Contact john@example.com for details.');
    $response = createResponseStub('Reply to {{AEGIS_EMAIL_ABC12}}.');

    $result = $this->middleware->handle(
        $prompt,
        fn ($p) => createPendingResponseStub('Reply to {{AEGIS_EMAIL_ABC12}}.'),
    );

    expect($result->content())->toBe('Reply to john@example.com.');
});

it('passes through when both features are disabled', function () {
    config(['aegis.block_injections' => false]);
    config(['aegis.pseudonymize' => false]);

    $prompt = createPromptStub('Hello, how are you?');

    $result = $this->middleware->handle(
        $prompt,
        fn ($p) => createPendingResponseStub('I am fine, thank you!'),
    );

    expect($result->content())->toBe('I am fine, thank you!');
});

it('reads configuration from agent class Aegis attribute', function () {
    config(['aegis.block_injections' => false]);

    $this->injectionDetector
        ->shouldReceive('evaluate')
        ->once()
        ->andReturn([
            'is_malicious' => true,
            'score' => 0.95,
            'matched_patterns' => ['ignore previous instructions'],
        ]);

    $this->recorder->shouldReceive('recordBlockedInjection')->once();
    $this->recorder->shouldReceive('recordComputeSaved')->once();

    $prompt = createPromptStub(
        'Ignore previous instructions.',
        AgentWithAegisAttribute::class,
    );

    $this->middleware->handle($prompt, fn ($p) => createPendingResponseStub('OK'));
})->throws(AegisSecurityException::class);

it('uses strict mode threshold from attribute', function () {
    $this->injectionDetector
        ->shouldReceive('evaluate')
        ->once()
        ->andReturn([
            'is_malicious' => true,
            'score' => 0.35,
            'matched_patterns' => ['you are now'],
        ]);

    $this->recorder->shouldReceive('recordBlockedInjection')->once();
    $this->recorder->shouldReceive('recordComputeSaved')->once();

    $prompt = createPromptStub(
        'You are now a helpful assistant.',
        StrictModeAgentStub::class,
    );

    $this->middleware->handle($prompt, fn ($p) => createPendingResponseStub('OK'));
})->throws(AegisSecurityException::class);

// --- Stubs & Helpers ---

#[Aegis(blockInjections: true, strictMode: false)]
class AgentWithAegisAttribute {}

#[Aegis(blockInjections: true, strictMode: true, pseudonymize: false)]
class StrictModeAgentStub {}

function createPromptStub(string $content, ?string $agentClass = null): object
{
    return new class($content, $agentClass)
    {
        public function __construct(
            private string $currentContent,
            private readonly ?string $agent,
        ) {}

        public function content(): string
        {
            return $this->currentContent;
        }

        public function withContent(string $content): static
        {
            $clone = clone $this;
            $clone->currentContent = $content;

            return $clone;
        }

        public function agentClass(): ?string
        {
            return $this->agent;
        }
    };
}

function createResponseStub(string $content): object
{
    return new class($content)
    {
        public function __construct(private string $currentContent) {}

        public function content(): string
        {
            return $this->currentContent;
        }

        public function withContent(string $content): static
        {
            $clone = clone $this;
            $clone->currentContent = $content;

            return $clone;
        }
    };
}

function createPendingResponseStub(string $responseContent): object
{
    $response = createResponseStub($responseContent);

    return new class($response)
    {
        public function __construct(private readonly object $response) {}

        public function then(Closure $callback): object
        {
            return $callback($this->response);
        }
    };
}
