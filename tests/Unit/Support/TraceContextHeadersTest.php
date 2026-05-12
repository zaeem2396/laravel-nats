<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use LaravelNats\Support\TraceContextHeaders;

it('validates W3C traceparent shape', function (): void {
    expect(TraceContextHeaders::isValidTraceParent('00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01'))->toBeTrue()
        ->and(TraceContextHeaders::isValidTraceParent('00-00000000000000000000000000000000-00f067aa0ba902b7-01'))->toBeFalse()
        ->and(TraceContextHeaders::isValidTraceParent('00-4bf92f3577b34da6a3ce929d0e0e4736-0000000000000000-01'))->toBeFalse()
        ->and(TraceContextHeaders::isValidTraceParent('not-a-traceparent'))->toBeFalse();
});

it('does not inject when disabled', function (): void {
    $config = new Repository([
        'nats_basis' => [
            'trace_context' => [
                'inject_on_publish' => false,
            ],
        ],
    ]);

    expect(TraceContextHeaders::mergeForPublish($config, []))->toBe([]);
});
