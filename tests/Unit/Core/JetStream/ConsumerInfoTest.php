<?php

declare(strict_types=1);

use LaravelNats\Core\JetStream\ConsumerConfig;
use LaravelNats\Core\JetStream\ConsumerInfo;

describe('ConsumerInfo', function (): void {
    describe('constructor', function (): void {
        it('creates instance with stream name, consumer name, config and state', function (): void {
            $config = new ConsumerConfig('my-consumer');
            $state = ['num_pending' => 10, 'num_ack_pending' => 2, 'num_waiting' => 1];
            $info = new ConsumerInfo('my-stream', 'my-consumer', $config, $state);

            expect($info->getStreamName())->toBe('my-stream');
            expect($info->getName())->toBe('my-consumer');
            expect($info->getConfig())->toBe($config);
            expect($info->getState())->toBe($state);
        });

        it('creates instance with empty state', function (): void {
            $config = new ConsumerConfig('c');
            $info = new ConsumerInfo('s', 'c', $config);

            expect($info->getState())->toBe([]);
        });
    });

    describe('fromArray', function (): void {
        it('creates instance from API response', function (): void {
            $data = [
                'stream_name' => 'orders',
                'name' => 'shipments',
                'config' => [
                    'durable_name' => 'shipments',
                    'filter_subject' => 'orders.shipped',
                ],
                'state' => [
                    'num_pending' => 5,
                    'num_ack_pending' => 1,
                    'num_waiting' => 0,
                ],
            ];

            $info = ConsumerInfo::fromArray($data);

            expect($info->getStreamName())->toBe('orders');
            expect($info->getName())->toBe('shipments');
            expect($info->getConfig()->getDurableName())->toBe('shipments');
            expect($info->getConfig()->getFilterSubject())->toBe('orders.shipped');
            expect($info->getNumPending())->toBe(5);
            expect($info->getNumAckPending())->toBe(1);
            expect($info->getNumWaiting())->toBe(0);
        });

        it('handles missing state fields', function (): void {
            $data = [
                'stream_name' => 'minimal',
                'name' => 'min-consumer',
                'config' => ['durable_name' => 'min-consumer'],
                'state' => [],
            ];

            $info = ConsumerInfo::fromArray($data);

            expect($info->getNumPending())->toBe(0);
            expect($info->getNumAckPending())->toBe(0);
            expect($info->getNumWaiting())->toBe(0);
        });
    });

    describe('state accessors', function (): void {
        it('returns num_pending from state', function (): void {
            $config = new ConsumerConfig('c');
            $state = ['num_pending' => 42];
            $info = new ConsumerInfo('s', 'c', $config, $state);

            expect($info->getNumPending())->toBe(42);
        });

        it('returns num_ack_pending from state', function (): void {
            $config = new ConsumerConfig('c');
            $state = ['num_ack_pending' => 7];
            $info = new ConsumerInfo('s', 'c', $config, $state);

            expect($info->getNumAckPending())->toBe(7);
        });

        it('returns num_waiting from state', function (): void {
            $config = new ConsumerConfig('c');
            $state = ['num_waiting' => 3];
            $info = new ConsumerInfo('s', 'c', $config, $state);

            expect($info->getNumWaiting())->toBe(3);
        });
    });

    describe('toArray', function (): void {
        it('converts to array with stream_name, name, config and state', function (): void {
            $config = (new ConsumerConfig('dur'))->withFilterSubject('x.>');
            $state = ['num_pending' => 100];
            $info = new ConsumerInfo('stream-one', 'dur', $config, $state);

            $array = $info->toArray();

            expect($array['stream_name'])->toBe('stream-one');
            expect($array['name'])->toBe('dur');
            expect($array['config'])->toBeArray();
            expect($array['config']['durable_name'])->toBe('dur');
            expect($array['config']['filter_subject'])->toBe('x.>');
            expect($array['state'])->toBe($state);
        });
    });
});
