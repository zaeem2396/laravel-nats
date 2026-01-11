<?php

declare(strict_types=1);

namespace LaravelNats\Core\Protocol;

/**
 * ServerInfo represents the INFO message sent by the NATS server upon connection.
 *
 * When a client connects, the NATS server immediately sends an INFO message
 * containing details about the server's capabilities, version, and cluster
 * configuration. This information is crucial for:
 *
 * - Determining available features (JetStream, headers, etc.)
 * - Understanding cluster topology
 * - Configuring client behavior based on server capabilities
 *
 * Example INFO JSON from server:
 * {
 *   "server_id": "NCXK...",
 *   "server_name": "my-nats",
 *   "version": "2.10.0",
 *   "proto": 1,
 *   "git_commit": "abc123",
 *   "go": "go1.21",
 *   "host": "0.0.0.0",
 *   "port": 4222,
 *   "headers": true,
 *   "max_payload": 1048576,
 *   "jetstream": true,
 *   ...
 * }
 */
final class ServerInfo
{
    /**
     * Create a new ServerInfo instance.
     *
     * @param string $serverId Unique server identifier
     * @param string $serverName Human-readable server name
     * @param string $version NATS server version
     * @param int $proto Protocol version
     * @param string $host Server host
     * @param int $port Server port
     * @param int $maxPayload Maximum message payload size in bytes
     * @param bool $headersSupported Whether headers are supported (NATS 2.2+)
     * @param bool $jetStreamEnabled Whether JetStream is enabled
     * @param bool $authRequired Whether authentication is required
     * @param bool $tlsRequired Whether TLS is required
     * @param bool $tlsAvailable Whether TLS is available
     * @param array<string> $connectUrls Cluster URLs for failover
     * @param string|null $clusterId Cluster identifier
     * @param string|null $clusterName Cluster name
     */
    public function __construct(
        public readonly string $serverId,
        public readonly string $serverName,
        public readonly string $version,
        public readonly int $proto,
        public readonly string $host,
        public readonly int $port,
        public readonly int $maxPayload,
        public readonly bool $headersSupported,
        public readonly bool $jetStreamEnabled,
        public readonly bool $authRequired,
        public readonly bool $tlsRequired,
        public readonly bool $tlsAvailable,
        public readonly array $connectUrls = [],
        public readonly ?string $clusterId = null,
        public readonly ?string $clusterName = null,
    ) {
    }

    /**
     * Create a ServerInfo from the JSON data in an INFO message.
     *
     * @param array<string, mixed> $data The decoded JSON data
     *
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            serverId: $data['server_id'] ?? '',
            serverName: $data['server_name'] ?? '',
            version: $data['version'] ?? '',
            proto: (int) ($data['proto'] ?? 1),
            host: $data['host'] ?? '0.0.0.0',
            port: (int) ($data['port'] ?? 4222),
            maxPayload: (int) ($data['max_payload'] ?? 1048576),
            headersSupported: (bool) ($data['headers'] ?? false),
            jetStreamEnabled: (bool) ($data['jetstream'] ?? false),
            authRequired: (bool) ($data['auth_required'] ?? false),
            tlsRequired: (bool) ($data['tls_required'] ?? false),
            tlsAvailable: (bool) ($data['tls_available'] ?? false),
            connectUrls: $data['connect_urls'] ?? [],
            clusterId: $data['cluster'] ?? null,
            clusterName: $data['cluster_name'] ?? null,
        );
    }

    /**
     * Check if the server supports a minimum protocol version.
     *
     * @param int $minVersion Minimum required protocol version
     *
     * @return bool
     */
    public function supportsProto(int $minVersion): bool
    {
        return $this->proto >= $minVersion;
    }

    /**
     * Get the maximum payload size in a human-readable format.
     *
     * @return string E.g., "1 MB"
     */
    public function getMaxPayloadFormatted(): string
    {
        $bytes = $this->maxPayload;

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' bytes';
    }
}
