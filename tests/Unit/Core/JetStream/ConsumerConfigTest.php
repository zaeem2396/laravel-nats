<?php

declare(strict_types=1);

use LaravelNats\Core\JetStream\ConsumerConfig;

describe('ConsumerConfig', function (): void {
    describe('constructor', function (): void {
        it('creates instance with null durable name (ephemeral)', function (): void {
            $config = new ConsumerConfig();

            expect($config->getDurableName())->toBeNull();
            expect($config->getDeliverPolicy())->toBe(ConsumerConfig::DELIVER_ALL);
            expect($config->getAckPolicy())->toBe(ConsumerConfig::ACK_EXPLICIT);
            expect($config->getReplayPolicy())->toBe(ConsumerConfig::REPLAY_INSTANT);
        });

        it('creates instance with durable name', function (): void {
            $config = new ConsumerConfig('my-durable');

            expect($config->getDurableName())->toBe('my-durable');
        });

        it('normalises empty string durable name to null', function (): void {
            $config = new ConsumerConfig('');

            expect($config->getDurableName())->toBeNull();
        });
    });

    describe('fromArray', function (): void {
        it('creates instance from array', function (): void {
            $data = [
                'durable_name' => 'test-consumer',
                'filter_subject' => 'orders.>',
                'deliver_policy' => ConsumerConfig::DELIVER_NEW,
                'ack_policy' => ConsumerConfig::ACK_ALL,
                'ack_wait' => 30.0,
                'max_deliver' => 3,
                'replay_policy' => ConsumerConfig::REPLAY_ORIGINAL,
                'deliver_subject' => '_inbox.xyz',
                'opt_start_seq' => 100,
                'opt_start_time' => '2020-01-01T00:00:00Z',
            ];

            $config = ConsumerConfig::fromArray($data);

            expect($config->getDurableName())->toBe('test-consumer');
            expect($config->getFilterSubject())->toBe('orders.>');
            expect($config->getDeliverPolicy())->toBe(ConsumerConfig::DELIVER_NEW);
            expect($config->getAckPolicy())->toBe(ConsumerConfig::ACK_ALL);
            expect($config->getAckWait())->toBe(30.0);
            expect($config->getMaxDeliver())->toBe(3);
            expect($config->getReplayPolicy())->toBe(ConsumerConfig::REPLAY_ORIGINAL);
            expect($config->getDeliverSubject())->toBe('_inbox.xyz');
            expect($config->getOptStartSeq())->toBe(100);
            expect($config->getOptStartTime())->toBe('2020-01-01T00:00:00Z');
        });

        it('uses defaults for missing values', function (): void {
            $config = ConsumerConfig::fromArray(['durable_name' => 'minimal']);

            expect($config->getDeliverPolicy())->toBe(ConsumerConfig::DELIVER_ALL);
            expect($config->getAckPolicy())->toBe(ConsumerConfig::ACK_EXPLICIT);
            expect($config->getReplayPolicy())->toBe(ConsumerConfig::REPLAY_INSTANT);
            expect($config->getFilterSubject())->toBeNull();
            expect($config->getAckWait())->toBeNull();
            expect($config->getMaxDeliver())->toBeNull();
            expect($config->getDeliverSubject())->toBeNull();
            expect($config->getOptStartSeq())->toBeNull();
            expect($config->getOptStartTime())->toBeNull();
        });

        it('accepts empty durable_name for ephemeral', function (): void {
            $config = ConsumerConfig::fromArray([]);

            expect($config->getDurableName())->toBeNull();
        });
    });

    describe('immutability', function (): void {
        it('returns new instance with updated durable name', function (): void {
            $config = new ConsumerConfig('old');
            $newConfig = $config->withDurableName('new');

            expect($newConfig->getDurableName())->toBe('new');
            expect($config->getDurableName())->toBe('old');
        });

        it('returns new instance with updated filter subject', function (): void {
            $config = new ConsumerConfig();
            $newConfig = $config->withFilterSubject('events.>');

            expect($newConfig->getFilterSubject())->toBe('events.>');
            expect($config->getFilterSubject())->toBeNull();
        });

        it('returns new instance with updated deliver policy', function (): void {
            $config = new ConsumerConfig();
            $newConfig = $config->withDeliverPolicy(ConsumerConfig::DELIVER_LAST);

            expect($newConfig->getDeliverPolicy())->toBe(ConsumerConfig::DELIVER_LAST);
            expect($config->getDeliverPolicy())->toBe(ConsumerConfig::DELIVER_ALL);
        });

        it('returns new instance with updated ack policy', function (): void {
            $config = new ConsumerConfig();
            $newConfig = $config->withAckPolicy(ConsumerConfig::ACK_NONE);

            expect($newConfig->getAckPolicy())->toBe(ConsumerConfig::ACK_NONE);
            expect($config->getAckPolicy())->toBe(ConsumerConfig::ACK_EXPLICIT);
        });

        it('returns new instance with updated ack wait', function (): void {
            $config = new ConsumerConfig();
            $newConfig = $config->withAckWait(60.0);

            expect($newConfig->getAckWait())->toBe(60.0);
            expect($config->getAckWait())->toBeNull();
        });
    });

    describe('toArray', function (): void {
        it('converts to array with required and set optional fields', function (): void {
            $config = new ConsumerConfig('dur');
            $config = $config->withFilterSubject('x.>')
                ->withDeliverPolicy(ConsumerConfig::DELIVER_NEW)
                ->withAckPolicy(ConsumerConfig::ACK_ALL)
                ->withAckWait(30.0)
                ->withMaxDeliver(5)
                ->withOptStartSeq(10);

            $array = $config->toArray();

            expect($array['durable_name'])->toBe('dur');
            expect($array['filter_subject'])->toBe('x.>');
            expect($array['deliver_policy'])->toBe('new');
            expect($array['ack_policy'])->toBe('all');
            expect($array['replay_policy'])->toBe('instant');
            expect($array['ack_wait'])->toBe(30_000_000_000);
            expect($array['max_deliver'])->toBe(5);
            expect($array['opt_start_seq'])->toBe(10);
        });

        it('excludes null optional fields', function (): void {
            $config = new ConsumerConfig();

            $array = $config->toArray();

            expect($array)->not->toHaveKey('durable_name');
            expect($array)->not->toHaveKey('filter_subject');
            expect($array)->not->toHaveKey('ack_wait');
            expect($array)->not->toHaveKey('max_deliver');
            expect($array)->not->toHaveKey('deliver_subject');
            expect($array)->not->toHaveKey('opt_start_seq');
            expect($array)->not->toHaveKey('opt_start_time');
        });
    });

    describe('constants', function (): void {
        it('has deliver policy constants', function (): void {
            expect(ConsumerConfig::DELIVER_ALL)->toBe('all');
            expect(ConsumerConfig::DELIVER_LAST)->toBe('last');
            expect(ConsumerConfig::DELIVER_LAST_PER_SUBJECT)->toBe('last_per_subject');
            expect(ConsumerConfig::DELIVER_NEW)->toBe('new');
            expect(ConsumerConfig::DELIVER_BY_START_SEQUENCE)->toBe('by_start_sequence');
            expect(ConsumerConfig::DELIVER_BY_START_TIME)->toBe('by_start_time');
        });

        it('has ack policy constants', function (): void {
            expect(ConsumerConfig::ACK_NONE)->toBe('none');
            expect(ConsumerConfig::ACK_ALL)->toBe('all');
            expect(ConsumerConfig::ACK_EXPLICIT)->toBe('explicit');
        });

        it('has replay policy constants', function (): void {
            expect(ConsumerConfig::REPLAY_INSTANT)->toBe('instant');
            expect(ConsumerConfig::REPLAY_ORIGINAL)->toBe('original');
        });
    });
});
