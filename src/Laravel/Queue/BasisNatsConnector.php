<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Queue;

use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\Connectors\ConnectorInterface;
use LaravelNats\Connection\ConnectionManager;
use RuntimeException;

/**
 * Queue connector for the `nats_basis` driver (basis-company/nats via {@see ConnectionManager}).
 */
class BasisNatsConnector implements ConnectorInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function connect(array $config): Queue
    {
        $manager = $this->resolveConnectionManager();

        $prefix = $config['prefix'] ?? $this->readConfig('nats_basis.queue.prefix', 'laravel.queue.');
        $dlqSubject = $config['dead_letter_queue'] ?? $config['dlq_subject'] ?? null;
        if ($dlqSubject === '') {
            $dlqSubject = null;
        }
        if ($dlqSubject !== null && is_string($dlqSubject) && ! str_contains($dlqSubject, '.')) {
            $dlqSubject = $prefix . $dlqSubject;
        }

        $blockFor = $config['block_for'] ?? $this->readConfig('nats_basis.queue.block_for', 0.1);
        $blockFor = is_numeric($blockFor) ? (float) $blockFor : 0.1;

        $basisConnection = $config['nats_basis_connection'] ?? $config['basis_connection'] ?? null;
        if ($basisConnection === '') {
            $basisConnection = null;
        }

        $maxInFlight = $config['max_in_flight'] ?? $this->readConfig('nats_basis.queue.max_in_flight', null);

        return new BasisNatsQueue(
            connections: $manager,
            basisConnectionName: is_string($basisConnection) ? $basisConnection : null,
            defaultQueue: $config['queue'] ?? 'default',
            retryAfter: (int) ($config['retry_after'] ?? $this->readConfig('nats_basis.queue.retry_after', 60)),
            maxTries: (int) ($config['tries'] ?? $this->readConfig('nats_basis.queue.tries', 3)),
            deadLetterQueue: is_string($dlqSubject) ? $dlqSubject : null,
            subjectPrefix: is_string($prefix) ? $prefix : 'laravel.queue.',
            popBlockSeconds: $blockFor,
            maxInFlight: $this->normalizeMaxInFlight($maxInFlight),
        );
    }

    /**
     * @param mixed $value
     */
    protected function normalizeMaxInFlight(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $n = (int) $value;

        return $n > 0 ? $n : null;
    }

    protected function resolveConnectionManager(): ConnectionManager
    {
        if (! function_exists('app') || ! app()->bound(ConnectionManager::class)) {
            throw new RuntimeException('The nats_basis queue driver requires the Laravel container and ConnectionManager (register NatsServiceProvider).');
        }

        return app(ConnectionManager::class);
    }

    /**
     * @param mixed $default
     *
     * @return mixed
     */
    protected function readConfig(string $key, mixed $default = []): mixed
    {
        if (! function_exists('config')) {
            return $default;
        }

        try {
            $value = config($key, $default);

            return $value ?? $default;
        } catch (\Throwable) {
            return $default;
        }
    }
}
