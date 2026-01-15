<?php

declare(strict_types=1);

namespace LaravelNats\Core\Connection;

use LaravelNats\Contracts\Connection\ConnectionInterface;
use LaravelNats\Core\Protocol\CommandBuilder;
use LaravelNats\Core\Protocol\Parser;
use LaravelNats\Core\Protocol\ServerInfo;
use LaravelNats\Exceptions\ConnectionException;
use LaravelNats\Exceptions\ProtocolException;

/**
 * Connection manages the TCP/TLS socket connection to a NATS server.
 *
 * This class handles:
 * - Socket creation and connection
 * - TLS upgrade when required
 * - Protocol handshake (INFO/CONNECT exchange)
 * - Raw I/O operations
 * - Connection state tracking
 *
 * The connection follows the NATS protocol flow:
 * 1. Client connects via TCP
 * 2. Server sends INFO message
 * 3. Client sends CONNECT message
 * 4. (Optional) TLS upgrade if required
 * 5. Connection ready for messaging
 *
 * This is a low-level class - typically you'll use the Client class
 * which provides higher-level messaging operations.
 */
final class Connection implements ConnectionInterface
{
    /**
     * Read buffer size.
     */
    private const BUFFER_SIZE = 65536; // 64KB

    /**
     * Interval between proactive health checks in seconds.
     */
    private const HEALTH_CHECK_INTERVAL = 5.0;

    /**
     * The socket resource.
     *
     * @var resource|null
     */
    private $socket = null;

    /**
     * Server information received on connect.
     */
    private ?ServerInfo $serverInfo = null;

    /**
     * Buffer for incomplete line reads.
     */
    private string $readBuffer = '';

    /**
     * Whether currently connected.
     */
    private bool $connected = false;

    /**
     * Timestamp of last successful I/O operation.
     */
    private float $lastActivityTime = 0.0;

    /**
     * Timestamp of last health check.
     */
    private float $lastHealthCheck = 0.0;

    /**
     * Number of consecutive failed pings (for health tracking).
     */
    private int $failedPings = 0;

    /**
     * Protocol parser.
     */
    private readonly Parser $parser;

    /**
     * Command builder.
     */
    private readonly CommandBuilder $commandBuilder;

    /**
     * Create a new connection instance.
     *
     * @param ConnectionConfig $config Connection configuration
     */
    public function __construct(
        private readonly ConnectionConfig $config,
    ) {
        $this->parser = new Parser();
        $this->commandBuilder = new CommandBuilder();
    }

    /**
     * {@inheritdoc}
     *
     * Establishes a TCP connection and performs the NATS protocol handshake.
     */
    public function connect(): void
    {
        if ($this->connected) {
            return;
        }

        $this->createSocket();
        $this->performHandshake();
        $this->connected = true;
        $this->lastActivityTime = microtime(true);
        $this->lastHealthCheck = microtime(true);
    }

    /**
     * {@inheritdoc}
     */
    public function disconnect(): void
    {
        if ($this->socket !== null) {
            // Attempt graceful close
            @fclose($this->socket);
            $this->socket = null;
        }

        $this->connected = false;
        $this->serverInfo = null;
        $this->readBuffer = '';
        $this->lastActivityTime = 0.0;
        $this->lastHealthCheck = 0.0;
        $this->failedPings = 0;
    }

    /**
     * {@inheritdoc}
     */
    public function isConnected(): bool
    {
        if (! $this->connected || $this->socket === null) {
            return false;
        }

        // Check if socket is still valid using feof
        if (feof($this->socket)) {
            $this->markDisconnected();

            return false;
        }

        // Proactive check: use stream_get_meta_data to detect issues
        $meta = stream_get_meta_data($this->socket);
        if ($meta['eof'] || $meta['timed_out']) {
            $this->markDisconnected();

            return false;
        }

        return true;
    }

    /**
     * Perform a proactive health check on the connection.
     *
     * This method sends a PING and waits for PONG to verify the connection
     * is still alive. Use this for long-running processes.
     *
     * @param float $timeout Maximum time to wait for PONG in seconds
     *
     * @throws ConnectionException If health check fails
     *
     * @return bool True if connection is healthy
     */
    public function healthCheck(float $timeout = 2.0): bool
    {
        if (! $this->isConnected()) {
            return false;
        }

        try {
            // Send PING
            $this->ping();

            // Wait for PONG
            $deadline = microtime(true) + $timeout;

            while (microtime(true) < $deadline) {
                $line = $this->readLine();

                if ($line !== null) {
                    $type = $this->parser->detectType($line);

                    if ($type === 'PONG') {
                        $this->failedPings = 0;
                        $this->lastHealthCheck = microtime(true);

                        return true;
                    }

                    // Handle PING from server during health check
                    if ($type === 'PING') {
                        $this->pong();
                    }
                }

                usleep(1000);
            }

            // Timeout waiting for PONG
            $this->failedPings++;

            if ($this->failedPings >= $this->config->getMaxPingsOut()) {
                $this->markDisconnected();

                return false;
            }

            return true; // Allow some failed pings before declaring dead
        } catch (ConnectionException) {
            $this->markDisconnected();

            return false;
        }
    }

