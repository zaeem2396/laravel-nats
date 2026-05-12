<?php

declare(strict_types=1);

namespace LaravelNats\Outbox;

/**
 * Summary returned by {@see NatsOutboxDispatcher}.
 */
final class NatsOutboxDispatchResult
{
    /**
     * @param list<string> $publishedIds
     * @param list<string> $failedIds
     */
    public function __construct(
        public readonly array $publishedIds,
        public readonly array $failedIds,
    ) {
    }

    public function attempted(): int
    {
        return count($this->publishedIds) + count($this->failedIds);
    }

    public function published(): int
    {
        return count($this->publishedIds);
    }

    public function failed(): int
    {
        return count($this->failedIds);
    }

    public function succeeded(): bool
    {
        return $this->failedIds === [];
    }
}
