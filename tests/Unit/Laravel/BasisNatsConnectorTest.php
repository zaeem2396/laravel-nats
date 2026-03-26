<?php

declare(strict_types=1);

use LaravelNats\Laravel\Queue\BasisNatsConnector;
use LaravelNats\Laravel\Queue\BasisNatsQueue;

describe('BasisNatsConnector', function (): void {
    it('returns BasisNatsQueue with connection config', function (): void {
        $connector = new BasisNatsConnector();
        $queue = $connector->connect([
            'queue' => 'jobs',
            'retry_after' => 120,
            'tries' => 5,
            'prefix' => 'app.queue.',
            'dead_letter_queue' => 'failed',
            'block_for' => 0.25,
        ]);

        expect($queue)->toBeInstanceOf(BasisNatsQueue::class)
            ->and($queue->getQueue())->toBe('jobs')
            ->and($queue->getRetryAfter())->toBe(120)
            ->and($queue->getMaxTries())->toBe(5)
            ->and($queue->getDeadLetterQueueSubject())->toBe('app.queue.failed');
    });

    it('uses full subject when dead_letter_queue contains a dot', function (): void {
        $connector = new BasisNatsConnector();
        $queue = $connector->connect([
            'queue' => 'default',
            'dead_letter_queue' => 'errors.dlq.all',
        ]);

        expect($queue->getDeadLetterQueueSubject())->toBe('errors.dlq.all');
    });
});
