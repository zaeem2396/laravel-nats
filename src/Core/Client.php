<?php

declare(strict_types=1);

namespace LaravelNats\Core;

use LaravelNats\Contracts\Messaging\MessageInterface;
use LaravelNats\Contracts\Messaging\PublisherInterface;
use LaravelNats\Contracts\Messaging\SubscriberInterface;
use LaravelNats\Contracts\Serialization\SerializerInterface;
use LaravelNats\Core\Connection\Connection;
use LaravelNats\Core\Connection\ConnectionConfig;
use LaravelNats\Core\JetStream\JetStreamClient;
use LaravelNats\Core\JetStream\JetStreamConfig;
use LaravelNats\Core\Messaging\Message;
use LaravelNats\Core\Protocol\ServerInfo;
use LaravelNats\Core\Serialization\JsonSerializer;
use LaravelNats\Exceptions\ConnectionException;
use LaravelNats\Exceptions\PublishException;
use LaravelNats\Exceptions\SubscriptionException;
use LaravelNats\Exceptions\TimeoutException;

/**
 * Client is the main entry point for NATS operations.
 *
 * This class provides a high-level API for:
 * - Publishing messages to subjects
 * - Subscribing to subjects
 * - Request/Reply pattern
 *
 * The Client manages the underlying connection and provides
 * convenience methods that handle serialization, protocol
 * formatting, and error handling.
 *
 * Basic Usage:
 *
 *     $client = new Client(ConnectionConfig::local());
 *     $client->connect();
 *
 *     // Publish
 *     $client->publish('orders.created', ['id' => 123]);
 *
 *     // Subscribe
 *     $client->subscribe('orders.*', function (Message $msg) {
 *         echo "Received: " . $msg->getSubject();
 *     });
 *
 *     // Request/Reply
 *     $response = $client->request('api.users.get', ['id' => 1]);
 */
final class Client implements PublisherInterface, SubscriberInterface
{
    /**
     * The underlying connection.
     */
    private readonly Connection $connection;

    /**
     * The serializer for message payloads.
     */
    private SerializerInterface $serializer;

    /**
     * Active subscriptions.
     *
     * @var array<string, array{subject: string, queue: string|null, callback: callable}>
     */
    private array $subscriptions = [];

    /**
     * Subscription ID counter.
     */
    private int $sidCounter = 0;

    /**
     * Pending requests waiting for replies.
     *
     * @var array<string, array{deadline: float, message: MessageInterface|null}>
     */
    private array $pendingRequests = [];

    /**
     * Reply subject prefix for this client.
     */
    private string $inboxPrefix;

    /**
     * Inbox subscription ID.
     */
    private ?string $inboxSid = null;

    /**
     * Create a new NATS client.
     *
     * @param ConnectionConfig $config Connection configuration
     * @param SerializerInterface|null $serializer Message serializer (defaults to JSON)
     */
    public function __construct(
        ConnectionConfig $config,
        ?SerializerInterface $serializer = null,
    ) {
        $this->connection = new Connection($config);
        $this->serializer = $serializer ?? new JsonSerializer();
        $this->inboxPrefix = '_INBOX.' . bin2hex(random_bytes(8));
    }

    /**
     * Connect to the NATS server.
     *
     * @throws ConnectionException When connection fails
     */
    public function connect(): void
    {
        $this->connection->connect();
    }

    /**
     * Disconnect from the NATS server.
     */
    public function disconnect(): void
    {
        $this->connection->disconnect();
        $this->subscriptions = [];
        $this->pendingRequests = [];
        $this->inboxSid = null;
    }

    /**
     * Check if connected to the NATS server.
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->connection->isConnected();
    }

    /**
     * Get the server information.
     *
     * @return ServerInfo|null
     */
    public function getServerInfo(): ?ServerInfo
    {
        return $this->connection->getServerInfo();
    }

    /**
     * Set the serializer.
     *
     * @param SerializerInterface $serializer
     */
    public function setSerializer(SerializerInterface $serializer): void
    {
        $this->serializer = $serializer;
    }

    /**
     * Get the serializer.
     *
     * @return SerializerInterface
     */
    public function getSerializer(): SerializerInterface
    {
        return $this->serializer;
    }

    /**
     * Get a JetStream client for this connection.
     *
     * @param JetStreamConfig|null $config JetStream configuration
     *
     * @return JetStreamClient
     */
    public function getJetStream(?JetStreamConfig $config = null): JetStreamClient
    {
        return new JetStreamClient($this, $config);
    }

