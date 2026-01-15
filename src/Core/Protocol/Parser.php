<?php

declare(strict_types=1);

namespace LaravelNats\Core\Protocol;

use LaravelNats\Exceptions\ProtocolException;

/**
 * Parser handles NATS protocol message parsing.
 *
 * The NATS protocol is text-based and line-oriented. Each command ends with CRLF.
 * This parser handles incoming protocol messages and extracts their components.
 *
 * NATS Protocol Commands:
 * - INFO {...}           Server info on connection
 * - MSG subject sid [reply-to] size\r\n[payload] Message delivery
 * - HMSG subject sid [reply-to] hdr-size total-size\r\n[headers]\r\n[payload] Message with headers
 * - +OK                  Acknowledgment (verbose mode)
 * - -ERR 'message'       Error from server
 * - PING                 Keepalive from server
 * - PONG                 Keepalive response
 *
 * The parser is stateless and can be used concurrently.
 */
final class Parser
{
    /**
     * Protocol command patterns.
     */
    private const PATTERN_INFO = '/^INFO\s+(.+)$/';

    private const PATTERN_MSG = '/^MSG\s+(\S+)\s+(\S+)\s+(?:(\S+)\s+)?(\d+)$/';

    private const PATTERN_HMSG = '/^HMSG\s+(\S+)\s+(\S+)\s+(?:(\S+)\s+)?(\d+)\s+(\d+)$/';

    private const PATTERN_ERR = '/^-ERR\s+[\'"]?(.+?)[\'"]?$/';

    private const PATTERN_PING = '/^PING$/';

    private const PATTERN_PONG = '/^PONG$/';

    private const PATTERN_OK = '/^\+OK$/';

    /**
     * Parse an INFO message.
     *
     * @param string $line The INFO line (without CRLF)
     *
     * @throws ProtocolException When parsing fails
     *
     * @return ServerInfo
     */
    public function parseInfo(string $line): ServerInfo
    {
        if (! preg_match(self::PATTERN_INFO, $line, $matches)) {
            throw ProtocolException::parseFailed($line, 'Expected INFO message');
        }

        $json = $matches[1];
        $data = json_decode($json, true);

        if (! is_array($data)) {
            throw ProtocolException::parseFailed($line, 'Invalid JSON in INFO message');
        }

        return ServerInfo::fromArray($data);
    }

    /**
     * Parse a MSG header line.
     *
     * Returns an array with:
     * - subject: The message subject
     * - sid: Subscription ID
     * - replyTo: Reply subject (null if not present)
     * - size: Payload size in bytes
     *
     * @param string $line The MSG line (without CRLF)
     *
     * @throws ProtocolException When parsing fails
     *
     * @return array{subject: string, sid: string, replyTo: string|null, size: int}
     */
    public function parseMsg(string $line): array
    {
        if (! preg_match(self::PATTERN_MSG, $line, $matches)) {
            throw ProtocolException::parseFailed($line, 'Expected MSG message');
        }

        // $matches[3] is the optional reply-to capture group
        // It exists but may be empty string when no reply-to is present
        $replyTo = $matches[3];

        return [
            'subject' => $matches[1],
            'sid' => $matches[2],
            'replyTo' => $replyTo !== '' ? $replyTo : null,
            'size' => (int) $matches[4],
        ];
    }

    /**
     * Parse an HMSG header line (message with headers).
     *
     * Returns an array with:
     * - subject: The message subject
     * - sid: Subscription ID
     * - replyTo: Reply subject (null if not present)
     * - headerSize: Size of headers section in bytes
     * - totalSize: Total size (headers + payload) in bytes
     *
     * @param string $line The HMSG line (without CRLF)
     *
     * @throws ProtocolException When parsing fails
     *
     * @return array{subject: string, sid: string, replyTo: string|null, headerSize: int, totalSize: int}
     */
    public function parseHmsg(string $line): array
    {
        if (! preg_match(self::PATTERN_HMSG, $line, $matches)) {
            throw ProtocolException::parseFailed($line, 'Expected HMSG message');
        }

        // $matches[3] is the optional reply-to capture group
        // It exists but may be empty string when no reply-to is present
        $replyTo = $matches[3];

        return [
            'subject' => $matches[1],
            'sid' => $matches[2],
            'replyTo' => $replyTo !== '' ? $replyTo : null,
            'headerSize' => (int) $matches[4],
            'totalSize' => (int) $matches[5],
        ];
    }

