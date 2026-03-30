<?php

declare(strict_types=1);

namespace LaravelNats\Publisher;

use Basis\Nats\Message\Payload;
use Basis\Nats\Message\Publish;
use Illuminate\Contracts\Config\Repository;
use JsonException;
use LaravelNats\Connection\ConnectionManager;
use LaravelNats\Exceptions\PublishException;
use LaravelNats\Observability\Contracts\NatsMetricsContract;
use LaravelNats\Publisher\Contracts\NatsPublisherContract;
use LaravelNats\Security\Exceptions\SubjectNotAllowedException;
use LaravelNats\Security\SubjectAclChecker;
use LaravelNats\Support\CorrelationHeaders;
use LaravelNats\Support\IdempotencyHeaders;
use LaravelNats\Support\MessageEnvelope;
use LogicException;

/**
 * Publishes JSON envelopes via basis-company/nats (HPUB when headers are present).
 *
 * @see \Basis\Nats\Message\Publish
 */
final class NatsPublisher implements NatsPublisherContract
{
    public function __construct(
        private readonly ConnectionManager $connections,
        private readonly Repository $config,
        private readonly NatsMetricsContract $metrics,
        private readonly SubjectAclChecker $subjectAcl,
    ) {
    }

    /**
     * {@inheritdoc}
     *
     * @throws PublishException
     */
    public function publish(string $subject, array $payload, array $headers = [], ?string $connection = null): void
    {
        $t0 = microtime(true);

        try {
            $this->subjectAcl->assertPublishAllowed($subject);

            $version = (string) $this->config->get('nats_basis.envelope_version', 'v1');
            $data = $payload;
            $idempotencyKey = null;
            if (array_key_exists('idempotency_key', $data)) {
                $raw = $data['idempotency_key'];
                unset($data['idempotency_key']);
                if (is_string($raw)) {
                    $trimmed = trim($raw);
                    if ($trimmed !== '') {
                        $idempotencyKey = $trimmed;
                    }
                }
            }

            $envelope = MessageEnvelope::create($subject, $data, $version, $idempotencyKey);
            $body = json_encode($envelope->toArray(), JSON_THROW_ON_ERROR);

            $client = $this->connections->connection($connection);
            $basisConnection = $client->connection;
            if ($basisConnection === null) {
                throw new LogicException('NATS client is disconnected; cannot publish.');
            }

            $merged = CorrelationHeaders::mergeForPublish($this->config, $this->normalizeHeaders($headers));
            $merged = IdempotencyHeaders::mergeForPublish($this->config, $merged, $idempotencyKey);
            $payloadMessage = new Payload($body, $merged);
            $basisConnection->sendMessage(new Publish([
                'subject' => $subject,
                'payload' => $payloadMessage,
                'replyTo' => null,
            ]));
            $this->recordPublishOutcome(true, $connection, (microtime(true) - $t0) * 1000.0);
        } catch (JsonException $e) {
            $this->recordPublishOutcome(false, $connection, (microtime(true) - $t0) * 1000.0);

            throw new PublishException(
                sprintf('Failed to publish to "%s": %s', $subject, 'Failed to encode message envelope: ' . $e->getMessage()),
                0,
                $e,
            );
        } catch (\Throwable $e) {
            if ($e instanceof PublishException) {
                $this->recordPublishOutcome(false, $connection, (microtime(true) - $t0) * 1000.0);

                throw $e;
            }

            if ($e instanceof SubjectNotAllowedException) {
                throw $e;
            }

            $this->recordPublishOutcome(false, $connection, (microtime(true) - $t0) * 1000.0);

            throw new PublishException(
                sprintf('Failed to publish to "%s": %s', $subject, $e->getMessage()),
                0,
                $e,
            );
        }
    }

    private function recordPublishOutcome(bool $success, ?string $connection, float $elapsedMs): void
    {
        if (! filter_var($this->config->get('nats_basis.observability.metrics_enabled', false), FILTER_VALIDATE_BOOL)) {
            return;
        }

        $connName = $connection ?? $this->connections->getDefaultConnection();
        $labels = [
            'connection' => $connName,
            'outcome' => $success ? 'success' : 'failure',
        ];
        $this->metrics->incrementCounter('laravel_nats.publish.total', $labels);
        if (
            $success
            && filter_var($this->config->get('nats_basis.observability.publish_latency_histogram', false), FILTER_VALIDATE_BOOL)
        ) {
            $this->metrics->observeHistogram('laravel_nats.publish.latency_ms', $elapsedMs, [
                'connection' => $connName,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $headers
     *
     * @return array<string, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }
            $out[$key] = is_string($value) ? $value : (string) $value;
        }

        return $out;
    }
}
