<?php

declare(strict_types=1);

use LaravelNats\Core\JetStream\StreamConfig;
use LaravelNats\Core\JetStream\StreamInfo;

describe('StreamInfo', function (): void {
    describe('constructor', function (): void {
        it('creates instance with config and state', function (): void {
            $config = new StreamConfig('test-stream', ['foo.>']);
            $state = ['messages' => 100, 'bytes' => 1024];
            $info = new StreamInfo($config, $state);

            expect($info->getConfig())->toBe($config);
            expect($info->getState())->toBe($state);
        });

        it('creates instance with empty state', function (): void {
            $config = new StreamConfig('test-stream');
            $info = new StreamInfo($config);

            expect($info->getState())->toBe([]);
        });
    });

    describe('fromArray', function (): void {
        it('creates instance from API response', function (): void {
            $data = [
                'config' => [
                    'name' => 'api-stream',
                    'subjects' => ['test.>'],
                    'retention' => 'limits',
                ],
                'state' => [
                    'messages' => 50,
                    'bytes' => 512,
                    'first_seq' => 1,
                    'last_seq' => 50,
                    'consumer_count' => 2,
                ],
            ];

            $info = StreamInfo::fromArray($data);

            expect($info->getConfig()->getName())->toBe('api-stream');
            expect($info->getMessageCount())->toBe(50);
            expect($info->getByteCount())->toBe(512);
            expect($info->getFirstSequence())->toBe(1);
            expect($info->getLastSequence())->toBe(50);
            expect($info->getConsumerCount())->toBe(2);
        });

        it('handles missing state fields', function (): void {
            $data = [
                'config' => ['name' => 'minimal-stream'],
                'state' => [],
            ];

            $info = StreamInfo::fromArray($data);

            expect($info->getMessageCount())->toBe(0);
            expect($info->getByteCount())->toBe(0);
            expect($info->getFirstSequence())->toBeNull();
            expect($info->getLastSequence())->toBeNull();
            expect($info->getConsumerCount())->toBe(0);
        });
    });

    describe('state accessors', function (): void {
        it('returns message count from state', function (): void {
            $config = new StreamConfig('test');
            $state = ['messages' => 123];
            $info = new StreamInfo($config, $state);

            expect($info->getMessageCount())->toBe(123);
        });

        it('returns byte count from state', function (): void {
            $config = new StreamConfig('test');
            $state = ['bytes' => 45678];
            $info = new StreamInfo($config, $state);

            expect($info->getByteCount())->toBe(45678);
        });

        it('returns first sequence from state', function (): void {
            $config = new StreamConfig('test');
            $state = ['first_seq' => 10];
            $info = new StreamInfo($config, $state);

            expect($info->getFirstSequence())->toBe(10);
        });

        it('returns last sequence from state', function (): void {
            $config = new StreamConfig('test');
            $state = ['last_seq' => 100];
            $info = new StreamInfo($config, $state);

            expect($info->getLastSequence())->toBe(100);
        });

        it('returns consumer count from state', function (): void {
            $config = new StreamConfig('test');
            $state = ['consumer_count' => 5];
            $info = new StreamInfo($config, $state);

            expect($info->getConsumerCount())->toBe(5);
        });
    });

    describe('toArray', function (): void {
        it('converts to array with config and state', function (): void {
            $config = new StreamConfig('test-stream', ['foo.>']);
            $state = ['messages' => 100];
            $info = new StreamInfo($config, $state);

            $array = $info->toArray();

            expect($array)->toHaveKey('config');
            expect($array)->toHaveKey('state');
            expect($array['config']['name'])->toBe('test-stream');
            expect($array['state']['messages'])->toBe(100);
        });
    });
});
