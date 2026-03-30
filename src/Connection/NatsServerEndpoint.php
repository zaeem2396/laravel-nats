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
     * Parses "host:port", "nats://host:port", or "tls://host:port".
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
            $port = isset($parts['port']) ? (int) $parts['port'] : 4222;

            return new self($host, $port);
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

    public function toKey(): string
    {
        return $this->host . ':' . $this->port;
    }
}
