<?php

declare(strict_types=1);

namespace LaravelNats\JetStream;

use Illuminate\Contracts\Config\Repository;
use JsonException;
use LaravelNats\Connection\ConnectionManager;
use LaravelNats\Exceptions\PublishException;
use LaravelNats\Security\Exceptions\SubjectNotAllowedException;
use LaravelNats\Security\SubjectAclChecker;
use LaravelNats\Support\MessageEnvelope;

/**
 * Publishes to subjects captured by a JetStream stream using basis {@see \Basis\Nats\Stream\Stream}.
 */
final class BasisJetStreamPublisher
{
    public function __construct(
        private readonly ConnectionManager $connections,
        private readonly Repository $config,
        private readonly SubjectAclChecker $subjectAcl,
    ) {
    }

    /**
     * @param array<string, mixed> $payload Application payload (wrapped when {@see $useEnvelope} is true)
     * @param array<string, string> $headers Reserved for a future HPUB path; JetStream publish in basis is body-only today
     *
     * @throws PublishException
     */
    public function publish(
        string $streamName,
        string $subject,
        array $payload,
        bool $useEnvelope = true,
        bool $waitForAck = true,
        array $headers = [],
        ?string $connection = null,
    ): void {
        unset($headers);

        try {
            $manager = new BasisJetStreamManager($this->connections, $connection);
            $stream = $manager->stream($streamName, $connection);
            if ($useEnvelope) {
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
            } else {
                $body = json_encode($payload, JSON_THROW_ON_ERROR);
            }

            if ($waitForAck) {
                $stream->publish($subject, $body);
            } else {
                $stream->put($subject, $body);
            }
        } catch (JsonException $e) {
            throw new PublishException(
                sprintf('Failed to publish to JetStream stream "%s": %s', $streamName, $e->getMessage()),
                0,
                $e,
            );
        } catch (\Throwable $e) {
            if ($e instanceof PublishException) {
                throw $e;
            }

            throw new PublishException(
                sprintf('Failed to publish to JetStream stream "%s": %s', $streamName, $e->getMessage()),
                0,
                $e,
            );
        }
    }
}
