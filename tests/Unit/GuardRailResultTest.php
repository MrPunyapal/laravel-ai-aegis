<?php

declare(strict_types=1);

use MrPunyapal\LaravelAiAegis\Data\GuardRailResult;

test('pass creates a passing result with null reason and stage', function (): void {
    $result = GuardRailResult::pass();

    expect($result->passed)->toBeTrue()
        ->and($result->reason)->toBeNull()
        ->and($result->stage)->toBeNull();
});

test('fail creates a failing result with the given reason and stage', function (): void {
    $result = GuardRailResult::fail('Blocked phrase detected.', 'input');

    expect($result->passed)->toBeFalse()
        ->and($result->reason)->toBe('Blocked phrase detected.')
        ->and($result->stage)->toBe('input');
});
