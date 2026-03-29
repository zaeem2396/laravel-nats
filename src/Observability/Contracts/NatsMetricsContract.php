<?php

declare(strict_types=1);

namespace LaravelNats\Observability\Contracts;

/**
 * Optional publish/subscribe metrics hook. Default binding is {@see \LaravelNats\Observability\NullNatsMetrics}.
 *
 * Rebind in a service provider to forward counters and histograms to Prometheus, OpenTelemetry, or StatsD.
 * Use **low-cardinality** label values only (connection name, outcome), not raw subjects.
 */
interface NatsMetricsContract
{
    /**
     * @param array<string, string> $labels
     */
    public function incrementCounter(string $name, array $labels = [], int $delta = 1): void;

    /**
     * @param array<string, string> $labels
     */
    public function observeHistogram(string $name, float $value, array $labels = []): void;
}
