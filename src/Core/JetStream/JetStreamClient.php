<?php

declare(strict_types=1);

namespace LaravelNats\Core\JetStream;

use LaravelNats\Contracts\Messaging\MessageInterface;
use LaravelNats\Core\Client;
use LaravelNats\Core\Connection\ConnectionConfig;
use LaravelNats\Core\Protocol\ServerInfo;
use LaravelNats\Exceptions\ConnectionException;
use LaravelNats\Exceptions\NatsException;
use LaravelNats\Exceptions\TimeoutException;

/**
 * JetStreamClient provides access to NATS JetStream functionality.
 *
 * JetStream is NATS's persistence and streaming layer. This client
 * provides methods to interact with JetStream streams and consumers
 * using the JetStream API protocol.
 *
 * JetStream API uses request/reply pattern:
 * - Requests are published to subjects like `$JS.API.STREAM.CREATE.*`
 * - Responses are received on reply subjects
 *
 * Basic Usage:
 *
 *     $client = new Client(ConnectionConfig::local());
 *     $client->connect();
 *
 *     $js = new JetStreamClient($client);
 *
 *     if ($js->isAvailable()) {
 *         // Use JetStream features
 *     }
 */
final class JetStreamClient
{
    /**
     * JetStream API prefix.
     */
    private const API_PREFIX = '$JS.API';

    /**
     * Default timeout for JetStream API requests (seconds).
     */
    private const DEFAULT_TIMEOUT = 5.0;

    /**
     * The underlying NATS client.
     */
    private readonly Client $client;

    /**
     * JetStream configuration.
     */
    private readonly JetStreamConfig $config;

    /**
     * Whether JetStream is available on this connection.
     */
    private ?bool $available = null;

    /**
     * Create a new JetStream client.
     *
     * @param Client $client The NATS client (must be connected)
     * @param JetStreamConfig|null $config JetStream configuration
     */
    public function __construct(
        Client $client,
        ?JetStreamConfig $config = null,
    ) {
        $this->client = $client;
        $this->config = $config ?? new JetStreamConfig();
    }

    /**
     * Check if JetStream is available on the connected server.
     *
     *
     * @throws ConnectionException If not connected
     *
     * @return bool True if JetStream is enabled
     */
    public function isAvailable(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }

        if (! $this->client->isConnected()) {
            throw ConnectionException::notConnected();
        }

        $serverInfo = $this->client->getServerInfo();

        if ($serverInfo === null) {
            $this->available = false;

            return false;
        }

        $this->available = $serverInfo->jetStreamEnabled;

