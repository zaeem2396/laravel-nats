<?php

declare(strict_types=1);

namespace LaravelNats\Core\JetStream;

use InvalidArgumentException;

/**
 * StreamConfig holds configuration for a JetStream stream.
 *
 * This class encapsulates all configuration options for creating
 * or updating a JetStream stream, including retention policies,
 * storage options, and limits.
 */
final class StreamConfig
{
    /**
     * Retention policy: limits (default).
     */
    public const RETENTION_LIMITS = 'limits';

    /**
     * Retention policy: interest.
     */
    public const RETENTION_INTEREST = 'interest';

    /**
     * Retention policy: work queue.
     */
    public const RETENTION_WORK_QUEUE = 'workqueue';

    /**
     * Storage type: file (persistent).
     */
    public const STORAGE_FILE = 'file';

    /**
     * Storage type: memory (ephemeral).
     */
    public const STORAGE_MEMORY = 'memory';

    /**
     * Discard policy: old (default).
     */
    public const DISCARD_OLD = 'old';

    /**
     * Discard policy: new.
     */
    public const DISCARD_NEW = 'new';

    /**
     * Stream name.
     */
    private string $name;

    /**
     * Stream description.
     */
    private ?string $description = null;

    /**
     * Subject patterns this stream will capture.
     *
     * @var array<int, string>
     */
    private array $subjects = [];

    /**
     * Retention policy.
     */
    private string $retention = self::RETENTION_LIMITS;

    /**
     * Maximum number of messages to keep.
     */
    private ?int $maxMessages = null;

    /**
     * Maximum bytes to store.
     */
    private ?int $maxBytes = null;

    /**
     * Maximum age of messages (seconds).
     */
    private ?int $maxAge = null;

    /**
     * Storage type.
     */
    private string $storage = self::STORAGE_FILE;

    /**
     * Number of replicas (1 = no replication).
     */
    private int $replicas = 1;

    /**
     * Discard policy when limits are reached.
     */
    private string $discard = self::DISCARD_OLD;

    /**
     * Duplicate detection window (nanoseconds).
     */
    private ?int $duplicateWindow = null;

    /**
     * Allow direct message access.
     */
    private bool $allowDirect = false;

    /**
     * Create a new stream configuration.
     *
     * @param string $name Stream name
     * @param array<int, string> $subjects Subject patterns
     */
    public function __construct(string $name, array $subjects = [])
    {
        if ($name === '') {
            throw new InvalidArgumentException('Stream name cannot be empty');
        }

        $this->name = $name;
        $this->subjects = $subjects;
    }

    /**
     * Create configuration from array.
     *
     * @param array<string, mixed> $data Configuration data
     *
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $name = $data['name'] ?? throw new InvalidArgumentException('Stream name is required');
        $subjects = $data['subjects'] ?? [];

        $config = new self($name, $subjects);

        if (isset($data['description'])) {
            $config->description = (string) $data['description'];
        }

        if (isset($data['retention'])) {
            $config->retention = (string) $data['retention'];
        }

        if (isset($data['max_messages'])) {
            $config->maxMessages = (int) $data['max_messages'];
        }

        if (isset($data['max_bytes'])) {
            $config->maxBytes = (int) $data['max_bytes'];
        }

        if (isset($data['max_age'])) {
            $config->maxAge = (int) $data['max_age'];
        }

        if (isset($data['storage'])) {
            $config->storage = (string) $data['storage'];
        }

        if (isset($data['replicas'])) {
            $config->replicas = (int) $data['replicas'];
        }

        if (isset($data['discard'])) {
            $config->discard = (string) $data['discard'];
        }

        if (isset($data['duplicate_window'])) {
            $config->duplicateWindow = (int) $data['duplicate_window'];
        }

        if (isset($data['allow_direct'])) {
            $config->allowDirect = (bool) $data['allow_direct'];
        }

        return $config;
    }

    /**
     * Get the stream name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the description.
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Set the description.
     *
     * @param string|null $description
     *
     * @return self
     */
    public function withDescription(?string $description): self
    {
        $new = clone $this;
        $new->description = $description;

        return $new;
    }

    /**
     * Get the subjects.
     *
     * @return array<int, string>
     */
    public function getSubjects(): array
    {
        return $this->subjects;
    }

    /**
     * Set the subjects.
     *
     * @param array<int, string> $subjects
     *
     * @return self
     */
    public function withSubjects(array $subjects): self
    {
        $new = clone $this;
        $new->subjects = $subjects;

        return $new;
    }

