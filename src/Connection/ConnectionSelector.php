<?php

declare(strict_types=1);

namespace LaravelNats\Connection;

use Illuminate\Contracts\Config\Repository;

/**
 * Resolves an optional NatsV2 connection name from explicit input or subject prefix rules.
 */
final class ConnectionSelector
{
    public function __construct(
        private readonly Repository $config,
    ) {
    }

    public function select(?string $explicit = null, ?string $subject = null): ?string
    {
        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        if (! is_string($subject) || $subject === '') {
            return null;
        }

        $rules = $this->subjectPrefixRules();
        $bestPrefix = null;
        $bestConnection = null;

        foreach ($rules as $prefix => $connection) {
            if (! str_starts_with($subject, $prefix)) {
                continue;
            }

            if ($bestPrefix === null || strlen($prefix) > strlen($bestPrefix)) {
                $bestPrefix = $prefix;
                $bestConnection = $connection;
            }
        }

        return $bestConnection;
    }

    public function selectForSubject(string $subject, ?string $explicit = null): ?string
    {
        return $this->select($explicit, $subject);
    }

    public function hasRules(): bool
    {
        return $this->subjectPrefixRules() !== [];
    }

    /**
     * @return array<string, string>
     */
    public function subjectPrefixRules(): array
    {
        /** @var mixed $rules */
        $rules = $this->config->get('nats_basis.connection_selection.subject_prefixes', []);
        if (! is_array($rules)) {
            return [];
        }

        $out = [];
        foreach ($rules as $prefix => $connection) {
            if (! is_string($prefix) || $prefix === '' || ! is_string($connection) || $connection === '') {
                continue;
            }
            $out[$prefix] = $connection;
        }

        return $out;
    }
}
