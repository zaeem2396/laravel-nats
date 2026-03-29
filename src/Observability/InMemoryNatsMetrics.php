<?php

declare(strict_types=1);

namespace LaravelNats\Observability;

use LaravelNats\Observability\Contracts\NatsMetricsContract;

/**
 * In-process counters and histogram samples for tests or local debugging. Not suitable for multi-request FPM metrics.
 */
final class InMemoryNatsMetrics implements NatsMetricsContract
{
    /** @var array<string, int> */
    private array $counters = [];

    /** @var list<array{name: string, value: float, labels: array<string, string>}> */
    private array $histograms = [];

    public function incrementCounter(string $name, array $labels = [], int $delta = 1): void
    {
        $key = $name . "\0" . json_encode($labels, JSON_THROW_ON_ERROR);
        $this->counters[$key] = ($this->counters[$key] ?? 0) + $delta;
    }

    public function observeHistogram(string $name, float $value, array $labels = []): void
    {
        $this->histograms[] = [
            'name' => $name,
            'value' => $value,
            'labels' => $labels,
        ];
    }

    /**
     * @param array<string, string> $labels
     */
    public function counterTotal(string $name, array $labels = []): int
    {
        $key = $name . "\0" . json_encode($labels, JSON_THROW_ON_ERROR);

        return $this->counters[$key] ?? 0;
    }

    /**
     * @return list<array{name: string, value: float, labels: array<string, string>}>
     */
    public function histogramObservations(): array
    {
        return $this->histograms;
    }

    public function reset(): void
    {
        $this->counters = [];
        $this->histograms = [];
    }
}
