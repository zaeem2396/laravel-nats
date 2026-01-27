<?php

declare(strict_types=1);

use LaravelNats\Core\JetStream\JetStreamClient;
use LaravelNats\Core\JetStream\StreamConfig;
use LaravelNats\Exceptions\ConnectionException;

/**
 * Helper to create a connected JetStream client for stream tests.
 *
 * Uses the shared helper from TestCase to ensure proper connection handling.
 * This ensures consistent connection behavior across all JetStream tests.
 */
function createStreamTestClient(): JetStreamClient
{
    $client = \LaravelNats\Tests\TestCase::createConnectedJetStreamClient();

    return new JetStreamClient($client);
}

/**
 * Run a test body with a JetStream client, retrying on disconnect.
 *
 * In CI the connection can drop after the previous test disconnects. This helper
 * creates a client, runs the closure, and on ConnectionException waits briefly
 * then retries with a fresh client (up to 3 attempts).
 */
function runWithStreamClientRetry(callable $run): void
{
    $lastException = null;
    $maxAttempts = 3;

    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        if ($attempt > 0) {
            usleep(1500000); // 1.5s before retry so NATS can close previous connection and accept new one
        }

        $js = createStreamTestClient();

        try {
            $run($js);

            return;
        } catch (ConnectionException $e) {
            $lastException = $e;
        } finally {
            try {
                $js->getClient()->disconnect();
            } catch (\Throwable) {
                // ignore
            }
        }
    }

    if ($lastException !== null) {
        throw $lastException;
    }
}

/**
 * Stream subject convention: use $streamName . '.>' per stream so subjects never
 * overlap across streams (avoids JetStream "subjects overlap" API 400). Tests
 * delete their stream in finally so CI stays clean.
 */
