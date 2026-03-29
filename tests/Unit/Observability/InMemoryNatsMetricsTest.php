<?php

declare(strict_types=1);

use LaravelNats\Observability\InMemoryNatsMetrics;

it('accumulates counters by name and labels', function (): void {
    $m = new InMemoryNatsMetrics();
    $m->incrementCounter('laravel_nats.publish.total', ['connection' => 'default', 'outcome' => 'success']);
    $m->incrementCounter('laravel_nats.publish.total', ['connection' => 'default', 'outcome' => 'success'], 2);

    expect($m->counterTotal('laravel_nats.publish.total', ['connection' => 'default', 'outcome' => 'success']))->toBe(3);
});

it('records histogram observations', function (): void {
    $m = new InMemoryNatsMetrics();
    $m->observeHistogram('laravel_nats.publish.latency_ms', 12.5, ['connection' => 'default']);

    $obs = $m->histogramObservations();

    expect($obs)->toHaveCount(1)
        ->and($obs[0]['name'])->toBe('laravel_nats.publish.latency_ms')
        ->and($obs[0]['value'])->toBe(12.5);
});

it('reset clears state', function (): void {
    $m = new InMemoryNatsMetrics();
    $m->incrementCounter('c', []);
    $m->reset();

    expect($m->counterTotal('c', []))->toBe(0)
        ->and($m->histogramObservations())->toBe([]);
});
