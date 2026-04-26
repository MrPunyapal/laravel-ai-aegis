<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Middleware;

use Closure;
use MrPunyapal\LaravelAiAegis\Contracts\PiiTransformerInterface;
use MrPunyapal\LaravelAiAegis\Contracts\RecorderInterface;
use MrPunyapal\LaravelAiAegis\Contracts\GuardRailOrchestratorInterface;
use MrPunyapal\LaravelAiAegis\Support\AegisConfigResolver;

final readonly class AegisMiddleware
{
    public function __construct(
        private PiiTransformerInterface $transformer,
        private GuardRailOrchestratorInterface $orchestrator,
        private AegisConfigResolver $resolver,
        private RecorderInterface $recorder,
    ) {}

    /**
     * Handle the agent prompt through the Aegis security pipeline.
     *
     * @param  Closure(object): object  $next
     */
    public function handle(object $prompt, Closure $next): mixed
    {
        $config = $this->resolver->resolve($prompt);

        $content = $prompt->content();

        // Stage 1 — Input guard rails (injection, max-length, blocked phrases)
        $this->orchestrator->runInput($content, $config, $config);

        // Stage 2 — Approval guard rail
        if ($config->requireApproval) {
            $this->orchestrator->runApproval($content, $config, $config);
        }

        // Stage 3 — Outbound PII transformation
        $sessionId = null;

        if ($config->piiEnabled && $config->piiRules !== []) {
            $result = $this->transformer->transform($content, $config->piiRules);
            $sessionId = $result->sessionId;

            if ($result->tokenCount > 0) {
                $this->recorder->recordPseudonymization($result->tokenCount);
            }

            $prompt = $prompt->withContent($result->text);
        }

        // Stage 4 — Forward to next middleware / LLM provider
        return $next($prompt)->then(function (object $response) use ($config, $sessionId): object {
            $responseContent = $response->content();

            // Stage 5 — Output guard rails (PII leakage, blocked phrases)
            $this->orchestrator->runOutput($responseContent, $config, $config);

            // Stage 6 — Inbound PII restore (tokenize only; replace/mask are irreversible)
            if ($sessionId !== null) {
                $responseContent = $this->transformer->restore($responseContent, $sessionId);
            }

            return $response->withContent($responseContent);
        });
    }
}