    /**
     * Get the retention policy.
     *
     * @return string
     */
    public function getRetention(): string
    {
        return $this->retention;
    }

    /**
     * Set the retention policy.
     *
     * @param string $retention
     *
     * @return self
     */
    public function withRetention(string $retention): self
    {
        $new = clone $this;
        $new->retention = $retention;

        return $new;
    }

    /**
     * Get max messages.
     *
     * @return int|null
     */
    public function getMaxMessages(): ?int
    {
        return $this->maxMessages;
    }

    /**
     * Set max messages.
     *
     * @param int|null $maxMessages
     *
     * @return self
     */
    public function withMaxMessages(?int $maxMessages): self
    {
        $new = clone $this;
        $new->maxMessages = $maxMessages;

        return $new;
    }

    /**
     * Get max bytes.
     *
     * @return int|null
     */
    public function getMaxBytes(): ?int
    {
        return $this->maxBytes;
    }

    /**
     * Set max bytes.
     *
     * @param int|null $maxBytes
     *
     * @return self
     */
    public function withMaxBytes(?int $maxBytes): self
    {
        $new = clone $this;
        $new->maxBytes = $maxBytes;

        return $new;
    }

    /**
     * Get max age (seconds).
     *
     * @return int|null
     */
    public function getMaxAge(): ?int
    {
        return $this->maxAge;
    }

    /**
     * Set max age (seconds).
     *
     * @param int|null $maxAge
     *
     * @return self
     */
    public function withMaxAge(?int $maxAge): self
    {
        $new = clone $this;
        $new->maxAge = $maxAge;

        return $new;
    }

    /**
     * Get storage type.
     *
     * @return string
     */
    public function getStorage(): string
    {
        return $this->storage;
    }

    /**
     * Set storage type.
     *
     * @param string $storage
     *
     * @return self
     */
    public function withStorage(string $storage): self
    {
        $new = clone $this;
        $new->storage = $storage;

        return $new;
    }

    /**
     * Get number of replicas.
     *
     * @return int
     */
    public function getReplicas(): int
    {
        return $this->replicas;
    }

    /**
     * Set number of replicas.
     *
     * @param int $replicas
     *
     * @return self
     */
    public function withReplicas(int $replicas): self
    {
        $new = clone $this;
        $new->replicas = $replicas;

        return $new;
    }

    /**
     * Get discard policy.
     *
     * @return string
     */
    public function getDiscard(): string
    {
        return $this->discard;
    }

    /**
     * Set discard policy.
     *
     * @param string $discard
     *
     * @return self
     */
    public function withDiscard(string $discard): self
    {
        $new = clone $this;
        $new->discard = $discard;

        return $new;
    }

    /**
     * Get duplicate window (nanoseconds).
     *
     * @return int|null
     */
    public function getDuplicateWindow(): ?int
    {
        return $this->duplicateWindow;
    }

    /**
     * Set duplicate window (nanoseconds).
     *
     * @param int|null $duplicateWindow
     *
     * @return self
     */
    public function withDuplicateWindow(?int $duplicateWindow): self
    {
        $new = clone $this;
        $new->duplicateWindow = $duplicateWindow;

        return $new;
    }

    /**
     * Check if direct access is allowed.
     *
     * @return bool
     */
    public function isAllowDirect(): bool
    {
        return $this->allowDirect;
    }

    /**
     * Set allow direct access.
     *
     * @param bool $allowDirect
     *
     * @return self
     */
    public function withAllowDirect(bool $allowDirect): self
    {
        $new = clone $this;
        $new->allowDirect = $allowDirect;

        return $new;
    }

    /**
     * Convert to array for API requests.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'subjects' => $this->subjects,
            'retention' => $this->retention,
            'storage' => $this->storage,
            'replicas' => $this->replicas,
            'discard' => $this->discard,
            'allow_direct' => $this->allowDirect,
        ];

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->maxMessages !== null) {
            $data['max_messages'] = $this->maxMessages;
        }

        if ($this->maxBytes !== null) {
            $data['max_bytes'] = $this->maxBytes;
        }

        if ($this->maxAge !== null) {
            $data['max_age'] = $this->maxAge;
        }

        if ($this->duplicateWindow !== null) {
            $data['duplicate_window'] = $this->duplicateWindow;
        }

        return $data;
    }
}