    /**
     * Check if a health check is due based on activity time.
     *
     * @return bool True if health check should be performed
     */
    public function isHealthCheckDue(): bool
    {
        if (! $this->connected) {
            return false;
        }

        $now = microtime(true);
        $sinceLastActivity = $now - $this->lastActivityTime;
        $sinceLastCheck = $now - $this->lastHealthCheck;

        // Health check is due if we haven't had activity or checks recently
        return $sinceLastActivity > self::HEALTH_CHECK_INTERVAL
            && $sinceLastCheck > self::HEALTH_CHECK_INTERVAL;
    }

    /**
     * Get the time since last successful I/O activity.
     *
     * @return float Seconds since last activity, or 0 if never connected
     */
    public function getIdleTime(): float
    {
        if ($this->lastActivityTime === 0.0) {
            return 0.0;
        }

        return microtime(true) - $this->lastActivityTime;
    }

    /**
     * Get the number of consecutive failed ping attempts.
     *
     * @return int Failed ping count
     */
    public function getFailedPingCount(): int
    {
        return $this->failedPings;
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $data): void
    {
        if (! $this->isConnected()) {
            throw ConnectionException::notConnected();
        }

        $this->doWrite($data);
    }

    /**
     * {@inheritdoc}
     */
    public function read(int $length = 0): ?string
    {
        if (! $this->isConnected()) {
            throw ConnectionException::notConnected();
        }

        $readLength = $length > 0 ? $length : self::BUFFER_SIZE;

        $data = @fread($this->socket, $readLength);

        if ($data === false) {
            $this->handleSocketError();

            return null;
        }

        return $data === '' ? null : $data;
    }

    /**
     * {@inheritdoc}
     *
     * This method buffers partial reads and returns complete lines.
     */
    public function readLine(): ?string
    {
        if (! $this->isConnected()) {
            throw ConnectionException::notConnected();
        }

        return $this->doReadLine();
    }

