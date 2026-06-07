<?php

declare(strict_types=1);

use LaravelNats\Outbox\Contracts\NatsOutboxStoreContract;
use LaravelNats\Outbox\NatsOutboxDispatcher;
use LaravelNats\Outbox\NatsOutboxMessage;
use LaravelNats\Publisher\Contracts\NatsPublisherContract;

it('publishes pending outbox messages and marks them published', function (): void {
    $messages = [
        new NatsOutboxMessage('1', 'orders.created', ['id' => 1]),
        new NatsOutboxMessage('2', 'orders.updated', ['id' => 2], connection: 'orders'),
    ];
    $store = new InMemoryOutboxStore($messages);
    $publisher = new RecordingOutboxPublisher;

    $result = (new NatsOutboxDispatcher($publisher))->dispatch($store, limit: 10);

    expect($result->published())->toBe(2)
        ->and($result->failed())->toBe(0)
        ->and($store->published)->toBe(['1', '2'])
        ->and($publisher->subjects)->toBe(['orders.created', 'orders.updated']);
});

it('marks failures and can continue processing', function (): void {
    $messages = [
        new NatsOutboxMessage('1', 'orders.fail', ['id' => 1]),
        new NatsOutboxMessage('2', 'orders.ok', ['id' => 2]),
    ];
    $store = new InMemoryOutboxStore($messages);
    $publisher = new RecordingOutboxPublisher(failSubject: 'orders.fail');

    $result = (new NatsOutboxDispatcher($publisher))->dispatch($store, limit: 10, stopOnFailure: false);

    expect($result->publishedIds)->toBe(['2'])
        ->and($result->failedIds)->toBe(['1'])
        ->and($store->failed)->toBe(['1']);
});

it('stops on the first failure by default', function (): void {
    $messages = [
        new NatsOutboxMessage('1', 'orders.fail', ['id' => 1]),
        new NatsOutboxMessage('2', 'orders.ok', ['id' => 2]),
    ];
    $store = new InMemoryOutboxStore($messages);
    $publisher = new RecordingOutboxPublisher(failSubject: 'orders.fail');

    $result = (new NatsOutboxDispatcher($publisher))->dispatch($store);

    expect($result->publishedIds)->toBe([])
        ->and($result->failedIds)->toBe(['1'])
        ->and($store->published)->toBe([]);
});

final class RecordingOutboxPublisher implements NatsPublisherContract
{
    /**
     * @var list<string>
     */
    public array $subjects = [];

    public function __construct(
        private readonly ?string $failSubject = null,
    ) {}

    public function publish(string $subject, array $payload, array $headers = [], ?string $connection = null): void
    {
        if ($subject === $this->failSubject) {
            throw new RuntimeException('publish failed');
        }

        $this->subjects[] = $subject;
    }
}

final class InMemoryOutboxStore implements NatsOutboxStoreContract
{
    /**
     * @var list<string>
     */
    public array $published = [];

    /**
     * @var list<string>
     */
    public array $failed = [];

    /**
     * @param  list<NatsOutboxMessage>  $messages
     */
    public function __construct(
        private readonly array $messages,
    ) {}

    public function nextBatch(int $limit): iterable
    {
        return array_slice($this->messages, 0, $limit);
    }

    public function markPublished(NatsOutboxMessage $message): void
    {
        $this->published[] = $message->id;
    }

    public function markFailed(NatsOutboxMessage $message, Throwable $exception): void
    {
        $this->failed[] = $message->id;
    }
}
