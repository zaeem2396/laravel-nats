<?php

declare(strict_types=1);

namespace LaravelNats\Observability;

use LaravelNats\Observability\Contracts\NatsMetricsContract;

/**
 * No-op metrics implementation (package default).
 */
final class NullNatsMetrics implements NatsMetricsContract
{
    public function incrementCounter(string $name, array $labels = [], int $delta = 1): void
    {
    }

    public function observeHistogram(string $name, float $value, array $labels = []): void
    {
    }
}