    /**
     * Read a specific number of bytes from the connection.
     *
     * Unlike read(), this method ensures exactly $length bytes are read.
     *
     * @param int $length Number of bytes to read
     *
     * @throws ConnectionException When read fails
     *
     * @return string The data read
     */
    public function readBytes(int $length): string
    {
        $data = '';

        // First, drain from the buffer
        if ($this->readBuffer !== '') {
            $fromBuffer = min($length, strlen($this->readBuffer));
            $data = substr($this->readBuffer, 0, $fromBuffer);
            $this->readBuffer = substr($this->readBuffer, $fromBuffer);
            $length -= $fromBuffer;
        }

        // Read remaining from socket
        while ($length > 0) {
            $chunk = $this->read($length);

            if ($chunk === null) {
                throw ConnectionException::readFailed('Unexpected end of data');
            }

            $data .= $chunk;
            $length -= strlen($chunk);
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function getServerInfo(): ?ServerInfo
    {
        return $this->serverInfo;
    }

    /**
     * {@inheritdoc}
     */
    public function ping(): void
    {
        $this->write($this->commandBuilder->ping());
    }

    /**
     * {@inheritdoc}
     */
    public function pong(): void
    {
        $this->write($this->commandBuilder->pong());
    }

    /**
     * Get the protocol parser.
     *
     * @return Parser
     */
    public function getParser(): Parser
    {
        return $this->parser;
    }

    /**
     * Get the command builder.
     *
     * @return CommandBuilder
     */
    public function getCommandBuilder(): CommandBuilder
    {
        return $this->commandBuilder;
    }

    /**
     * Check socket readability without blocking.
     *
     * This method uses stream_select to check if there's data available
     * or if the socket has been closed by the remote end.
     *
     * @param float $timeout Maximum time to wait in seconds (default 0 = non-blocking)
     *
     * @return bool|null True if readable, false if not, null if error/closed
     */
    public function isReadable(float $timeout = 0.0): ?bool
    {
        if ($this->socket === null) {
            return null;
        }

        $seconds = (int) floor($timeout);
        $microseconds = (int) (($timeout - $seconds) * 1000000);

        // Perform stream_select to check socket state
        $selectResult = $this->performStreamSelect(
            readSockets: [$this->socket],
            exceptSockets: [$this->socket],
            seconds: $seconds,
            microseconds: $microseconds,
        );

        if ($selectResult === null) {
            // stream_select failed
            $this->markDisconnected();

            return null;
        }

        if ($selectResult['hasExceptions']) {
            $this->markDisconnected();

            return null;
        }

        return $selectResult['readable'];
    }

    /**
     * Attempt to detect if the connection is still alive.
     *
     * This performs a quick, non-blocking check using stream metadata
     * and optional socket-level checks.
     *
     * @return bool True if connection appears alive
     */
    public function probeConnection(): bool
    {
        if (! $this->connected || $this->socket === null) {
            return false;
        }

        // Check stream metadata
        $meta = stream_get_meta_data($this->socket);

        if ($meta['eof']) {
            $this->markDisconnected();

            return false;
        }

        if ($meta['timed_out']) {
            // Timeout occurred - connection might be stale
            return false;
        }

        // Check for socket exceptions (non-blocking)
        $selectResult = $this->performStreamSelect(
            readSockets: [],
            exceptSockets: [$this->socket],
            seconds: 0,
            microseconds: 0,
        );

        if ($selectResult === null || $selectResult['hasExceptions']) {
            $this->markDisconnected();

            return false;
        }

        return true;
    }

    /**
     * Internal write implementation.
     *
     * This method is used during handshake when $this->connected is not yet true.
     *
     * @param string $data Data to write
     *
     * @throws ConnectionException
     */
    private function doWrite(string $data): void
    {
        if ($this->socket === null) {
            throw ConnectionException::notConnected();
        }

        $length = strlen($data);
        $written = 0;

        while ($written < $length) {
            $result = @fwrite($this->socket, substr($data, $written));

            if ($result === false) {
                $this->handleSocketError();

                throw ConnectionException::writeFailed();
            }

            if ($result === 0) {
                throw ConnectionException::disconnected();
            }

            $written += $result;
        }

        // Flush the stream
        fflush($this->socket);

        // Update activity timestamp
        $this->lastActivityTime = microtime(true);
    }

    /**
     * Internal read line implementation.
     *
     * This method is used during handshake when $this->connected is not yet true.
     *
     * @throws ConnectionException
     *
     * @return string|null
     */
    private function doReadLine(): ?string
    {
        if ($this->socket === null) {
            throw ConnectionException::notConnected();
        }

        // Check buffer first for complete line
        $crlfPos = strpos($this->readBuffer, "\r\n");
        if ($crlfPos !== false) {
            $line = substr($this->readBuffer, 0, $crlfPos);
            $this->readBuffer = substr($this->readBuffer, $crlfPos + 2);

            return $line;
        }

        // Try to read more data
        $data = @fgets($this->socket);

        if ($data === false) {
            // Check if this is just no data available or an actual error
            if (feof($this->socket)) {
                throw ConnectionException::disconnected();
            }

            return null;
        }

        $this->readBuffer .= $data;

        // Update activity timestamp on successful read
        $this->lastActivityTime = microtime(true);

        // Try again for complete line
        $crlfPos = strpos($this->readBuffer, "\r\n");
        if ($crlfPos !== false) {
            $line = substr($this->readBuffer, 0, $crlfPos);
            $this->readBuffer = substr($this->readBuffer, $crlfPos + 2);

            return $line;
        }

        return null;
    }

    /**
     * Create the TCP socket.
     *
     * @throws ConnectionException When socket creation fails
     */
    private function createSocket(): void
    {
        $address = sprintf(
            '%s://%s:%d',
            $this->config->isTlsEnabled() ? 'tls' : 'tcp',
            $this->config->getHost(),
            $this->config->getPort(),
        );

        $context = stream_context_create([
            'ssl' => $this->config->getTlsOptions(),
        ]);

        $errorCode = 0;
        $errorMessage = '';

        $socket = @stream_socket_client(
            $address,
            $errorCode,
            $errorMessage,
            $this->config->getTimeout(),
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if ($socket === false) {
            if ($errorCode === 110 || $errorCode === 10060) { // ETIMEDOUT
                throw ConnectionException::timeout(
                    $this->config->getHost(),
                    $this->config->getPort(),
                    $this->config->getTimeout(),
                );
            }

            if ($errorCode === 111 || $errorCode === 10061) { // ECONNREFUSED
                throw ConnectionException::refused(
                    $this->config->getHost(),
                    $this->config->getPort(),
                );
            }

            throw new ConnectionException(
                sprintf('Failed to connect to %s: %s (%d)', $address, $errorMessage, $errorCode),
            );
        }

        // Set stream options
        stream_set_blocking($socket, true);
        stream_set_timeout($socket, (int) $this->config->getTimeout());

        $this->socket = $socket;
    }

    /**
     * Perform the NATS protocol handshake.
     *
     * @throws ConnectionException When handshake fails
     * @throws ProtocolException When protocol errors occur
     */
    private function performHandshake(): void
    {
        // Read INFO from server
        $infoLine = $this->waitForLine();

        if ($infoLine === null) {
            throw ConnectionException::readFailed('No INFO received from server');
        }

        $type = $this->parser->detectType($infoLine);

        if ($type !== 'INFO') {
            throw ProtocolException::invalidMessage('Expected INFO, got: ' . $infoLine);
        }

        $this->serverInfo = $this->parser->parseInfo($infoLine);

        // Check if TLS is required but not enabled
        if ($this->serverInfo->tlsRequired && ! $this->config->isTlsEnabled()) {
            throw ConnectionException::tlsFailed('Server requires TLS but TLS is not enabled');
        }

        // Check if auth is required
        if ($this->serverInfo->authRequired && ! $this->config->hasAuth()) {
            throw ConnectionException::authenticationFailed('Server requires authentication');
        }

        // Send CONNECT
        $connectCommand = $this->commandBuilder->connect(
            $this->config->toConnectArray(),
        );
        $this->doWrite($connectCommand);

        // Send initial PING to verify connection
        $this->doWrite($this->commandBuilder->ping());

        // Wait for PONG (or error)
        $response = $this->waitForLine();

        if ($response === null) {
            throw ConnectionException::readFailed('No response to CONNECT');
        }

        $responseType = $this->parser->detectType($response);

        if ($responseType === '-ERR') {
            $error = $this->parser->parseError($response);

            throw ConnectionException::authenticationFailed($error);
        }

        // May receive +OK if verbose mode, then PONG
        if ($responseType === '+OK') {
            $response = $this->waitForLine();
            $responseType = $response !== null ? $this->parser->detectType($response) : 'UNKNOWN';
        }

        if ($responseType !== 'PONG') {
            throw ProtocolException::invalidMessage('Expected PONG, got: ' . ($response ?? 'nothing'));
        }
    }

    /**
     * Wait for a complete line with timeout.
     *
     * Uses doReadLine() to work during handshake when $this->connected is not yet true.
     *
     * @return string|null The line or null on timeout
     */
    private function waitForLine(): ?string
    {
        $deadline = microtime(true) + $this->config->getTimeout();

        while (microtime(true) < $deadline) {
            $line = $this->doReadLine();

            if ($line !== null) {
                return $line;
            }

            // Small sleep to avoid busy wait
            usleep(1000);
        }

        return null;
    }

    /**
     * Handle socket errors and update connection state.
     */
    private function handleSocketError(): void
    {
        $error = error_get_last();

        if ($error !== null) {
            // Log error if logging is available
        }

        // Check if connection is still valid
        if ($this->socket !== null && feof($this->socket)) {
            $this->markDisconnected();
        }
    }

    /**
     * Mark the connection as disconnected.
     *
     * This is called when we detect the connection has been lost.
     * It updates internal state but does NOT close the socket
     * (that's done in disconnect()).
     */
    private function markDisconnected(): void
    {
        $this->connected = false;
    }

    /**
     * Perform stream_select and return structured results.
     *
     * This helper method wraps stream_select to work around PHPStan's
     * limitations with understanding in-place array modification.
     *
     * @param array<resource> $readSockets Sockets to check for readability
     * @param array<resource> $exceptSockets Sockets to check for exceptions
     * @param int $seconds Timeout seconds
     * @param int $microseconds Timeout microseconds
     *
     * @return array{readable: bool, hasExceptions: bool}|null Null on failure
     */
    private function performStreamSelect(
        array $readSockets,
        array $exceptSockets,
        int $seconds,
        int $microseconds,
    ): ?array {
        $read = $readSockets;
        $write = [];
        $except = $exceptSockets;

        $result = @stream_select($read, $write, $except, $seconds, $microseconds);

        if ($result === false) {
            return null;
        }

        // After stream_select:
        // - $read contains only sockets that are readable
        // - $except contains only sockets with exceptions
        // We need to check if these arrays still have elements
        $readCount = count($read);
        $exceptCount = count($except);

        return [
            'readable' => $readCount > 0,
            'hasExceptions' => $exceptCount > 0,
        ];
    }
}
