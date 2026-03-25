<?php

declare(strict_types=1);

namespace LaravelNats\JetStream;

use Basis\Nats\Stream\RetentionPolicy;
use Basis\Nats\Stream\StorageBackend;
use Basis\Nats\Stream\Stream;
use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;
use LaravelNats\Connection\ConnectionManager;

/**
 * Applies documented starter presets from {@see config('nats_basis.jetstream.presets')}.
 */
final class BasisStreamProvisioner
{
    public function __construct(
        private readonly ConnectionManager $connections,
        private readonly Repository $config,
    ) {
    }

    /**
     * @param non-empty-string $presetKey Key under nats_basis.jetstream.presets
     */
    public function provision(string $presetKey, bool $createIfNotExists = true, ?string $connection = null): Stream
    {
        /** @var array<string, array<string, mixed>> $presets */
        $presets = $this->config->get('nats_basis.jetstream.presets', []);
        if (! isset($presets[$presetKey]) || ! is_array($presets[$presetKey])) {
            throw new InvalidArgumentException("Unknown JetStream preset \"{$presetKey}\".");
        }

        $def = $presets[$presetKey];
        $name = isset($def['name']) && is_string($def['name']) && $def['name'] !== '' ? $def['name'] : $presetKey;
        $subjects = $def['subjects'] ?? [];
        if (! is_array($subjects) || $subjects === []) {
            throw new InvalidArgumentException("JetStream preset \"{$presetKey}\" must define non-empty subjects.");
        }

        $manager = new BasisJetStreamManager($this->connections, $connection);
        $stream = $manager->stream($name, $connection);
        $cfg = $stream->getConfiguration();
        /** @var list<string> $subjectList */
        $subjectList = array_values(array_filter($subjects, static fn ($s): bool => is_string($s) && $s !== ''));
        $cfg->setSubjects($subjectList);

        $storage = isset($def['storage']) && is_string($def['storage']) ? $def['storage'] : StorageBackend::FILE;
        $cfg->setStorageBackend(StorageBackend::validate($storage));

        $retention = isset($def['retention']) && is_string($def['retention']) ? $def['retention'] : RetentionPolicy::LIMITS;
        $cfg->setRetentionPolicy(RetentionPolicy::validate($retention));

        if (isset($def['max_bytes']) && is_int($def['max_bytes'])) {
            $cfg->setMaxBytes($def['max_bytes']);
        }

        if ($createIfNotExists) {
            $stream->createIfNotExists();
        } else {
            $stream->create();
        }

        return $stream;
    }
}
