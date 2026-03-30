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
