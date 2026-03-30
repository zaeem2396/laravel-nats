<?php

declare(strict_types=1);

namespace LaravelNats\Connection;

/**
 * A single TCP endpoint (host + port) for basis NATS connections.
 */
final class NatsServerEndpoint
{
    public function __construct(
        public readonly string $host,
        public readonly int $port,
    ) {
    }

    /**
     * Parses "host:port", "[ipv6]:port", "nats://host:port", or "tls://host:port".
     */
    public static function parse(string $raw): ?self
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (str_contains($raw, '://')) {
            $parts = parse_url($raw);
            if ($parts === false || ! isset($parts['host'])) {
                return null;
            }
            $host = (string) $parts['host'];
            if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
                $host = substr($host, 1, -1);
            }
            $port = isset($parts['port']) ? (int) $parts['port'] : 4222;

            return new self($host, $port);
        }

        if (str_starts_with($raw, '[')) {
            return self::parseBracketedEndpoint($raw);
        }

        if (! str_contains($raw, ':')) {
            return new self($raw, 4222);
        }

        $pos = strrpos($raw, ':');
        if ($pos === false) {
            return null;
        }

        $host = trim(substr($raw, 0, $pos));
        $portStr = trim(substr($raw, $pos + 1));
        if ($host === '' || $portStr === '' || ! ctype_digit($portStr)) {
            return null;
        }

        $port = (int) $portStr;
        if ($port < 1 || $port > 65535) {
            return null;
        }

        return new self($host, $port);
    }

    /**
     * Parses "[ipv6]:port" or "[ipv6]" (default port). Avoids strrpos on colon-delimited IPv6 literals.
     */
    private static function parseBracketedEndpoint(string $raw): ?self
    {
        $close = strpos($raw, ']');
        if ($close === false) {
            return null;
        }

        $host = trim(substr($raw, 1, $close - 1));
        if ($host === '') {
            return null;
        }

        $rest = trim(substr($raw, $close + 1));
        if ($rest === '') {
            return new self($host, 4222);
        }

        if (! str_starts_with($rest, ':')) {
            return null;
        }

        $portStr = trim(substr($rest, 1));
        if ($portStr === '' || ! ctype_digit($portStr)) {
            return null;
        }

        $port = (int) $portStr;
        if ($port < 1 || $port > 65535) {
            return null;
        }

        return new self($host, $port);
    }

    public function toKey(): string
    {
        return $this->host . ':' . $this->port;
    }
}
