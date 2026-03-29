<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use LaravelNats\Subscriber\InboundMessage;
use LaravelNats\Subscriber\Middleware\RedactedEnvelopeLogInboundMiddleware;
use Psr\Log\LoggerInterface;

it('logs redacted envelope data at debug', function (): void {
    $config = new Repository([
        'nats_basis' => [
            'observability' => [
                'redact_key_substrings' => ['token'],
            ],
        ],
    ]);

    $logger = \Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('debug')
        ->once()
        ->withArgs(function (string $msg, array $context): bool {
            return $msg === 'NATS v2 inbound envelope'
                && ($context['data']['api_token'] ?? null) === '[REDACTED]'
                && ($context['data']['order_id'] ?? null) === 1;
        });

    $middleware = new RedactedEnvelopeLogInboundMiddleware($logger, $config);

    $body = json_encode([
        'id' => 'uuid',
        'type' => 'orders.created',
        'version' => 'v1',
        'data' => ['order_id' => 1, 'api_token' => 'secret'],
    ], JSON_THROW_ON_ERROR);

    $message = new InboundMessage('orders.created', $body, [], null);

    $invoked = false;
    $middleware->handle($message, function () use (&$invoked): void {
        $invoked = true;
    });

    expect($invoked)->toBeTrue();
});

it('skips logging when body is not a v2 envelope', function (): void {
    $config = new Repository([
        'nats_basis' => [
            'observability' => [
                'redact_key_substrings' => [],
            ],
        ],
    ]);

    $logger = \Mockery::mock(LoggerInterface::class);
    $logger->shouldNotReceive('debug');

    $middleware = new RedactedEnvelopeLogInboundMiddleware($logger, $config);

    $message = new InboundMessage('raw', '{"foo":1}', [], null);

    $called = false;
    $middleware->handle($message, function () use (&$called): void {
        $called = true;
    });

    expect($called)->toBeTrue();
});
