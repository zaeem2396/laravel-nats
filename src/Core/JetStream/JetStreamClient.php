<?php

declare(strict_types=1);

namespace LaravelNats\Core\JetStream;

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
