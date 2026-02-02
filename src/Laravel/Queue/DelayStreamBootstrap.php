<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Queue;

use LaravelNats\Core\JetStream\ConsumerConfig;
use LaravelNats\Core\JetStream\JetStreamClient;
use LaravelNats\Core\JetStream\StreamConfig;
use LaravelNats\Exceptions\NatsException;
use LaravelNats\Laravel\NatsManager;

/**
 * Ensures the JetStream delay stream and consumer exist for delayed jobs.
 *
 * When delayed jobs are enabled, jobs are published to a JetStream stream
 * and a pull consumer is used to process them when due. This class creates
 * the stream and consumer if they do not already exist.
 */
final class DelayStreamBootstrap
{
    public function __construct(
        private readonly NatsManager $nats,
    ) {
    }

    /**
     * Ensure the delay stream and consumer exist using an existing JetStream client.
     *
     * Use this when you already have a JetStreamClient (e.g. from the queue connector).
     * Idempotent: safe to call multiple times.
     *
     * @param JetStreamClient $js JetStream client
     * @param string $streamName JetStream stream name
     * @param string $subjectPrefix Subject prefix for delay messages (e.g. "laravel.delayed.")
     * @param string $consumerName Durable consumer name
     *
     * @throws NatsException If JetStream is not available or creation fails
     */
    public static function ensureStreamAndConsumer(
        JetStreamClient $js,
        string $streamName,
        string $subjectPrefix,
        string $consumerName,
    ): void {
        if (! $js->isAvailable()) {
            throw new NatsException('JetStream is not available on this server');
        }

        self::ensureStreamInternal($js, $streamName, $subjectPrefix);
        self::ensureConsumerInternal($js, $streamName, $subjectPrefix, $consumerName);
    }

    private static function ensureStreamInternal(
        JetStreamClient $js,
        string $streamName,
        string $subjectPrefix,
    ): void {
        $subject = rtrim($subjectPrefix, '.') . '.>';

        try {
            $js->getStreamInfo($streamName);

            return;
        } catch (NatsException $e) {
            if (! str_contains(strtolower($e->getMessage()), 'not found')) {
                throw $e;
            }
        }

        $config = (new StreamConfig($streamName, [$subject]))
            ->withDescription('Laravel delayed queue jobs')
            ->withRetention(StreamConfig::RETENTION_LIMITS)
            ->withStorage(StreamConfig::STORAGE_FILE);

        $js->createStream($config);
    }

    private static function ensureConsumerInternal(
        JetStreamClient $js,
        string $streamName,
        string $subjectPrefix,
        string $consumerName,
    ): void {
        $filterSubject = rtrim($subjectPrefix, '.') . '.>';

        try {
            $js->getConsumerInfo($streamName, $consumerName);

            return;
        } catch (NatsException $e) {
            if (! str_contains(strtolower($e->getMessage()), 'not found')) {
                throw $e;
            }
        }

        $config = (new ConsumerConfig($consumerName))
            ->withFilterSubject($filterSubject)
            ->withDeliverPolicy(ConsumerConfig::DELIVER_ALL)
            ->withAckPolicy(ConsumerConfig::ACK_EXPLICIT);

        $js->createConsumer($streamName, $consumerName, $config);
    }

    /**
     * Ensure the delay stream and consumer exist for the given configuration.
     *
     * Creates the stream and durable consumer if they are missing.
     * Idempotent: safe to call multiple times.
     *
     * @param string|null $connectionName NATS connection name (null for default)
     * @param string $streamName JetStream stream name
     * @param string $subjectPrefix Subject prefix for delay messages (e.g. "laravel.delayed.")
     * @param string $consumerName Durable consumer name
     *
     * @throws NatsException If JetStream is not available or creation fails
     */
    public function ensure(
        ?string $connectionName,
        string $streamName,
        string $subjectPrefix,
        string $consumerName,
    ): void {
        $js = $this->nats->jetstream($connectionName);

        if (! $js->isAvailable()) {
            throw new NatsException('JetStream is not available on this server');
        }

        self::ensureStreamInternal($js, $streamName, $subjectPrefix);
        self::ensureConsumerInternal($js, $streamName, $subjectPrefix, $consumerName);
    }
}
