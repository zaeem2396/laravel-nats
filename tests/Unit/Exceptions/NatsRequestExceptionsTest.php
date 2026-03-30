<?php

declare(strict_types=1);

use LaravelNats\Exceptions\NatsNoRespondersException;
use LaravelNats\Exceptions\NatsRequestTimeoutException;

it('builds no responders message', function (): void {
    $e = new NatsNoRespondersException('foo.bar');

    expect($e->subject)->toBe('foo.bar')
        ->and($e->getMessage())->toContain('foo.bar')
        ->and($e->getMessage())->toContain('503');
});

it('builds timeout message', function (): void {
    $e = new NatsRequestTimeoutException('x', 1.5);

    expect($e->subject)->toBe('x')
        ->and($e->timeoutSeconds)->toBe(1.5);
});
