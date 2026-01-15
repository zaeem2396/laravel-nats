<?php

declare(strict_types=1);

namespace LaravelNats\Contracts\Connection;

use LaravelNats\Core\Protocol\ServerInfo;

/**
 * ConnectionInterface defines the contract for NATS server connections.
 *
 * This interface abstracts the low-level socket connection to a NATS server,
 * providing methods for connection lifecycle management and raw I/O operations.
 *
 * The connection is responsible for:
 * - Establishing TCP/TLS connections to NATS servers
 * - Managing connection state (connected, disconnected, reconnecting)
 * - Sending and receiving raw protocol data
 * - Handling ping/pong for connection health
 */
interface ConnectionInterface
{
    /**
     * Establish a connection to the NATS server.
     *
     * This method initiates the TCP/TLS handshake with the NATS server
     * and performs the initial protocol negotiation (INFO/CONNECT exchange).
     *
     * @throws \LaravelNats\Exceptions\ConnectionException When connection fails
     */
    public function connect(): void;

    /**
     * Close the connection to the NATS server.
     *
     * This method gracefully closes the connection, sending any pending
     * data and properly closing the socket.
     */
    public function disconnect(): void;

    /**
     * Check if currently connected to the NATS server.
     *
     * @return bool True if connected, false otherwise
     */
    public function isConnected(): bool;

    /**
     * Send raw data to the NATS server.
     *
     * This method writes data directly to the socket. The data should
     * already be formatted according to the NATS protocol.
     *
     * @param string $data The raw protocol data to send
     *
     * @throws \LaravelNats\Exceptions\ConnectionException When write fails
     */
    public function write(string $data): void;

    /**
     * Read raw data from the NATS server.
     *
     * This method reads data from the socket buffer. It may return
     * partial data depending on what's available.
     *
     * @param int $length Maximum number of bytes to read
     *
     * @throws \LaravelNats\Exceptions\ConnectionException When read fails
     *
     * @return string|null The data read, or null if no data available
     */
    public function read(int $length = 0): ?string;

    /**
     * Read a complete line from the NATS server.
     *
     * NATS protocol uses CRLF-terminated lines for commands.
     * This method reads until a complete line is available.
     *
     * @throws \LaravelNats\Exceptions\ConnectionException When read fails
     *
     * @return string|null The complete line (without CRLF), or null if not available
     */
    public function readLine(): ?string;

    /**
     * Get the server information received during connection.
     *
     * The NATS server sends an INFO message upon connection that contains
     * details about the server (version, capabilities, cluster info, etc.).
     *
     * @return ServerInfo|null Server info if connected, null otherwise
     */
    public function getServerInfo(): ?ServerInfo;

    /**
     * Send a PING to the server.
     *
     * PING is used for connection health checking. The server responds with PONG.
     */
    public function ping(): void;

    /**
     * Send a PONG to the server.
     *
     * PONG is sent in response to a server PING.
     */
    public function pong(): void;

    /**
     * Perform a proactive health check on the connection.
     *
     * This method sends a PING and waits for PONG to verify the connection
     * is still alive. Use this for long-running processes.
     *
     * @param float $timeout Maximum time to wait for PONG in seconds
     *
     * @return bool True if connection is healthy
     */
    public function healthCheck(float $timeout = 2.0): bool;

    /**
     * Check if a health check is due based on activity time.
     *
     * @return bool True if health check should be performed
     */
    public function isHealthCheckDue(): bool;

    /**
     * Get the time since last successful I/O activity.
     *
     * @return float Seconds since last activity, or 0 if never connected
     */
    public function getIdleTime(): float;

    /**
     * Attempt to detect if the connection is still alive.
     *
     * This performs a quick, non-blocking check using stream metadata.
     *
     * @return bool True if connection appears alive
     */
    public function probeConnection(): bool;
}
