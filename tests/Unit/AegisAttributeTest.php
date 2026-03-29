<?php

declare(strict_types=1);

use MrPunyapal\LaravelAiAegis\Attributes\Aegis;

it('has sensible defaults', function (): void {
    $aegis = new Aegis;

    expect($aegis->blockInjections)->toBeTrue()
        ->and($aegis->pseudonymize)->toBeTrue()
        ->and($aegis->strictMode)->toBeFalse()
        ->and($aegis->piiTypes)->toBe(['email', 'phone', 'ssn', 'credit_card', 'ip_address']);
});

it('accepts custom configuration', function (): void {
    $aegis = new Aegis(
        blockInjections: false,
        pseudonymize: true,
        strictMode: true,
        piiTypes: ['email'],
    );

    expect($aegis->blockInjections)->toBeFalse()
        ->and($aegis->strictMode)->toBeTrue()
        ->and($aegis->piiTypes)->toBe(['email']);
});

it('can be read from class attributes via reflection', function (): void {
    $reflection = new ReflectionClass(AegisAnnotatedStub::class);
    $attributes = $reflection->getAttributes(Aegis::class);

    expect($attributes)->toHaveCount(1);

    $instance = $attributes[0]->newInstance();

    expect($instance->blockInjections)->toBeTrue()
        ->and($instance->strictMode)->toBeTrue()
        ->and($instance->pseudonymize)->toBeFalse();
});

it('is a readonly class', function (): void {
    $reflection = new ReflectionClass(Aegis::class);

    expect($reflection->isReadOnly())->toBeTrue();
});

#[Aegis(blockInjections: true, pseudonymize: false, strictMode: true)]
class AegisAnnotatedStub {}
