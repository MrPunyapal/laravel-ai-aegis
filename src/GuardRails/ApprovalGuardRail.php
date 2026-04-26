<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\GuardRails;

use MrPunyapal\LaravelAiAegis\Contracts\ApprovalHandlerInterface;
use MrPunyapal\LaravelAiAegis\Contracts\GuardRailInterface;
use MrPunyapal\LaravelAiAegis\Data\AegisConfig;
use MrPunyapal\LaravelAiAegis\Data\GuardRailResult;
use MrPunyapal\LaravelAiAegis\Enums\GuardRailStage;

final readonly class ApprovalGuardRail implements GuardRailInterface
{
    public function stage(): GuardRailStage
    {
        return GuardRailStage::Approval;
    }

    public function check(string $content, mixed $context): GuardRailResult
    {
        if (! $context instanceof AegisConfig || ! $context->requireApproval) {
            return GuardRailResult::pass();
        }

        $handler = $context->approvalHandler;

        if (! $handler instanceof ApprovalHandlerInterface) {
            return GuardRailResult::fail(
                reason: 'Approval is required but no ApprovalHandlerInterface is configured.',
                stage: GuardRailStage::Approval->value,
            );
        }

        if (! $handler->approve($content, $context)) {
            return GuardRailResult::fail(
                reason: 'Request denied by approval handler.',
                stage: GuardRailStage::Approval->value,
            );
        }

        return GuardRailResult::pass();
    }
}