describe('Stream Management', function (): void {
    beforeEach(function (): void {
        if (! \LaravelNats\Tests\TestCase::isNatsReachable()) {
            $this->markTestSkipped('NATS server not available');
        }
    });

    describe('stream CRUD operations', function (): void {
        it('creates a stream with basic configuration', function (): void {
            runWithStreamClientRetry(function (JetStreamClient $js): void {
                expect($js->getClient()->isConnected())->toBeTrue();

                $streamName = 'test-stream-' . uniqid();
                $subject = $streamName . '.>';
                $config = new StreamConfig($streamName, [$subject]);
                $info = $js->createStream($config);

                expect($info->getConfig()->getName())->toBe($streamName);
                expect($info->getConfig()->getSubjects())->toBe([$subject]);

                try {
                    $js->deleteStream($streamName);
                } catch (\Throwable) {
                }
            });
        });

        it('gets stream information', function (): void {
            runWithStreamClientRetry(function (JetStreamClient $js): void {
                $streamName = 'info-stream-' . uniqid();
                $subject = $streamName . '.>';
                $config = new StreamConfig($streamName, [$subject]);
                $js->createStream($config);

                $info = $js->getStreamInfo($streamName);

                expect($info->getConfig()->getName())->toBe($streamName);
                expect($info->getConfig()->getSubjects())->toBe([$subject]);

                try {
                    $js->deleteStream($streamName);
                } catch (\Throwable) {
                }
            });
        });

        it('updates stream configuration', function (): void {
            runWithStreamClientRetry(function (JetStreamClient $js): void {
                $streamName = 'update-stream-' . uniqid();
                $subject = $streamName . '.>';
                $config = new StreamConfig($streamName, [$subject]);
                $js->createStream($config);

                $updatedConfig = $config->withDescription('Updated description');
                $updatedConfig = $updatedConfig->withMaxMessages(1000);

                $info = $js->updateStream($updatedConfig);

                expect($info->getConfig()->getDescription())->toBe('Updated description');

                try {
                    $js->deleteStream($streamName);
                } catch (\Throwable) {
                }
            });
        });

        it('deletes a stream', function (): void {
            runWithStreamClientRetry(function (JetStreamClient $js): void {
                $streamName = 'delete-stream-' . uniqid();
                $subject = $streamName . '.>';
                $config = new StreamConfig($streamName, [$subject]);
                $js->createStream($config);

                $deleted = $js->deleteStream($streamName);

                expect($deleted)->toBeTrue();

                expect(fn () => $js->getStreamInfo($streamName))->toThrow(
                    \LaravelNats\Exceptions\NatsException::class,
                );

                try {
                    $js->deleteStream($streamName);
                } catch (\Throwable) {
                }
            });
        });

        it('purges all messages from a stream', function (): void {
            runWithStreamClientRetry(function (JetStreamClient $js): void {
                $streamName = 'purge-stream-' . uniqid();
                $subject = $streamName . '.>';
                $config = new StreamConfig($streamName, [$subject]);
                $js->createStream($config);

                // Publish to the stream's subject so messages are captured
                $client = $js->getClient();
                $client->publish($streamName . '.evt', ['message' => 1]);
                $client->publish($streamName . '.evt', ['message' => 2]);
                $client->publish($streamName . '.evt', ['message' => 3]);

                usleep(100000); // 100ms for messages to be stored

                $infoBefore = $js->getStreamInfo($streamName);
                expect($infoBefore->getMessageCount())->toBeGreaterThan(0);

                $purged = $js->purgeStream($streamName);
                expect($purged)->toBeTrue();

                usleep(100000); // 100ms for purge to complete

                $infoAfter = $js->getStreamInfo($streamName);
                expect($infoAfter->getMessageCount())->toBe(0);

                try {
                    $js->deleteStream($streamName);
                } catch (\Throwable) {
                }
            });
        });
    });

    describe('stream operations', function (): void {
        it('gets a message by sequence number', function (): void {
            runWithStreamClientRetry(function (JetStreamClient $js): void {
                $streamName = 'get-msg-stream-' . uniqid();
                $subject = $streamName . '.>';
                $config = new StreamConfig($streamName, [$subject]);
                $js->createStream($config);

                // Publish to the stream's subject
                $client = $js->getClient();
                $client->publish($streamName . '.evt', ['test' => 'data']);

                usleep(100000); // 100ms for message to be stored

                $info = $js->getStreamInfo($streamName);
                $lastSeq = $info->getLastSequence();

                if ($lastSeq !== null) {
                    $message = $js->getMessage($streamName, $lastSeq);

                    expect($message)->toHaveKey('message');
                    // JetStream API returns message data as base64
                    $data = $message['message']['data'] ?? '';
                    expect(base64_decode($data, true) ?: $data)->toBe('{"test":"data"}');
                }

                try {
                    $js->deleteStream($streamName);
                } catch (\Throwable) {
                }
            });
        });

        it('deletes a message by sequence number', function (): void {
            runWithStreamClientRetry(function (JetStreamClient $js): void {
                $streamName = 'del-msg-stream-' . uniqid();
                $subject = $streamName . '.>';
                $config = new StreamConfig($streamName, [$subject]);
                $js->createStream($config);

                // Publish to the stream's subject
                $client = $js->getClient();
                $client->publish($streamName . '.evt', ['test' => 'data']);

                usleep(100000); // 100ms for message to be stored

                $infoBefore = $js->getStreamInfo($streamName);
                $lastSeq = $infoBefore->getLastSequence();

                if ($lastSeq !== null) {
                    $deleted = $js->deleteMessage($streamName, $lastSeq);
                    expect($deleted)->toBeTrue();

                    usleep(100000); // 100ms for deletion to complete

                    $infoAfter = $js->getStreamInfo($streamName);
                    expect($infoAfter->getMessageCount())->toBeLessThan($infoBefore->getMessageCount());
                }

                try {
                    $js->deleteStream($streamName);
                } catch (\Throwable) {
                }
            });
        });
    });

    describe('stream configuration options', function (): void {
        it('creates stream with all configuration options', function (): void {
            runWithStreamClientRetry(function (JetStreamClient $js): void {
                $streamName = 'full-config-stream-' . uniqid();
                $subject = $streamName . '.>';
                $config = new StreamConfig($streamName, [$subject]);
                $config = $config->withDescription('Full configuration test');
                $config = $config->withRetention(StreamConfig::RETENTION_INTEREST);
                $config = $config->withMaxMessages(1000);
                $config = $config->withMaxBytes(1024000);
                $config = $config->withMaxAge(3600);
                $config = $config->withStorage(StreamConfig::STORAGE_MEMORY);
                $config = $config->withReplicas(1);
                $config = $config->withDiscard(StreamConfig::DISCARD_NEW);
                $config = $config->withAllowDirect(true);

                $info = $js->createStream($config);

                expect($info->getConfig()->getName())->toBe($streamName);
                expect($info->getConfig()->getSubjects())->toBe([$subject]);
                expect($info->getConfig()->getDescription())->toBe('Full configuration test');
                expect($info->getConfig()->getRetention())->toBe(StreamConfig::RETENTION_INTEREST);
                expect($info->getConfig()->getStorage())->toBe(StreamConfig::STORAGE_MEMORY);
                expect($info->getConfig()->isAllowDirect())->toBeTrue();

                $verifyInfo = $js->getStreamInfo($streamName);
                expect($verifyInfo->getConfig()->getName())->toBe($streamName);

                try {
                    $js->deleteStream($streamName);
                } catch (\Throwable) {
                }
            });
        });
    });
});