    /**
     * {@inheritdoc}
     */
    public function publish(string $subject, mixed $payload, array $headers = []): void
    {
        $this->ensureConnected();
        $this->validateSubject($subject, false);

        $serializedPayload = $this->serializer->serialize($payload);

        if ($headers !== []) {
            $command = $this->connection->getCommandBuilder()->publishWithHeaders(
                $subject,
                $serializedPayload,
                $headers,
            );
        } else {
            $command = $this->connection->getCommandBuilder()->publish(
                $subject,
                $serializedPayload,
            );
        }

        $this->connection->write($command);
    }

    /**
     * {@inheritdoc}
     */
    public function publishRaw(string $subject, string $payload, ?string $replyTo = null, array $headers = []): void
    {
        $this->ensureConnected();
        $this->validateSubject($subject, false);

        if ($headers !== []) {
            $command = $this->connection->getCommandBuilder()->publishWithHeaders(
                $subject,
                $payload,
                $headers,
                $replyTo,
            );
        } else {
            $command = $this->connection->getCommandBuilder()->publish(
                $subject,
                $payload,
                $replyTo,
            );
        }

        $this->connection->write($command);
    }

    /**
     * {@inheritdoc}
     */
    public function request(string $subject, mixed $payload, float $timeout = 5.0, array $headers = []): MessageInterface
    {
        $this->ensureConnected();
        $this->validateSubject($subject, false);

        // Ensure inbox subscription is set up
        $this->ensureInboxSubscription();

        // Generate unique inbox for this request
        $replyTo = $this->inboxPrefix . '.' . bin2hex(random_bytes(4));

        // Set up pending request
        $deadline = microtime(true) + $timeout;
        $this->pendingRequests[$replyTo] = [
            'deadline' => $deadline,
            'message' => null,
        ];

        // Publish with reply-to
        $serializedPayload = $this->serializer->serialize($payload);

        if ($headers !== []) {
            $command = $this->connection->getCommandBuilder()->publishWithHeaders(
                $subject,
                $serializedPayload,
                $headers,
                $replyTo,
            );
        } else {
            $command = $this->connection->getCommandBuilder()->publish(
                $subject,
                $serializedPayload,
                $replyTo,
            );
        }

        $this->connection->write($command);

        // Wait for reply
        while (microtime(true) < $deadline) {
            $this->process(0.1);

            // process() may have updated the pending request with a response
            $message = $this->pendingRequests[$replyTo]['message'];
            // @phpstan-ignore-next-line (message is set by dispatchMessage via process)
            if ($message !== null) {
                unset($this->pendingRequests[$replyTo]);

                return $message;
            }
        }

        unset($this->pendingRequests[$replyTo]);

        throw TimeoutException::requestTimeout($subject, $timeout);
    }

    /**
     * {@inheritdoc}
     */
    public function subscribe(string $subject, callable $callback): string
    {
        $this->ensureConnected();
        $this->validateSubject($subject, true);

        $sid = $this->generateSid();

        $command = $this->connection->getCommandBuilder()->subscribe($subject, $sid);
        $this->connection->write($command);

        $this->subscriptions[$sid] = [
            'subject' => $subject,
            'queue' => null,
            'callback' => $callback,
        ];

        return $sid;
    }

    /**
     * {@inheritdoc}
     */
    public function queueSubscribe(string $subject, string $queue, callable $callback): string
    {
        $this->ensureConnected();
        $this->validateSubject($subject, true);

        $sid = $this->generateSid();

        $command = $this->connection->getCommandBuilder()->subscribe($subject, $sid, $queue);
        $this->connection->write($command);

        $this->subscriptions[$sid] = [
            'subject' => $subject,
            'queue' => $queue,
            'callback' => $callback,
        ];

        return $sid;
    }

