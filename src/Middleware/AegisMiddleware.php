<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Middleware;

use Closure;
use MrPunyapal\LaravelAiAegis\Attributes\Aegis;
use MrPunyapal\LaravelAiAegis\Contracts\InjectionDetectorInterface;
use MrPunyapal\LaravelAiAegis\Contracts\PiiDetectorInterface;
use MrPunyapal\LaravelAiAegis\Exceptions\AegisSecurityException;
use MrPunyapal\LaravelAiAegis\Pulse\AegisRecorder;
use ReflectionClass;

final class AegisMiddleware
{
    public function __construct(
        private readonly PiiDetectorInterface $piiDetector,
        private readonly InjectionDetectorInterface $injectionDetector,
        private readonly AegisRecorder $recorder,
    ) {}

    /**
     * Handle the agent prompt through the Aegis security pipeline.
     *
     * Intercepts the AgentPrompt before it reaches the LLM provider,
     * then uses the ->then() closure to process the AgentResponse.
     *
     * @param  Closure(object): object  $next
     */
    public function handle(object $prompt, Closure $next): mixed
    {
        $config = $this->resolveConfiguration($prompt);
        $content = $prompt->content();

        // Phase 1: Localized Prompt Injection Defense
        if ($config->blockInjections) {
            $this->detectInjection($content, $config->strictMode);
        }

        // Phase 2: Bidirectional Pseudonymization (outbound)
        $sessionId = null;

        if ($config->pseudonymize) {
            $result = $this->piiDetector->pseudonymize($content, $config->piiTypes);
            $sessionId = $result['session_id'];
            $prompt = $prompt->withContent($result['text']);
            $this->recorder->recordPseudonymization();
        }

        // Phase 3: Forward to next middleware / LLM provider
        return $next($prompt)->then(function (object $response) use ($sessionId): object {
            // Phase 4: Reverse Pseudonymization (inbound)
            if ($sessionId !== null) {
                $restoredContent = $this->piiDetector->depseudonymize(
                    $response->content(),
                    $sessionId,
                );

                return $response->withContent($restoredContent);
            }

            return $response;
        });
    }

    /**
     * Evaluate prompt against injection attack vectors and block if threshold exceeded.
     */
    private function detectInjection(string $content, bool $strictMode): void
    {
        $result = $this->injectionDetector->evaluate($content);
        $threshold = $strictMode
            ? 0.3
            : (float) config('aegis.injection_threshold', 0.7);

        if ($result['score'] >= $threshold) {
            $this->recorder->recordBlockedInjection($result['score']);
            $this->recorder->recordComputeSaved(0.03);

            throw AegisSecurityException::promptInjectionDetected($result['score']);
        }
    }

    /**
     * Resolve Aegis configuration from the agent class attribute or fallback to config.
     */
    private function resolveConfiguration(object $prompt): Aegis
    {
        $agentClass = method_exists($prompt, 'agentClass')
            ? $prompt->agentClass()
            : null;

        if ($agentClass !== null && class_exists($agentClass)) {
            $reflection = new ReflectionClass($agentClass);
            $attributes = $reflection->getAttributes(Aegis::class);

            if ($attributes !== []) {
                return $attributes[0]->newInstance();
            }
        }

        return new Aegis(
            blockInjections: (bool) config('aegis.block_injections', true),
            pseudonymize: (bool) config('aegis.pseudonymize', true),
            strictMode: (bool) config('aegis.strict_mode', false),
            piiTypes: (array) config('aegis.pii_types', ['email', 'phone', 'ssn', 'credit_card', 'ip_address']),
        );
    }
}
