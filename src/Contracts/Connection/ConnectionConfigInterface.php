<?php

declare(strict_types=1);

namespace LaravelNats\Contracts\Connection;

/**
 * ConnectionConfigInterface defines the contract for connection configuration.
 *
 * This interface provides a structured way to access connection parameters
 * that are needed to establish and maintain a NATS connection.
 *
 * Configuration includes:
 * - Server URLs and authentication
 * - Timeout settings
 * - TLS/SSL options
 * - Buffer sizes and limits
 */
interface ConnectionConfigInterface
{
    /**
     * Get the NATS server URL.
     *
     * @return string The server URL (e.g., "nats://localhost:4222")
     */
    public function getHost(): string;

    /**
     * Get the NATS server port.
     *
     * @return int The server port (default: 4222)
     */
    public function getPort(): int;

    /**
     * Get the username for authentication.
     *
     * @return string|null The username, or null if not using user/pass auth
     */
    public function getUser(): ?string;

    /**
     * Get the password for authentication.
     *
     * @return string|null The password, or null if not using user/pass auth
     */
    public function getPassword(): ?string;

    /**
     * Get the authentication token.
     *
     * @return string|null The token, or null if not using token auth
     */
    public function getToken(): ?string;

    /**
     * Get the connection timeout in seconds.
     *
     * @return float The timeout value
     */
    public function getTimeout(): float;

    /**
     * Check if TLS/SSL should be used.
     *
     * @return bool True if TLS should be used
     */
    public function isTlsEnabled(): bool;

    /**
     * Get the TLS context options.
     *
     * @return array<string, mixed> SSL context options for stream_context_create
     */
    public function getTlsOptions(): array;

    /**
     * Get the client name to identify this connection.
     *
     * @return string The client name
     */
    public function getClientName(): string;

    /**
     * Check if verbose mode is enabled.
     *
     * In verbose mode, the server sends +OK for successful operations.
     *
     * @return bool True if verbose mode is enabled
     */
    public function isVerbose(): bool;

    /**
     * Check if pedantic mode is enabled.
     *
     * In pedantic mode, the server performs additional validation.
     *
     * @return bool True if pedantic mode is enabled
     */
    public function isPedantic(): bool;

    /**
     * Get the ping interval in seconds.
     *
     * @return float The interval between ping messages
     */
    public function getPingInterval(): float;

    /**
     * Get the maximum ping outstanding before disconnect.
     *
     * @return int Maximum pings without pong before considering connection dead
     */
    public function getMaxPingsOut(): int;

    /**
     * Convert the configuration to an array for the CONNECT command.
     *
     * @return array<string, mixed> The configuration as an associative array
     */
    public function toConnectArray(): array;
}
