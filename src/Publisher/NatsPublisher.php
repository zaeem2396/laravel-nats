<?php

declare(strict_types=1);

namespace LaravelNats\Publisher;

use Basis\Nats\Message\Payload;
use Basis\Nats\Message\Publish;
use Illuminate\Contracts\Config\Repository;
use JsonException;
use LaravelNats\Connection\ConnectionManager;
use LaravelNats\Exceptions\PublishException;
use LaravelNats\Publisher\Contracts\NatsPublisherContract;
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
    ) {
    }

    /**
     * {@inheritdoc}
     *
     * @throws PublishException
     */
    public function publish(string $subject, array $payload, array $headers = [], ?string $connection = null): void
    {
        try {
            $version = (string) $this->config->get('nats_basis.envelope_version', 'v1');
            $envelope = MessageEnvelope::create($subject, $payload, $version);
            $body = json_encode($envelope->toArray(), JSON_THROW_ON_ERROR);

            $client = $this->connections->connection($connection);
            $basisConnection = $client->connection;
            if ($basisConnection === null) {
                throw new LogicException('NATS client is disconnected; cannot publish.');
            }

            $payloadMessage = new Payload($body, $this->normalizeHeaders($headers));
            $basisConnection->sendMessage(new Publish([
                'subject' => $subject,
                'payload' => $payloadMessage,
                'replyTo' => null,
            ]));
        } catch (JsonException $e) {
            throw new PublishException(
                sprintf('Failed to publish to "%s": %s', $subject, 'Failed to encode message envelope: ' . $e->getMessage()),
                0,
                $e,
            );
        } catch (\Throwable $e) {
            if ($e instanceof PublishException) {
                throw $e;
            }

            throw new PublishException(
                sprintf('Failed to publish to "%s": %s', $subject, $e->getMessage()),
                0,
                $e,
            );
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
