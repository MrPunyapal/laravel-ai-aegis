<?php

declare(strict_types=1);

use MrPunyapal\LaravelAiAegis\Enums\PiiAction;
use MrPunyapal\LaravelAiAegis\Pii\PiiRuleParser;
use MrPunyapal\LaravelAiAegis\Pii\PiiTypeRegistry;
use MrPunyapal\LaravelAiAegis\Pii\Types\EmailType;

beforeEach(function (): void {
    $registry = new PiiTypeRegistry;
    $registry->register(new EmailType);
    $this->parser = new PiiRuleParser($registry);
});

describe('string DSL', function (): void {
    test('throws InvalidArgumentException for an unknown PII type', function (): void {
        expect(fn () => $this->parser->parse('unknown_type'))
            ->toThrow(InvalidArgumentException::class, 'Unknown PII type: "unknown_type"');
    });
});

describe('array form', function (): void {
    test('throws when the type key is missing', function (): void {
        expect(fn () => $this->parser->parse(['action' => 'tokenize']))
            ->toThrow(InvalidArgumentException::class, '"type" string key');
    });

    test('throws when type value is not a string', function (): void {
        expect(fn () => $this->parser->parse(['type' => 123]))
            ->toThrow(InvalidArgumentException::class, '"type" string key');
    });

    test('throws when type is unknown', function (): void {
        expect(fn () => $this->parser->parse(['type' => 'unknown']))
            ->toThrow(InvalidArgumentException::class, 'Unknown PII type: "unknown"');
    });

    test('defaults to tokenize when action is omitted', function (): void {
        $rule = $this->parser->parse(['type' => 'email']);

        expect($rule->type)->toBe('email')
            ->and($rule->action)->toBe(PiiAction::Tokenize);
    });

    test('parses an explicit action from an array', function (): void {
        $rule = $this->parser->parse(['type' => 'email', 'action' => 'replace']);

        expect($rule->action)->toBe(PiiAction::Replace);
    });

    test('parses mask_start and mask_end from an array', function (): void {
        $rule = $this->parser->parse([
            'type' => 'email',
            'action' => 'mask',
            'mask_start' => 3,
            'mask_end' => 4,
        ]);

        expect($rule->action)->toBe(PiiAction::Mask)
            ->and($rule->maskStart)->toBe(3)
            ->and($rule->maskEnd)->toBe(4);
    });

    test('parses replacement text from an array', function (): void {
        $rule = $this->parser->parse([
            'type' => 'email',
            'action' => 'replace',
            'replacement' => '[HIDDEN]',
        ]);

        expect($rule->action)->toBe(PiiAction::Replace)
            ->and($rule->replacement)->toBe('[HIDDEN]');
    });

    test('clamps negative mask values to zero', function (): void {
        $rule = $this->parser->parse([
            'type' => 'email',
            'action' => 'mask',
            'mask_start' => -5,
            'mask_end' => -3,
        ]);

        expect($rule->maskStart)->toBe(0)
            ->and($rule->maskEnd)->toBe(0);
    });
});
