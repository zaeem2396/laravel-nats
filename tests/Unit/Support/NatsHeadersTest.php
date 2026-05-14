<?php

declare(strict_types=1);

use LaravelNats\Support\NatsHeaders;

it('finds headers case-insensitively', function (): void {
    $headers = ['TraceParent' => 'abc'];

    expect(NatsHeaders::has($headers, 'traceparent'))->toBeTrue()
        ->and(NatsHeaders::get($headers, 'TRACEPARENT'))->toBe('abc');
});

it('adds values only when missing', function (): void {
    $headers = ['X-Request-Id' => 'existing'];

    expect(NatsHeaders::putIfMissing($headers, 'x-request-id', 'new'))->toBe($headers)
        ->and(NatsHeaders::putIfMissing([], 'traceparent', '00-abc-def-01'))->toBe([
            'traceparent' => '00-abc-def-01',
        ]);
});

it('ignores empty names and empty values when adding', function (): void {
    expect(NatsHeaders::putIfMissing([], '', 'x'))->toBe([])
        ->and(NatsHeaders::putIfMissing([], 'traceparent', ''))->toBe([])
        ->and(NatsHeaders::putIfMissing([], 'traceparent', null))->toBe([]);
});
