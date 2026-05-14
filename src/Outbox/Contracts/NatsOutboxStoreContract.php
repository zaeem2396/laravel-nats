<?php

declare(strict_types=1);

namespace LaravelNats\Outbox\Contracts;

use LaravelNats\Outbox\NatsOutboxMessage;
use Throwable;

/**
 * Application-provided persistence contract for the optional NatsV2 outbox dispatcher.
 */
interface NatsOutboxStoreContract
{
    /**
     * @return iterable<NatsOutboxMessage>
     */
    public function nextBatch(int $limit): iterable;

    public function markPublished(NatsOutboxMessage $message): void;

    public function markFailed(NatsOutboxMessage $message, Throwable $exception): void;
}
