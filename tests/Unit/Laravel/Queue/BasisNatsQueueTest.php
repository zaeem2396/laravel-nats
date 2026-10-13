<?php

declare(strict_types=1);

use Basis\Nats\Client;
use Basis\Nats\Message\Payload;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use LaravelNats\Connection\ConnectionManager;
use LaravelNats\Laravel\Queue\BasisNatsQueue;
use Mockery\MockInterface;

function makeBasisQueue(array $overrides = []): BasisNatsQueue
{
    $connections = new ConnectionManager(new Repository([
        'nats_basis' => [
            'default' => 'default',
            'connections' => [
                'default' => ['host' => '127.0.0.1', 'port' => 4222],
            ],
        ],
    ]));

    return new BasisNatsQueue(
        $connections,
        'default',
        $overrides['queue'] ?? 'jobs',
        $overrides['retry_after'] ?? 90,
        $overrides['tries'] ?? 3,
        $overrides['dead_letter_queue'] ?? null,
        $overrides['prefix'] ?? 'laravel.queue.',
        $overrides['block_for'] ?? 0.1,
        $overrides['max_in_flight'] ?? null,
    );
}

/**
 * Swap the queue's basis client for a mock whose process() delivers $body once to the active subscription.
 */
function fakeBasisClientDelivering(BasisNatsQueue $queue, string $body): MockInterface
{
    $handler = null;
    $client = Mockery::spy(Client::class);
    $client->shouldReceive('subscribe')->andReturnUsing(function (string $subject, callable $cb) use (&$handler, $client) {
        $handler = $cb;

        return $client;
    });
    $client->shouldReceive('process')->andReturnUsing(function () use (&$handler, &$body) {
        if ($handler !== null && $body !== null) {
            $handler(new Payload($body), null);
            $body = null;
        }

        return null;
    });

    $connections = (new ReflectionProperty(BasisNatsQueue::class, 'connections'))->getValue($queue);
    (new ReflectionProperty(ConnectionManager::class, 'clients'))->setValue($connections, ['default' => $client]);

    return $client;
}

describe('BasisNatsQueue', function (): void {
    beforeEach(function (): void {
        $this->queue = makeBasisQueue();
        $this->queue->setContainer(new Container);
    });

    it('returns queue name and retry settings', function (): void {
        expect($this->queue->getQueue())->toBe('jobs')
            ->and($this->queue->getQueue('custom'))->toBe('custom')
            ->and($this->queue->getRetryAfter())->toBe(90)
            ->and($this->queue->getMaxTries())->toBe(3);
    });

    it('reports zero pending and delayed sizes', function (): void {
        expect($this->queue->size())->toBe(0)
            ->and($this->queue->pendingSize())->toBe(0)
            ->and($this->queue->delayedSize())->toBe(0)
            ->and($this->queue->creationTimeOfOldestPendingJob())->toBeNull();
    });

    it('tracks reserved size from in-flight counter', function (): void {
        $ref = new ReflectionProperty(BasisNatsQueue::class, 'inFlight');
        $ref->setAccessible(true);
        $ref->setValue($this->queue, 3);

        expect($this->queue->reservedSize())->toBe(3);
    });

    it('returns null from pop when in-flight cap is reached', function (): void {
        $queue = makeBasisQueue(['max_in_flight' => 1]);
        $ref = new ReflectionProperty(BasisNatsQueue::class, 'inFlight');
        $ref->setAccessible(true);
        $ref->setValue($queue, 1);

        expect($queue->pop())->toBeNull();
    });

    it('counts popped jobs as in flight until they are handled', function (): void {
        $queue = makeBasisQueue(['max_in_flight' => 1]);
        $queue->setContainer(new Container);
        $queue->setConnectionName('nats_basis');
        $client = fakeBasisClientDelivering($queue, '{"uuid":"job-1","job":"X","data":[]}');

        $job = $queue->pop();

        expect($job)->not->toBeNull()
            ->and($queue->getInFlightCount())->toBe(1)
            ->and($queue->reservedSize())->toBe(1)
            ->and($queue->pop())->toBeNull();

        $job->delete();

        expect($queue->getInFlightCount())->toBe(0);
        $client->shouldHaveReceived('subscribe')->once();
    });

    it('republishes the original payload when a job is released with a delay', function (): void {
        $queue = makeBasisQueue();
        $queue->setContainer(new Container);
        $queue->setConnectionName('nats_basis');
        $client = fakeBasisClientDelivering($queue, '{"uuid":"job-1","job":"App\\\\Jobs\\\\Ping","data":[],"attempts":1}');

        $queue->pop()->release(30);

        $client->shouldHaveReceived('publish')->once()->with('laravel.queue.jobs', Mockery::on(
            fn (string $raw): bool => json_decode($raw, true) === [
                'uuid' => 'job-1',
                'job' => 'App\\Jobs\\Ping',
                'data' => [],
                'attempts' => 2,
            ],
        ));
    });

    it('manages dead letter queue subject', function (): void {
        $queue = makeBasisQueue(['dead_letter_queue' => 'failed']);

        expect($queue->getDeadLetterQueueSubject())->toBe('failed');

        $queue->setDeadLetterQueueSubject('errors.dlq');
        expect($queue->getDeadLetterQueueSubject())->toBe('errors.dlq');

        $queue->setDeadLetterQueueSubject(null);
        expect($queue->getDeadLetterQueueSubject())->toBeNull();
    });
});
