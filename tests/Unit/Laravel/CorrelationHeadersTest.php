<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Http\Request;
use LaravelNats\Support\CorrelationHeaders;

it('merges request id from current request when inject is enabled', function (): void {
    $this->app->instance('request', Request::create('/', 'GET', [], [], [], [
        'HTTP_X_REQUEST_ID' => 'req-from-http',
    ]));

    $config = new Repository([
        'nats_basis' => [
            'correlation' => [
                'inject_on_publish' => true,
                'generate_when_missing' => false,
                'request_id_header' => 'X-Request-Id',
                'correlation_id_header' => 'Nats-Correlation-Id',
            ],
        ],
    ]);

    $out = CorrelationHeaders::mergeForPublish($config, []);

    expect($out['X-Request-Id'] ?? null)->toBe('req-from-http');
});

it('does not override explicit publish headers', function (): void {
    $this->app->instance('request', Request::create('/', 'GET', [], [], [], [
        'HTTP_X_REQUEST_ID' => 'from-request',
    ]));

    $config = new Repository([
        'nats_basis' => [
            'correlation' => [
                'inject_on_publish' => true,
                'generate_when_missing' => false,
                'request_id_header' => 'X-Request-Id',
                'correlation_id_header' => 'Nats-Correlation-Id',
            ],
        ],
    ]);

    $out = CorrelationHeaders::mergeForPublish($config, ['x-request-id' => 'explicit']);

    expect($out['x-request-id'])->toBe('explicit');
});

it('is inactive when inject_on_publish is false', function (): void {
    $this->app->instance('request', Request::create('/', 'GET', [], [], [], [
        'HTTP_X_REQUEST_ID' => 'ignored',
    ]));

    $config = new Repository([
        'nats_basis' => [
            'correlation' => [
                'inject_on_publish' => false,
            ],
        ],
    ]);

    expect(CorrelationHeaders::mergeForPublish($config, []))->toBe([]);
});
