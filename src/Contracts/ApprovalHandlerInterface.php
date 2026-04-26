<?php

declare(strict_types=1);

namespace MrPunyapal\LaravelAiAegis\Contracts;

interface ApprovalHandlerInterface
{
    /**
     * Called when a prompt requires human approval before proceeding.
     * Return true to allow, false to deny.
     */
    public function approve(string $content, mixed $context): bool;
}
