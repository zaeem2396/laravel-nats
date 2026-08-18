<?php

declare(strict_types=1);

use LaravelNats\Support\MixedTypes;

it('narrows mixed strings including numeric scalars', function (): void {
    expect(MixedTypes::string('host'))->toBe('host')
        ->and(MixedTypes::string(4222))->toBe('4222')
        ->and(MixedTypes::string(1.5))->toBe('1.5')
        ->and(MixedTypes::string(true))->toBe('1')
        ->and(MixedTypes::string(null, 'fallback'))->toBe('fallback')
        ->and(MixedTypes::string([], 'fallback'))->toBe('fallback');
});

it('returns null for empty or non-scalar nullable strings', function (): void {
    expect(MixedTypes::nullableString(null))->toBeNull()
        ->and(MixedTypes::nullableString(''))->toBeNull()
        ->and(MixedTypes::nullableString('nats'))->toBe('nats')
        ->and(MixedTypes::nullableString(8))->toBe('8')
        ->and(MixedTypes::nullableString([]))->toBeNull();
});

it('narrows mixed integers and floats', function (): void {
    expect(MixedTypes::int(60))->toBe(60)
        ->and(MixedTypes::int('60'))->toBe(60)
        ->and(MixedTypes::int(1.9))->toBe(1)
        ->and(MixedTypes::int('nope', 7))->toBe(7)
        ->and(MixedTypes::nullableInt(null))->toBeNull()
        ->and(MixedTypes::nullableInt('12'))->toBe(12)
        ->and(MixedTypes::float(1))->toBe(1.0)
        ->and(MixedTypes::float('0.5'))->toBe(0.5)
        ->and(MixedTypes::float('x', 2.5))->toBe(2.5);
});

it('narrows mixed booleans like PHP casts for scalars', function (): void {
    expect(MixedTypes::bool(true))->toBeTrue()
        ->and(MixedTypes::bool(0))->toBeFalse()
        ->and(MixedTypes::bool('0'))->toBeFalse()
        ->and(MixedTypes::bool('false'))->toBeTrue()
        ->and(MixedTypes::bool([]))->toBeFalse()
        ->and(MixedTypes::bool(['x']))->toBeTrue();
});

it('normalizes mixed arrays into assoc maps and lists', function (): void {
    expect(MixedTypes::assoc(['host' => '127.0.0.1', 0 => 'x']))
        ->toBe(['host' => '127.0.0.1', '0' => 'x'])
        ->and(MixedTypes::assoc('nope'))->toBe([])
        ->and(MixedTypes::list(['a', 'b']))->toBe(['a', 'b'])
        ->and(MixedTypes::stringList(['orders', 2, null]))->toBe(['orders', '2'])
        ->and(MixedTypes::intList(['1', 2, 'x', null]))->toBe([1, 2]);
});