        return $this->available;
    }

    /**
     * Get the server information.
     *
     * @return ServerInfo|null
     */
    public function getServerInfo(): ?ServerInfo
    {
        return $this->client->getServerInfo();
    }

    /**
     * Get the underlying NATS client.
     *
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Get the JetStream configuration.
     *
     * @return JetStreamConfig
     */
    public function getConfig(): JetStreamConfig
    {
        return $this->config;
    }

    /**
     * Make a JetStream API request.
     *
     * This is a low-level method for making requests to JetStream API subjects.
     * Higher-level methods should use this internally.
     *
     * @param string $subject The JetStream API subject (e.g., 'STREAM.CREATE.stream-name')
     * @param array<string, mixed> $payload The request payload
     * @param float $timeout Request timeout in seconds
     *
     * @throws NatsException If JetStream is not available
     * @throws TimeoutException If request times out
     * @throws ConnectionException If not connected
     *
     * @return array<string, mixed> The response payload
     */
    public function apiRequest(string $subject, array $payload = [], float $timeout = self::DEFAULT_TIMEOUT): array
    {
        if (! $this->isAvailable()) {
            throw new NatsException('JetStream is not available on this server');
        }

        $fullSubject = $this->buildApiSubject($subject);

        // Convert empty array to empty object for JetStream API
        $jsonPayload = empty($payload) ? '{}' : json_encode($payload);

        if ($jsonPayload === false) {
            throw new NatsException('Failed to encode JetStream API request payload');
        }

        $requestPayload = $jsonPayload;

        try {
            $response = $this->client->request($fullSubject, $requestPayload, $timeout);
            $decoded = $response->getDecodedPayload();

            if (! is_array($decoded)) {
                throw new NatsException('Invalid JetStream API response format');
            }

            // Check for error in response
            if (isset($decoded['error'])) {
                $errorCode = $decoded['error']['code'] ?? 0;
                $errorMessage = $decoded['error']['description'] ?? 'Unknown JetStream error';

                throw new NatsException("JetStream API error [{$errorCode}]: {$errorMessage}");
            }

            return $decoded;
        } catch (TimeoutException $e) {
            throw new TimeoutException("JetStream API request to '{$subject}' timed out after {$timeout} seconds", 0, $e);
        }
    }

    /**
     * Create a new stream.
     *
     * @param StreamConfig $config Stream configuration
     * @param float|null $timeout Request timeout
     *
     * @throws NatsException If JetStream is not available or creation fails
     * @throws TimeoutException If request times out
     * @throws ConnectionException If not connected
     *
     * @return StreamInfo Created stream information
     */
    public function createStream(StreamConfig $config, ?float $timeout = null): StreamInfo
    {
        $timeout ??= $this->config->getTimeout();
        $subject = 'STREAM.CREATE.' . $config->getName();
        $payload = $config->toArray();

        $response = $this->apiRequest($subject, $payload, $timeout);

        return StreamInfo::fromArray($response);
    }

    /**
     * Get stream information.
     *
     * @param string $streamName Stream name
     * @param float|null $timeout Request timeout
     *
     * @throws NatsException If JetStream is not available or stream not found
     * @throws TimeoutException If request times out
     * @throws ConnectionException If not connected
     *
     * @return StreamInfo Stream information
     */
    public function getStreamInfo(string $streamName, ?float $timeout = null): StreamInfo
    {
        $timeout ??= $this->config->getTimeout();
        $subject = 'STREAM.INFO.' . $streamName;

        $response = $this->apiRequest($subject, [], $timeout);

        return StreamInfo::fromArray($response);
    }

    /**
     * Update stream configuration.
     *
     * @param StreamConfig $config Stream configuration
     * @param float|null $timeout Request timeout
     *
     * @throws NatsException If JetStream is not available or update fails
     * @throws TimeoutException If request times out
     * @throws ConnectionException If not connected
     *
     * @return StreamInfo Updated stream information
     */
    public function updateStream(StreamConfig $config, ?float $timeout = null): StreamInfo
    {
        $timeout ??= $this->config->getTimeout();
        $subject = 'STREAM.UPDATE.' . $config->getName();
        $payload = $config->toArray();

        $response = $this->apiRequest($subject, $payload, $timeout);

        return StreamInfo::fromArray($response);
    }

    /**
     * Delete a stream.
     *
     * @param string $streamName Stream name
     * @param float|null $timeout Request timeout
     *
     * @throws NatsException If JetStream is not available or deletion fails
     * @throws TimeoutException If request times out
     * @throws ConnectionException If not connected
     *
     * @return bool True if deleted successfully
     */
    public function deleteStream(string $streamName, ?float $timeout = null): bool
    {
        $timeout ??= $this->config->getTimeout();
        $subject = 'STREAM.DELETE.' . $streamName;

        $this->apiRequest($subject, [], $timeout);

        return true;
    }

    /**
     * Purge all messages from a stream.
     *
     * @param string $streamName Stream name
     * @param float|null $timeout Request timeout
     *
     * @throws NatsException If JetStream is not available or purge fails
     * @throws TimeoutException If request times out
     * @throws ConnectionException If not connected
     *
     * @return bool True if purged successfully
     */
    public function purgeStream(string $streamName, ?float $timeout = null): bool
    {
        $timeout ??= $this->config->getTimeout();
        $subject = 'STREAM.PURGE.' . $streamName;

        $this->apiRequest($subject, [], $timeout);

        return true;
    }

    /**
     * Get a message from a stream by sequence number.
     *
     * @param string $streamName Stream name
     * @param int $sequence Sequence number
     * @param float|null $timeout Request timeout
     *
     * @throws NatsException If JetStream is not available or message not found
     * @throws TimeoutException If request times out
     * @throws ConnectionException If not connected
     *
     * @return array<string, mixed> Message data
     */
    public function getMessage(string $streamName, int $sequence, ?float $timeout = null): array
    {
        $timeout ??= $this->config->getTimeout();
        $subject = 'STREAM.MSG.GET.' . $streamName;
        $payload = ['seq' => $sequence];

        return $this->apiRequest($subject, $payload, $timeout);
    }

    /**
     * Delete a message from a stream by sequence number.
     *
     * @param string $streamName Stream name
     * @param int $sequence Sequence number
     * @param float|null $timeout Request timeout
     *
     * @throws NatsException If JetStream is not available or deletion fails
     * @throws TimeoutException If request times out
     * @throws ConnectionException If not connected
     *
     * @return bool True if deleted successfully
     */
    public function deleteMessage(string $streamName, int $sequence, ?float $timeout = null): bool
    {
        $timeout ??= $this->config->getTimeout();
        $subject = 'STREAM.MSG.DELETE.' . $streamName;
        $payload = ['seq' => $sequence];

        $this->apiRequest($subject, $payload, $timeout);

        return true;
    }

    /**
     * Create a durable consumer on a stream.
     *
     * @param string $streamName Stream name
     * @param string $consumerName Durable consumer name
     * @param ConsumerConfig $config Consumer configuration
     * @param float|null $timeout Request timeout
     *
     * @throws NatsException If JetStream is not available or creation fails
     * @throws TimeoutException If request times out
     * @throws ConnectionException If not connected
     *
     * @return ConsumerInfo Created consumer information
     */
    public function createConsumer(string $streamName, string $consumerName, ConsumerConfig $config, ?float $timeout = null): ConsumerInfo
    {
        $timeout ??= $this->config->getTimeout();
        $subject = 'CONSUMER.DURABLE.CREATE.' . $streamName . '.' . $consumerName;
        $payload = [
            'stream_name' => $streamName,
            'config' => $config->toArray(),
        ];

        $response = $this->apiRequest($subject, $payload, $timeout);

        return ConsumerInfo::fromArray($response);
    }

    /**
     * Get consumer information.
     *
     * @param string $streamName Stream name
     * @param string $consumerName Consumer name
     * @param float|null $timeout Request timeout
     *
     * @throws NatsException If JetStream is not available or consumer not found
     * @throws TimeoutException If request times out
     * @throws ConnectionException If not connected
     *
     * @return ConsumerInfo Consumer information
     */
    public function getConsumerInfo(string $streamName, string $consumerName, ?float $timeout = null): ConsumerInfo
    {
        $timeout ??= $this->config->getTimeout();
        $subject = 'CONSUMER.INFO.' . $streamName . '.' . $consumerName;

        $response = $this->apiRequest($subject, [], $timeout);

        return ConsumerInfo::fromArray($response);
    }

    /**
     * Delete a consumer.
     *
     * @param string $streamName Stream name
     * @param string $consumerName Consumer name
     * @param float|null $timeout Request timeout
     *
     * @throws NatsException If JetStream is not available or deletion fails
     * @throws TimeoutException If request times out
     * @throws ConnectionException If not connected
     *
     * @return bool True if deleted successfully
     */
    public function deleteConsumer(string $streamName, string $consumerName, ?float $timeout = null): bool
    {
        $timeout ??= $this->config->getTimeout();
        $subject = 'CONSUMER.DELETE.' . $streamName . '.' . $consumerName;

        $this->apiRequest($subject, [], $timeout);

        return true;
    }

    /**
     * Fetch the next message(s) from a pull consumer (batch size 1).
     *
     * Sends a request to CONSUMER.MSG.NEXT and returns the consumed message, or null when
     * no_wait is true and no message is available (server responds with Status 404).
     *
     * @param string $streamName Stream name
     * @param string $consumerName Consumer name
     * @param float|null $timeout Request timeout in seconds
     * @param bool $noWait If true, return null when no message available instead of waiting
     *
     * @throws NatsException If JetStream is not available or consumer not found
     * @throws TimeoutException If request times out (when no_wait is false and no message)
     * @throws ConnectionException If not connected
     *
     * @return JetStreamConsumedMessage|null The consumed message, or null when no_wait and no message
     */
    public function fetchNextMessage(
        string $streamName,
        string $consumerName,
        ?float $timeout = null,
        bool $noWait = false,
    ): ?JetStreamConsumedMessage {
        $timeout ??= $this->config->getTimeout();
        $subject = 'CONSUMER.MSG.NEXT.' . $streamName . '.' . $consumerName;
        $fullSubject = $this->buildApiSubject($subject);

        if (! $this->isAvailable()) {
            throw new NatsException('JetStream is not available on this server');
        }

        $payload = ['batch' => 1];
        if ($noWait) {
            $payload['no_wait'] = true;
        }

        try {
            $response = $this->client->request($fullSubject, $payload, $timeout);
        } catch (TimeoutException $e) {
            if ($noWait) {
                return null;
            }

            throw new TimeoutException(
                "JetStream fetch next message timed out after {$timeout} seconds",
                0,
                $e,
            );
        }

        // Server may respond with Status 404 when no messages (no_wait)
        if ($this->isNoMessageResponse($response)) {
            return null;
        }

        return JetStreamConsumedMessage::fromNatsMessage($response);
    }

    /**
     * Acknowledge a consumed message (positive ack).
     *
     * @param JetStreamConsumedMessage $message The message to acknowledge
     */
    public function ack(JetStreamConsumedMessage $message): void
    {
        $this->sendAck($message->getAckSubject(), JetStreamConsumedMessage::ACK);
    }

    /**
     * Negative acknowledge (redeliver the message).
     *
     * @param JetStreamConsumedMessage $message The message to nak
     * @param int|null $delayNanos Optional delay before redelivery (nanoseconds)
     */
    public function nak(JetStreamConsumedMessage $message, ?int $delayNanos = null): void
    {
        $payload = JetStreamConsumedMessage::NAK;
        if ($delayNanos !== null && $delayNanos > 0) {
            $payload = json_encode(['delay' => $delayNanos]);
            if ($payload === false) {
                $payload = JetStreamConsumedMessage::NAK;
            }
        }

        $this->client->publishRaw($message->getAckSubject(), $payload);
    }

    /**
     * Terminate the message (do not redeliver).
     *
     * @param JetStreamConsumedMessage $message The message to terminate
     */
    public function term(JetStreamConsumedMessage $message): void
    {
        $this->sendAck($message->getAckSubject(), JetStreamConsumedMessage::TERM);
    }

    /**
     * Signal work in progress (extend ack wait, do not redeliver yet).
     *
     * @param JetStreamConsumedMessage $message The message to signal in progress
     */
    public function inProgress(JetStreamConsumedMessage $message): void
    {
        $this->sendAck($message->getAckSubject(), JetStreamConsumedMessage::IN_PROGRESS);
    }

    /**
     * List consumers for a stream (paged).
     *
     * @param string $streamName Stream name
     * @param int $offset Pagination offset (default 0)
     * @param float|null $timeout Request timeout
     *
     * @throws NatsException If JetStream is not available or stream not found
     * @throws TimeoutException If request times out
     * @throws ConnectionException If not connected
     *
     * @return array{total: int, offset: int, limit: int, consumers: list<ConsumerInfo>}
     */
    public function listConsumers(string $streamName, int $offset = 0, ?float $timeout = null): array
    {
        $timeout ??= $this->config->getTimeout();
        $subject = 'CONSUMER.LIST.' . $streamName;
        $payload = ['offset' => $offset];

        $response = $this->apiRequest($subject, $payload, $timeout);

        $total = (int) ($response['total'] ?? 0);
        $off = (int) ($response['offset'] ?? 0);
        $limit = (int) ($response['limit'] ?? 0);
        $items = $response['consumers'] ?? [];

        $consumers = [];
        foreach ($items as $item) {
            $state = [
                'num_pending' => $item['num_pending'] ?? 0,
                'num_ack_pending' => $item['num_ack_pending'] ?? 0,
                'num_waiting' => $item['num_waiting'] ?? 0,
            ];
            $consumers[] = ConsumerInfo::fromArray([
                'stream_name' => $item['stream_name'] ?? $streamName,
                'name' => $item['name'] ?? '',
                'config' => $item['config'] ?? [],
                'state' => $state,
            ]);
        }

        return [
            'total' => $total,
            'offset' => $off,
            'limit' => $limit,
            'consumers' => $consumers,
        ];
    }

    /**
     * Send an ack payload to the ack subject.
     */
    private function sendAck(string $ackSubject, string $payload): void
    {
        if (! $this->isAvailable()) {
            throw new NatsException('JetStream is not available on this server');
        }

        $this->client->publishRaw($ackSubject, $payload);
    }

    /**
     * Check if the response indicates no message available (404).
     */
    private function isNoMessageResponse(MessageInterface $message): bool
    {
        $status = $message->getHeader('Status');

        return $status === '404' || $status === '404 ';
    }

    /**
     * Build a full JetStream API subject.
     *
     * @param string $subject The API subject (e.g., 'STREAM.CREATE.stream-name')
     *
     * @return string Full subject (e.g., '$JS.API.STREAM.CREATE.stream-name')
     */
    private function buildApiSubject(string $subject): string
    {
        $prefix = $this->config->getDomain() !== null
            ? '$JS.' . $this->config->getDomain() . '.API'
            : self::API_PREFIX;

        return $prefix . '.' . $subject;
    }
}
