<?php

declare(strict_types=1);

namespace LaravelNats\Security;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use LaravelNats\Security\Exceptions\NatsConfigurationException;

/**
 * Optional strict checks for `nats_basis` before connections are used (see `nats_basis.security.validate_on_boot`).
 */
final class NatsBasisConfigurationValidator
{
    /**
     * @param bool $force When true (e.g. `nats:v2:config:validate`), run checks even if validate_on_boot is false.
     */
    public function validate(Repository $config, Application $app, bool $force = false): void
    {
        if (
            ! $force
            && ! filter_var($config->get('nats_basis.security.validate_on_boot', false), FILTER_VALIDATE_BOOL)
        ) {
            return;
        }

        /** @var mixed $connections */
        $connections = $config->get('nats_basis.connections', []);
        if (! is_array($connections) || $connections === []) {
            throw NatsConfigurationException::global('nats_basis.connections must be a non-empty array when validate_on_boot is enabled.');
        }

        $requireTlsInProduction = filter_var($config->get('nats_basis.security.tls.require_in_production', false), FILTER_VALIDATE_BOOL);
        $production = $app->environment('production');

        foreach ($connections as $name => $entry) {
            if (! is_string($name) || $name === '') {
                throw NatsConfigurationException::global('nats_basis.connections keys must be non-empty strings.');
            }

            if (! is_array($entry)) {
                throw NatsConfigurationException::forConnection($name, 'connection entry must be an array.');
            }

            $this->validateHostPort($name, $entry);
            $this->validateTimeout($name, $entry);

            if ($requireTlsInProduction && $production) {
                $this->validateTlsForProduction($name, $entry);
            }
        }
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function validateHostPort(string $name, array $entry): void
    {
        $host = $entry['host'] ?? '';
        $host = is_string($host) ? trim($host) : '';
        if ($host === '') {
            throw NatsConfigurationException::forConnection($name, 'host must be a non-empty string.');
        }

        $port = $entry['port'] ?? 4222;
        $port = is_int($port) ? $port : (int) $port;
        if ($port < 1 || $port > 65535) {
            throw NatsConfigurationException::forConnection($name, sprintf('port must be between 1 and 65535, got %d.', $port));
        }
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function validateTimeout(string $name, array $entry): void
    {
        $timeout = $entry['timeout'] ?? 1.0;
        $timeout = is_float($timeout) || is_int($timeout) ? (float) $timeout : 1.0;
        if ($timeout <= 0.0) {
            throw NatsConfigurationException::forConnection($name, 'timeout must be greater than 0.');
        }
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function validateTlsForProduction(string $name, array $entry): void
    {
        $ca = $this->nonEmptyString($entry['tlsCaFile'] ?? null);
        $cert = $this->nonEmptyString($entry['tlsCertFile'] ?? null);
        $key = $this->nonEmptyString($entry['tlsKeyFile'] ?? null);
        $handshakeFirst = filter_var($entry['tlsHandshakeFirst'] ?? false, FILTER_VALIDATE_BOOL);

        $hasTrustOrClientCert = $ca !== null || ($cert !== null && $key !== null);

        if (! $hasTrustOrClientCert && ! $handshakeFirst) {
            throw NatsConfigurationException::forConnection(
                $name,
                'TLS is required in production: set tlsCaFile, or tlsCertFile + tlsKeyFile, or tlsHandshakeFirst when connecting to a TLS NATS endpoint.',
            );
        }
    }

    private function nonEmptyString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $s = is_string($value) ? trim($value) : trim((string) $value);

        return $s !== '' ? $s : null;
    }
}