    /**
     * {@inheritdoc}
     */
    public function unsubscribe(string $sid, ?int $maxMessages = null): void
    {
        $this->ensureConnected();

        if (! isset($this->subscriptions[$sid])) {
            throw SubscriptionException::notFound($sid);
        }

        $command = $this->connection->getCommandBuilder()->unsubscribe($sid, $maxMessages);
        $this->connection->write($command);

        if ($maxMessages === null) {
            unset($this->subscriptions[$sid]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function process(float $timeout = 0.0): int
    {
        if (! $this->isConnected()) {
            return 0;
        }

        $processed = 0;
        $deadline = microtime(true) + $timeout;

        do {
            $line = $this->connection->readLine();

            if ($line === null) {
                if ($timeout > 0) {
                    usleep(1000); // 1ms sleep to avoid busy wait
                }
                continue;
            }

            $type = $this->connection->getParser()->detectType($line);

            switch ($type) {
                case 'MSG':
                    $processed += $this->handleMsg($line);
                    break;

                case 'HMSG':
                    $processed += $this->handleHmsg($line);
                    break;

                case 'PING':
                    $this->connection->pong();
                    break;

                case 'PONG':
                    // Handle pong (for request timeout, connection health)
                    break;

                case '-ERR':
                    $error = $this->connection->getParser()->parseError($line);
                    // Log or handle error
                    break;
            }
        } while ($timeout > 0 && microtime(true) < $deadline);

        return $processed;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscriptions(): array
    {
        return $this->subscriptions;
    }

    /**
     * {@inheritdoc}
     */
    public function hasSubscription(string $sid): bool
    {
        return isset($this->subscriptions[$sid]);
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscriptionCount(): int
    {
        return count($this->subscriptions);
    }

    /**
     * Handle a MSG protocol message.
     *
     * @param string $line The MSG line
     *
     * @return int Number of messages processed
     */
    private function handleMsg(string $line): int
    {
        $parsed = $this->connection->getParser()->parseMsg($line);

        // Read payload
        $payload = $this->connection->readBytes($parsed['size']);

        // Read trailing CRLF
        $this->connection->readBytes(2);

        $message = Message::fromReceived(
            subject: $parsed['subject'],
            payload: $payload,
            replyTo: $parsed['replyTo'],
            sid: $parsed['sid'],
            serializer: $this->serializer,
        );

        return $this->dispatchMessage($message);
    }

    /**
     * Handle an HMSG protocol message (with headers).
     *
     * @param string $line The HMSG line
     *
     * @return int Number of messages processed
     */
    private function handleHmsg(string $line): int
    {
        $parsed = $this->connection->getParser()->parseHmsg($line);

        // Read headers + payload
        $data = $this->connection->readBytes($parsed['totalSize']);

        // Read trailing CRLF
        $this->connection->readBytes(2);

        // Split headers and payload
        $headerData = substr($data, 0, $parsed['headerSize']);
        $payload = substr($data, $parsed['headerSize']);

        $headers = $this->connection->getParser()->parseHeaders($headerData);

        $message = Message::fromReceived(
            subject: $parsed['subject'],
            payload: $payload,
            replyTo: $parsed['replyTo'],
            sid: $parsed['sid'],
            headers: $headers,
            serializer: $this->serializer,
        );

        return $this->dispatchMessage($message);
    }

    /**
     * Dispatch a message to the appropriate handler.
     *
     * @param Message $message The message to dispatch
     *
     * @return int 1 if dispatched, 0 otherwise
     */
    private function dispatchMessage(Message $message): int
    {
        $sid = $message->getSid();

        // Check if this is a reply to a pending request
        if (str_starts_with($message->getSubject(), $this->inboxPrefix . '.')) {
            $replySubject = $message->getSubject();
            if (isset($this->pendingRequests[$replySubject])) {
                $this->pendingRequests[$replySubject]['message'] = $message;

                return 1;
            }
        }

        // Dispatch to subscription callback
        if ($sid !== null && isset($this->subscriptions[$sid])) {
            $callback = $this->subscriptions[$sid]['callback'];
            $callback($message);

            return 1;
        }

        return 0;
    }

    /**
     * Ensure the inbox subscription is set up for request/reply.
     */
    private function ensureInboxSubscription(): void
    {
        if ($this->inboxSid !== null) {
            return;
        }

        $inboxSubject = $this->inboxPrefix . '.>';
        $this->inboxSid = $this->generateSid();

        $command = $this->connection->getCommandBuilder()->subscribe($inboxSubject, $this->inboxSid);
        $this->connection->write($command);

        // Store internally but don't add to public subscriptions
        $this->subscriptions[$this->inboxSid] = [
            'subject' => $inboxSubject,
            'queue' => null,
            'callback' => function (): void {
            }, // Handled specially
        ];
    }

    /**
     * Ensure the client is connected.
     *
     * @throws ConnectionException
     */
    private function ensureConnected(): void
    {
        if (! $this->isConnected()) {
            throw ConnectionException::notConnected();
        }
    }

    /**
     * Validate a subject string.
     *
     * @param string $subject The subject to validate
     * @param bool $allowWildcards Whether wildcards are allowed
     *
     * @throws PublishException|SubscriptionException
     */
    private function validateSubject(string $subject, bool $allowWildcards): void
    {
        $parser = $this->connection->getParser();

        if (! $parser->isValidSubject($subject, $allowWildcards)) {
            if ($allowWildcards) {
                throw SubscriptionException::invalidSubject($subject);
            }

            throw PublishException::invalidSubject($subject);
        }
    }

    /**
     * Generate a unique subscription ID.
     *
     * @return string
     */
    private function generateSid(): string
    {
        return (string) ++$this->sidCounter;
    }
}
