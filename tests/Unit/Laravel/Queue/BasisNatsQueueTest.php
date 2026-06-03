<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use LaravelNats\Connection\ConnectionManager;
use LaravelNats\Laravel\Queue\BasisNatsQueue;

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

    it('manages dead letter queue subject', function (): void {
        $queue = makeBasisQueue(['dead_letter_queue' => 'failed']);

        expect($queue->getDeadLetterQueueSubject())->toBe('failed');

        $queue->setDeadLetterQueueSubject('errors.dlq');
        expect($queue->getDeadLetterQueueSubject())->toBe('errors.dlq');

        $queue->setDeadLetterQueueSubject(null);
        expect($queue->getDeadLetterQueueSubject())->toBeNull();
    });
});
