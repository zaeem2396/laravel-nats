<?php

declare(strict_types=1);

/**
 * ============================================================================
 * PUBLISH/SUBSCRIBE INTEGRATION TESTS
 * ============================================================================
 *
 * These tests verify the pub/sub messaging pattern works correctly
 * with a real NATS server.
 *
 * Key scenarios tested:
 * - Basic publish and subscribe
 * - Wildcard subscriptions
 * - Queue groups for load balancing
 * - Request/Reply pattern
 * ============================================================================
 */

use LaravelNats\Core\Messaging\Message;

describe('basic pub/sub', function (): void {
    it('receives published message', function (): void {
        $client = testClient();
        $subject = $this->uniqueSubject();
        $received = null;

        $client->subscribe($subject, function (Message $msg) use (&$received): void {
            $received = $msg;
        });

        $client->publish($subject, ['test' => 'data']);

        // Process messages
        $client->process(0.5);

        expect($received)->not->toBeNull()
            ->and($received->getSubject())->toBe($subject)
            ->and($received->getDecodedPayload())->toBe(['test' => 'data']);

        $client->disconnect();
    });

    it('supports multiple subscribers', function (): void {
        $client = testClient();
        $subject = $this->uniqueSubject();
        $count = 0;

        // Two subscribers
        $client->subscribe($subject, function () use (&$count): void {
            $count++;
        });
        $client->subscribe($subject, function () use (&$count): void {
            $count++;
        });

        $client->publish($subject, 'test');
        $client->process(0.5);

        // Both should receive the message
        expect($count)->toBe(2);

        $client->disconnect();
    });

    it('handles high message volume', function (): void {
        $client = testClient();
        $subject = $this->uniqueSubject();
        $received = 0;
        $expected = 100;

        $client->subscribe($subject, function () use (&$received): void {
            $received++;
        });

        for ($i = 0; $i < $expected; $i++) {
            $client->publish($subject, ['index' => $i]);
        }

        // Process all messages
        $deadline = microtime(true) + 2.0;
        while ($received < $expected && microtime(true) < $deadline) {
            $client->process(0.1);
        }

        expect($received)->toBe($expected);

        $client->disconnect();
    });
});

describe('wildcard subscriptions', function (): void {
    it('matches single token wildcard', function (): void {
        $client = testClient();
        $prefix = $this->uniqueSubject('orders');
        $received = [];

        $client->subscribe($prefix . '.*', function (Message $msg) use (&$received): void {
            $received[] = $msg->getSubject();
        });

        $client->publish($prefix . '.created', 'test1');
        $client->publish($prefix . '.updated', 'test2');
        $client->publish($prefix . '.deleted', 'test3');

        $client->process(0.5);

        expect($received)->toContain($prefix . '.created')
            ->and($received)->toContain($prefix . '.updated')
            ->and($received)->toContain($prefix . '.deleted');

        $client->disconnect();
    });

    it('matches multi-token wildcard', function (): void {
        $client = testClient();
        $prefix = $this->uniqueSubject('events');
        $received = [];

        $client->subscribe($prefix . '.>', function (Message $msg) use (&$received): void {
            $received[] = $msg->getSubject();
        });

        $client->publish($prefix . '.user.created', 'test1');
        $client->publish($prefix . '.order.item.added', 'test2');

        $client->process(0.5);

        expect($received)->toContain($prefix . '.user.created')
            ->and($received)->toContain($prefix . '.order.item.added');

        $client->disconnect();
    });
});

describe('unsubscribe', function (): void {
    it('stops receiving after unsubscribe', function (): void {
        $client = testClient();
        $subject = $this->uniqueSubject();
        $received = 0;

        $sid = $client->subscribe($subject, function () use (&$received): void {
            $received++;
        });

        $client->publish($subject, 'first');
        $client->process(0.3);

        expect($received)->toBe(1);

        $client->unsubscribe($sid);

        $client->publish($subject, 'second');
        $client->process(0.3);

        // Should still be 1 after unsubscribe
        expect($received)->toBe(1);

        $client->disconnect();
    });

    it('supports auto-unsubscribe after N messages', function (): void {
        $client = testClient();
        $subject = $this->uniqueSubject();
        $received = 0;

        $sid = $client->subscribe($subject, function () use (&$received): void {
            $received++;
        });

        // Auto-unsubscribe after 2 messages
        $client->unsubscribe($sid, 2);

        $client->publish($subject, 'one');
        $client->publish($subject, 'two');
        $client->publish($subject, 'three');

        $client->process(0.5);

        expect($received)->toBe(2);

        $client->disconnect();
    });
});

describe('request reply', function (): void {
    it('receives reply to request', function (): void {
        $client = testClient();
        $subject = $this->uniqueSubject('api');

        // Set up responder
        $client->subscribe($subject, function (Message $msg) use ($client): void {
            if ($msg->expectsReply()) {
                $client->publishRaw(
                    $msg->getReplyTo(),
                    json_encode(['response' => 'ok']),
                );
            }
        });

        // Make request
        $response = $client->request($subject, ['action' => 'test'], 2.0);

        expect($response)->not->toBeNull()
            ->and($response->getDecodedPayload())->toBe(['response' => 'ok']);

        $client->disconnect();
    });

    it('throws timeout when no reply', function (): void {
        $client = testClient();
        $subject = $this->uniqueSubject('timeout');

        // No responder set up

        expect(fn () => $client->request($subject, 'test', 0.5))
            ->toThrow(\LaravelNats\Exceptions\TimeoutException::class);

        $client->disconnect();
    });
});

describe('queue groups', function (): void {
    it('distributes messages across queue subscribers', function (): void {
        // Use two separate clients for queue group testing
        $client1 = testClient();
        $client2 = testClient();
        $subject = $this->uniqueSubject('queue');
        $queue = 'workers';
        $count1 = 0;
        $count2 = 0;

        $client1->queueSubscribe($subject, $queue, function () use (&$count1): void {
            $count1++;
        });

        $client2->queueSubscribe($subject, $queue, function () use (&$count2): void {
            $count2++;
        });

        // Publish multiple messages
        for ($i = 0; $i < 10; $i++) {
            $client1->publish($subject, ['index' => $i]);
        }

        // Process on both clients
        $deadline = microtime(true) + 2.0;
        while (($count1 + $count2) < 10 && microtime(true) < $deadline) {
            $client1->process(0.05);
            $client2->process(0.05);
        }

        // Total should be 10 (distributed between the two)
        expect($count1 + $count2)->toBe(10);

        $client1->disconnect();
        $client2->disconnect();
    });
});