    /**
     * Parse headers from header data.
     *
     * Headers follow a format similar to HTTP headers:
     * NATS/1.0\r\n
     * Header-Name: Header-Value\r\n
     * \r\n
     *
     * @param string $headerData The raw header data
     *
     * @return array<string, string> Parsed headers
     */
    public function parseHeaders(string $headerData): array
    {
        $headers = [];
        $lines = explode("\r\n", trim($headerData));

        // Skip the version line (NATS/1.0)
        array_shift($lines);

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            $colonPos = strpos($line, ':');
            if ($colonPos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $colonPos));
            $value = trim(substr($line, $colonPos + 1));
            $headers[$key] = $value;
        }

        return $headers;
    }

    /**
     * Parse an error message from the server.
     *
     * @param string $line The -ERR line
     *
     * @throws ProtocolException When parsing fails
     *
     * @return string The error message
     */
    public function parseError(string $line): string
    {
        if (! preg_match(self::PATTERN_ERR, $line, $matches)) {
            throw ProtocolException::parseFailed($line, 'Expected -ERR message');
        }

        return $matches[1];
    }

    /**
     * Detect the type of a protocol line.
     *
     * @param string $line The protocol line (without CRLF)
     *
     * @return string The command type (INFO, MSG, HMSG, PING, PONG, +OK, -ERR, UNKNOWN)
     */
    public function detectType(string $line): string
    {
        if (str_starts_with($line, 'INFO ')) {
            return 'INFO';
        }

        if (str_starts_with($line, 'MSG ')) {
            return 'MSG';
        }

        if (str_starts_with($line, 'HMSG ')) {
            return 'HMSG';
        }

        if (preg_match(self::PATTERN_PING, $line)) {
            return 'PING';
        }

        if (preg_match(self::PATTERN_PONG, $line)) {
            return 'PONG';
        }

        if (preg_match(self::PATTERN_OK, $line)) {
            return '+OK';
        }

        if (str_starts_with($line, '-ERR')) {
            return '-ERR';
        }

        return 'UNKNOWN';
    }

    /**
     * Validate a subject string.
     *
     * NATS subjects:
     * - Cannot be empty
     * - Cannot contain spaces
     * - Tokens separated by dots
     * - * matches single token (except in publish)
     * - > matches one or more tokens at end (except in publish)
     *
     * @param string $subject The subject to validate
     * @param bool $allowWildcards Whether to allow * and > wildcards
     *
     * @return bool True if valid
     */
    public function isValidSubject(string $subject, bool $allowWildcards = false): bool
    {
        if ($subject === '') {
            return false;
        }

        // No spaces, tabs, or control characters
        if (preg_match('/[\s\x00-\x1F]/', $subject)) {
            return false;
        }

        // Check for wildcards
        if (! $allowWildcards) {
            if (str_contains($subject, '*') || str_contains($subject, '>')) {
                return false;
            }
        }

        // If wildcards allowed, validate their usage
        if ($allowWildcards) {
            $tokens = explode('.', $subject);

            foreach ($tokens as $i => $token) {
                // Empty tokens are invalid (e.g., "foo..bar")
                if ($token === '') {
                    return false;
                }

                // > must be the last token
                if ($token === '>' && $i !== count($tokens) - 1) {
                    return false;
                }

                // * must be the entire token, not partial
                if (str_contains($token, '*') && $token !== '*') {
                    return false;
                }
            }
        }

        return true;
    }
}
