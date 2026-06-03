<?php

declare(strict_types=1);

use LaravelNats\Subscriber\InboundMessage;
use LaravelNats\Subscriber\Middleware\InboundMiddleware;
use LaravelNats\Subscriber\Middleware\InboundMiddlewarePipeline;

it('runs middleware in order before the handler', function (): void {
    $order = [];
    $message = new InboundMessage('test.sub', '{}', [], null);

    $first = new class($order) implements InboundMiddleware
    {
        public function __construct(private array &$order) {}

        public function handle(InboundMessage $message, Closure $next): void
        {
            $this->order[] = 'first';
            $next();
        }
    };

    $second = new class($order) implements InboundMiddleware
    {
        public function __construct(private array &$order) {}

        public function handle(InboundMessage $message, Closure $next): void
        {
            $this->order[] = 'second';
            $next();
        }
    };

    (new InboundMiddlewarePipeline([$first, $second]))->dispatch($message, function () use (&$order): void {
        $order[] = 'handler';
    });

    expect($order)->toBe(['first', 'second', 'handler']);
});

it('runs handler when middleware list is empty', function (): void {
    $ran = false;
    $message = new InboundMessage('test.sub', '{}', [], null);

    (new InboundMiddlewarePipeline([]))->dispatch($message, function () use (&$ran): void {
        $ran = true;
    });

    expect($ran)->toBeTrue();
});
