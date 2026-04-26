<?php

declare(strict_types=1);

use MrPunyapal\LaravelAiAegis\Attributes\Aegis;

it('has sensible defaults', function (): void {
    $aegis = new Aegis;

    expect($aegis->piiEnabled)->toBeTrue()
        ->and($aegis->piiRules)->toBeEmpty()
        ->and($aegis->blockInjections)->toBeTrue()
        ->and($aegis->strictMode)->toBeFalse()
        ->and($aegis->blockOutputPii)->toBeTrue()
        ->and($aegis->requireApproval)->toBeFalse()
        ->and($aegis->approvalHandler)->toBeNull();
});

it('accepts custom pii rules as DSL strings', function (): void {
    $aegis = new Aegis(piiRules: ['email:mask,3,5', 'phone:replace']);

    expect($aegis->piiRules)->toBe(['email:mask,3,5', 'phone:replace']);
});

it('accepts guard rail settings', function (): void {
    $aegis = new Aegis(
        blockInjections: false,
        strictMode: true,
        injectionThreshold: 0.4,
        inputBlockedPhrases: ['bad word'],
        maxInputLength: 500,
        blockOutputPii: false,
        allowedTools: ['weather_tool'],
        blockedTools: ['dangerous_tool'],
        requireApproval: true,
        approvalHandler: 'App\\MyApprovalHandler',
    );

    expect($aegis->blockInjections)->toBeFalse()
        ->and($aegis->strictMode)->toBeTrue()
        ->and($aegis->injectionThreshold)->toBe(0.4)
        ->and($aegis->inputBlockedPhrases)->toBe(['bad word'])
        ->and($aegis->maxInputLength)->toBe(500)
        ->and($aegis->blockOutputPii)->toBeFalse()
        ->and($aegis->allowedTools)->toBe(['weather_tool'])
        ->and($aegis->blockedTools)->toBe(['dangerous_tool'])
        ->and($aegis->requireApproval)->toBeTrue()
        ->and($aegis->approvalHandler)->toBe('App\\MyApprovalHandler');
});

it('can be read from class attributes via reflection', function (): void {
    $reflection = new ReflectionClass(AegisAnnotatedStub::class);
    $attributes = $reflection->getAttributes(Aegis::class);

    expect($attributes)->toHaveCount(1);

    $instance = $attributes[0]->newInstance();

    expect($instance->blockInjections)->toBeTrue()
        ->and($instance->strictMode)->toBeTrue()
        ->and($instance->piiEnabled)->toBeFalse();
});

it('is a readonly class', function (): void {
    $reflection = new ReflectionClass(Aegis::class);

    expect($reflection->isReadOnly())->toBeTrue();
});

#[Aegis(piiEnabled: false, blockInjections: true, strictMode: true)]
class AegisAnnotatedStub {}
