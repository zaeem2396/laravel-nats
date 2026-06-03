<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use LaravelNats\Subscriber\InboundMessage;
use LaravelNats\Subscriber\Middleware\CorrelationLogInboundMiddleware;

it('shares correlation context with the log channel when supported', function (): void {
    $message = new InboundMessage('events.test', '{}', [
        'Nats-Correlation-Id' => 'corr-123',
        'Nats-Request-Id' => 'req-456',
    ], null);

    $ran = false;
    (new CorrelationLogInboundMiddleware($this->app))->handle($message, function () use (&$ran): void {
        $ran = true;
    });

    expect($ran)->toBeTrue();
});

it('invokes handler when no correlation headers are present', function (): void {
    Log::spy();

    $message = new InboundMessage('events.test', '{}', [], null);
    $ran = false;

    (new CorrelationLogInboundMiddleware($this->app))->handle($message, function () use (&$ran): void {
        $ran = true;
    });

    expect($ran)->toBeTrue();
});
