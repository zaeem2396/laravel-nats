<?php

declare(strict_types=1);

namespace LaravelNats\Outbox;

use LaravelNats\Outbox\Contracts\NatsOutboxStoreContract;
use LaravelNats\Publisher\Contracts\NatsPublisherContract;
use Throwable;

/**
 * Publishes pending outbox rows from an application-provided store.
 */
final class NatsOutboxDispatcher
{
    public function __construct(
        private readonly NatsPublisherContract $publisher,
    ) {
    }

    public function dispatch(NatsOutboxStoreContract $store, int $limit = 100, bool $stopOnFailure = true): NatsOutboxDispatchResult
    {
        $published = [];
        $failed = [];

        foreach ($store->nextBatch(max(1, $limit)) as $message) {
            try {
                $this->publisher->publish(
                    $message->subject,
                    $message->payload,
                    $message->headers,
                    $message->connection,
                );
                $store->markPublished($message);
                $published[] = $message->id;
            } catch (Throwable $e) {
                $store->markFailed($message, $e);
                $failed[] = $message->id;

                if ($stopOnFailure) {
                    break;
                }
            }
        }

        return new NatsOutboxDispatchResult($published, $failed);
    }
}
