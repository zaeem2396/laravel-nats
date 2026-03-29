<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Http\Request;
use LaravelNats\Observability\CorrelationLogContext;

it('extracts request and correlation ids for logging context', function (): void {
    $request = Request::create('/', 'GET', [], [], [], [
        'HTTP_X_REQUEST_ID' => 'rid-1',
        'HTTP_X_CORRELATION_ID' => 'cid-1',
    ]);

    $config = new Repository([
        'nats_basis' => [
            'correlation' => [
                'request_id_header' => 'X-Request-Id',
                'correlation_id_header' => 'Nats-Correlation-Id',
            ],
        ],
    ]);

    $ctx = CorrelationLogContext::fromRequest($request, $config);

    expect($ctx['nats_request_id'] ?? null)->toBe('rid-1')
        ->and($ctx['nats_correlation_id'] ?? null)->toBe('cid-1');
});

it('reads configured correlation header name from request', function (): void {
    $request = Request::create('/', 'GET', [], [], [], [
        'HTTP_NATS_CORRELATION_ID' => 'nats-cid',
    ]);

    $config = new Repository([
        'nats_basis' => [
            'correlation' => [
                'request_id_header' => 'X-Request-Id',
                'correlation_id_header' => 'Nats-Correlation-Id',
            ],
        ],
    ]);

    $ctx = CorrelationLogContext::fromRequest($request, $config);

    expect($ctx)->toHaveKey('nats_correlation_id')
        ->and($ctx['nats_correlation_id'])->toBe('nats-cid');
});
