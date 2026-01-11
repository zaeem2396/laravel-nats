<?php

declare(strict_types=1);

namespace LaravelNats\Core\Protocol;

/**
 * CommandBuilder constructs NATS protocol commands.
 *
 * This class is responsible for building properly formatted NATS protocol
 * messages that can be sent to the server. All commands are terminated
 * with CRLF (\r\n).
 *
 * NATS Client Commands:
 * - CONNECT {...}        Client connection info (sent after INFO)
 * - PUB subject [reply-to] size\r\n[payload]\r\n   Publish message
 * - HPUB subject [reply-to] hdr-size total-size\r\n[headers]\r\n[payload]\r\n  Publish with headers
 * - SUB subject [queue] sid   Subscribe to subject
 * - UNSUB sid [max-msgs]      Unsubscribe
 * - PING                      Keepalive ping
 * - PONG                      Keepalive response
 */
final class CommandBuilder
{
    /**
     * Protocol line terminator.
     */
    private const CRLF = "\r\n";

    /**
     * Build a CONNECT command.
     *
     * The CONNECT command is sent by the client after receiving INFO.
     * It contains client information and authentication credentials.
     *
     * @param array<string, mixed> $options Client options
     *
     * @return string The complete CONNECT command
     */
    public function connect(array $options): string
    {
        $json = json_encode($options, JSON_THROW_ON_ERROR);

        return 'CONNECT ' . $json . self::CRLF;
    }

    /**
     * Build a PUB command (publish without headers).
     *
     * @param string $subject The subject to publish to
     * @param string $payload The message payload
     * @param string|null $replyTo Optional reply-to subject
     *
     * @return string The complete PUB command with payload
     */
    public function publish(string $subject, string $payload, ?string $replyTo = null): string
    {
        $size = strlen($payload);

        if ($replyTo !== null) {
            return sprintf('PUB %s %s %d%s%s%s', $subject, $replyTo, $size, self::CRLF, $payload, self::CRLF);
        }

        return sprintf('PUB %s %d%s%s%s', $subject, $size, self::CRLF, $payload, self::CRLF);
    }

    /**
     * Build an HPUB command (publish with headers).
     *
     * Headers are formatted as:
     * NATS/1.0\r\n
     * Header-Name: Header-Value\r\n
     * \r\n
     *
     * @param string $subject The subject to publish to
     * @param string $payload The message payload
     * @param array<string, string> $headers Message headers
     * @param string|null $replyTo Optional reply-to subject
     *
     * @return string The complete HPUB command
     */
    public function publishWithHeaders(
        string $subject,
        string $payload,
        array $headers,
        ?string $replyTo = null,
    ): string {
        $headerBlock = $this->buildHeaders($headers);
        $headerSize = strlen($headerBlock);
        $totalSize = $headerSize + strlen($payload);

        if ($replyTo !== null) {
            return sprintf(
                'HPUB %s %s %d %d%s%s%s%s',
                $subject,
                $replyTo,
                $headerSize,
                $totalSize,
                self::CRLF,
                $headerBlock,
                $payload,
                self::CRLF,
            );
        }

        return sprintf(
            'HPUB %s %d %d%s%s%s%s',
            $subject,
            $headerSize,
            $totalSize,
            self::CRLF,
            $headerBlock,
            $payload,
            self::CRLF,
        );
    }

    /**
     * Build a SUB command.
     *
     * @param string $subject The subject pattern to subscribe to
     * @param string $sid The subscription ID (client-generated)
     * @param string|null $queue Optional queue group name
     *
     * @return string The SUB command
     */
    public function subscribe(string $subject, string $sid, ?string $queue = null): string
    {
        if ($queue !== null) {
            return sprintf('SUB %s %s %s%s', $subject, $queue, $sid, self::CRLF);
        }

        return sprintf('SUB %s %s%s', $subject, $sid, self::CRLF);
    }

    /**
     * Build an UNSUB command.
     *
     * @param string $sid The subscription ID to unsubscribe
     * @param int|null $maxMessages Optional max messages before auto-unsubscribe
     *
     * @return string The UNSUB command
     */
    public function unsubscribe(string $sid, ?int $maxMessages = null): string
    {
        if ($maxMessages !== null) {
            return sprintf('UNSUB %s %d%s', $sid, $maxMessages, self::CRLF);
        }

        return sprintf('UNSUB %s%s', $sid, self::CRLF);
    }

    /**
     * Build a PING command.
     *
     * @return string The PING command
     */
    public function ping(): string
    {
        return 'PING' . self::CRLF;
    }

    /**
     * Build a PONG command.
     *
     * @return string The PONG command
     */
    public function pong(): string
    {
        return 'PONG' . self::CRLF;
    }

    /**
     * Build the headers block for HPUB.
     *
     * @param array<string, string> $headers The headers to format
     *
     * @return string The formatted header block
     */
    private function buildHeaders(array $headers): string
    {
        $lines = ['NATS/1.0'];

        foreach ($headers as $key => $value) {
            $lines[] = sprintf('%s: %s', $key, $value);
        }

        // Empty line at end to separate from payload
        $lines[] = '';
        $lines[] = '';

        return implode(self::CRLF, $lines);
    }
}
