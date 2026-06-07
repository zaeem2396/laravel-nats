<?php

declare(strict_types=1);

use LaravelNats\Observability\NullNatsMetrics;

it('implements metrics contract as no-op', function (): void {
    $metrics = new NullNatsMetrics;

    $metrics->incrementCounter('nats.publish', ['conn' => 'default']);
    $metrics->observeHistogram('nats.publish.ms', 12.5, ['conn' => 'default']);

    expect(true)->toBeTrue();
});
