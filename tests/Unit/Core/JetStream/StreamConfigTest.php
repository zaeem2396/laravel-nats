<?php

declare(strict_types=1);

use LaravelNats\Core\JetStream\StreamConfig;

describe('StreamConfig', function (): void {
    describe('constructor', function (): void {
        it('creates instance with name and subjects', function (): void {
            $config = new StreamConfig('test-stream', ['foo.>', 'bar.*']);

            expect($config->getName())->toBe('test-stream');
            expect($config->getSubjects())->toBe(['foo.>', 'bar.*']);
        });

        it('creates instance with empty subjects', function (): void {
            $config = new StreamConfig('test-stream');

            expect($config->getName())->toBe('test-stream');
            expect($config->getSubjects())->toBe([]);
        });

        it('throws exception for empty name', function (): void {
            expect(fn () => new StreamConfig(''))->toThrow(InvalidArgumentException::class);
        });
    });

    describe('fromArray', function (): void {
        it('creates instance from array', function (): void {
            $data = [
                'name' => 'array-stream',
                'subjects' => ['test.>'],
                'description' => 'Test stream',
                'retention' => 'interest',
                'max_messages' => 1000,
                'max_bytes' => 1024000,
                'max_age' => 3600,
                'storage' => 'memory',
                'replicas' => 3,
                'discard' => 'new',
                'duplicate_window' => 120000000000,
                'allow_direct' => true,
            ];

            $config = StreamConfig::fromArray($data);

            expect($config->getName())->toBe('array-stream');
            expect($config->getSubjects())->toBe(['test.>']);
            expect($config->getDescription())->toBe('Test stream');
            expect($config->getRetention())->toBe('interest');
            expect($config->getMaxMessages())->toBe(1000);
            expect($config->getMaxBytes())->toBe(1024000);
            expect($config->getMaxAge())->toBe(3600);
            expect($config->getStorage())->toBe('memory');
            expect($config->getReplicas())->toBe(3);
            expect($config->getDiscard())->toBe('new');
            expect($config->getDuplicateWindow())->toBe(120000000000);
            expect($config->isAllowDirect())->toBeTrue();
        });

        it('throws exception when name is missing', function (): void {
            expect(fn () => StreamConfig::fromArray([]))->toThrow(InvalidArgumentException::class, 'Stream name is required');
        });

        it('uses defaults for missing values', function (): void {
            $config = StreamConfig::fromArray(['name' => 'default-stream']);

            expect($config->getRetention())->toBe(StreamConfig::RETENTION_LIMITS);
            expect($config->getStorage())->toBe(StreamConfig::STORAGE_FILE);
            expect($config->getReplicas())->toBe(1);
            expect($config->getDiscard())->toBe(StreamConfig::DISCARD_OLD);
            expect($config->isAllowDirect())->toBeFalse();
        });
    });

    describe('immutability', function (): void {
        it('returns new instance with updated description', function (): void {
            $config = new StreamConfig('test');
            $newConfig = $config->withDescription('New description');

            expect($newConfig->getDescription())->toBe('New description');
            expect($config->getDescription())->toBeNull();
        });

        it('returns new instance with updated subjects', function (): void {
            $config = new StreamConfig('test', ['old.>']);
            $newConfig = $config->withSubjects(['new.>']);

            expect($newConfig->getSubjects())->toBe(['new.>']);
            expect($config->getSubjects())->toBe(['old.>']);
        });

        it('returns new instance with updated retention', function (): void {
            $config = new StreamConfig('test');
            $newConfig = $config->withRetention(StreamConfig::RETENTION_INTEREST);

            expect($newConfig->getRetention())->toBe(StreamConfig::RETENTION_INTEREST);
            expect($config->getRetention())->toBe(StreamConfig::RETENTION_LIMITS);
        });
    });

    describe('toArray', function (): void {
        it('converts to array with all fields', function (): void {
            $config = new StreamConfig('test-stream', ['foo.>']);
            $config = $config->withDescription('Test description');
            $config = $config->withMaxMessages(100);
            $config = $config->withMaxBytes(1024);
            $config = $config->withMaxAge(3600);
            $config = $config->withDuplicateWindow(120000000000);
            $config = $config->withAllowDirect(true);

            $array = $config->toArray();

            expect($array)->toHaveKey('name');
            expect($array)->toHaveKey('subjects');
            expect($array)->toHaveKey('description');
            expect($array)->toHaveKey('max_messages');
            expect($array)->toHaveKey('max_bytes');
            expect($array)->toHaveKey('max_age');
            expect($array)->toHaveKey('duplicate_window');
            expect($array)->toHaveKey('allow_direct');
            expect($array['name'])->toBe('test-stream');
        });

        it('excludes null optional fields', function (): void {
            $config = new StreamConfig('test-stream');
            $array = $config->toArray();

            expect($array)->not->toHaveKey('description');
            expect($array)->not->toHaveKey('max_messages');
            expect($array)->not->toHaveKey('max_bytes');
            expect($array)->not->toHaveKey('max_age');
            expect($array)->not->toHaveKey('duplicate_window');
        });
    });

    describe('constants', function (): void {
        it('has retention policy constants', function (): void {
            expect(StreamConfig::RETENTION_LIMITS)->toBe('limits');
            expect(StreamConfig::RETENTION_INTEREST)->toBe('interest');
            expect(StreamConfig::RETENTION_WORK_QUEUE)->toBe('workqueue');
        });

        it('has storage type constants', function (): void {
            expect(StreamConfig::STORAGE_FILE)->toBe('file');
            expect(StreamConfig::STORAGE_MEMORY)->toBe('memory');
        });

        it('has discard policy constants', function (): void {
            expect(StreamConfig::DISCARD_OLD)->toBe('old');
            expect(StreamConfig::DISCARD_NEW)->toBe('new');
        });
    });
});
