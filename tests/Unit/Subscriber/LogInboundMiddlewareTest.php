<?php

declare(strict_types=1);

use LaravelNats\Subscriber\InboundMessage;
use LaravelNats\Subscriber\Middleware\LogInboundMiddleware;
use Psr\Log\LoggerInterface;

it('logs subject and invokes next handler', function (): void {
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('debug')
        ->once()
        ->with('NATS v2 inbound message', Mockery::on(function (array $context): bool {
            return $context['subject'] === 'events.test' && $context['reply_to'] === 'inbox.1';
        }));

    $ran = false;
    $message = new InboundMessage('events.test', '{}', [], 'inbox.1');

    (new LogInboundMiddleware($logger))->handle($message, function () use (&$ran): void {
        $ran = true;
    });

    expect($ran)->toBeTrue();
});
