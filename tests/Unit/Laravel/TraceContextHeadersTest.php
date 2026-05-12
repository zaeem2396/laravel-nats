<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Http\Request;
use LaravelNats\Support\TraceContextHeaders;

it('merges trace context from the current request when enabled', function (): void {
    $this->app->instance('request', Request::create('/', 'GET', [], [], [], [
        'HTTP_TRACEPARENT' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01',
        'HTTP_TRACESTATE' => 'rojo=00f067aa0ba902b7',
    ]));

    $config = new Repository([
        'nats_basis' => [
            'trace_context' => [
                'inject_on_publish' => true,
                'traceparent_header' => 'traceparent',
                'tracestate_header' => 'tracestate',
            ],
        ],
    ]);

    $out = TraceContextHeaders::mergeForPublish($config, []);

    expect($out['traceparent'] ?? null)->toBe('00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01')
        ->and($out['tracestate'] ?? null)->toBe('rojo=00f067aa0ba902b7');
});

it('preserves explicit trace context headers', function (): void {
    $this->app->instance('request', Request::create('/', 'GET', [], [], [], [
        'HTTP_TRACEPARENT' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01',
    ]));

    $config = new Repository([
        'nats_basis' => [
            'trace_context' => [
                'inject_on_publish' => true,
            ],
        ],
    ]);

    $out = TraceContextHeaders::mergeForPublish($config, ['TraceParent' => 'explicit']);

    expect($out['TraceParent'])->toBe('explicit');
});
