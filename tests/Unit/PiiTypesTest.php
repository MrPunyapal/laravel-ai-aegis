<?php

declare(strict_types=1);

use MrPunyapal\LaravelAiAegis\Pii\Types\AddressType;
use MrPunyapal\LaravelAiAegis\Pii\Types\ApiKeyType;
use MrPunyapal\LaravelAiAegis\Pii\Types\BankAccountType;
use MrPunyapal\LaravelAiAegis\Pii\Types\DateOfBirthType;
use MrPunyapal\LaravelAiAegis\Pii\Types\JwtType;
use MrPunyapal\LaravelAiAegis\Pii\Types\NameType;
use MrPunyapal\LaravelAiAegis\Pii\Types\UrlType;

test('AddressType has correct type key and a valid pattern', function (): void {
    $type = new AddressType;

    expect($type->type())->toBe('address')
        ->and($type->pattern())->toBeString()->not->toBeEmpty();
});

test('ApiKeyType has correct type key and a valid pattern', function (): void {
    $type = new ApiKeyType;

    expect($type->type())->toBe('api_key')
        ->and($type->pattern())->toBeString()->not->toBeEmpty();
});

test('BankAccountType has correct type key and a valid pattern', function (): void {
    $type = new BankAccountType;

    expect($type->type())->toBe('bank_account')
        ->and($type->pattern())->toBeString()->not->toBeEmpty();
});

test('DateOfBirthType has correct type key and a valid pattern', function (): void {
    $type = new DateOfBirthType;

    expect($type->type())->toBe('date_of_birth')
        ->and($type->pattern())->toBeString()->not->toBeEmpty();
});

test('JwtType has correct type key and a valid pattern', function (): void {
    $type = new JwtType;

    expect($type->type())->toBe('jwt')
        ->and($type->pattern())->toBeString()->not->toBeEmpty();
});

test('NameType has correct type key and a valid pattern', function (): void {
    $type = new NameType;

    expect($type->type())->toBe('name')
        ->and($type->pattern())->toBeString()->not->toBeEmpty();
});

test('UrlType has correct type key and a valid pattern', function (): void {
    $type = new UrlType;

    expect($type->type())->toBe('url')
        ->and($type->pattern())->toBeString()->not->toBeEmpty();
});
