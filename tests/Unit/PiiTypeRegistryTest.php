<?php

declare(strict_types=1);

use MrPunyapal\LaravelAiAegis\Pii\PiiTypeRegistry;
use MrPunyapal\LaravelAiAegis\Pii\Types\EmailType;
use MrPunyapal\LaravelAiAegis\Pii\Types\PhoneType;

test('get throws InvalidArgumentException for an unknown type', function (): void {
    $registry = new PiiTypeRegistry;

    expect(fn () => $registry->get('unknown_type'))
        ->toThrow(InvalidArgumentException::class, 'Unknown PII type: "unknown_type"');
});

test('all returns all registered types indexed by their type key', function (): void {
    $registry = new PiiTypeRegistry;
    $registry->register(new EmailType);
    $registry->register(new PhoneType);

    $all = $registry->all();

    expect($all)->toHaveCount(2)
        ->and($all)->toHaveKey('email')
        ->and($all)->toHaveKey('phone');
});
