<?php

declare(strict_types=1);

use LaravelNats\Core\JetStream\ConsumerConfig;
use LaravelNats\Core\JetStream\ConsumerInfo;
use LaravelNats\Core\JetStream\JetStreamClient;
use LaravelNats\Core\JetStream\StreamConfig;
use LaravelNats\Exceptions\ConnectionException;

/**
 * Helper to create a connected JetStream client for consumer tests.
 */
function createConsumerTestClient(): JetStreamClient
{
    $client = \LaravelNats\Tests\TestCase::createConnectedJetStreamClient();

    return new JetStreamClient($client);
}

/**
 * Run a test body with a JetStream client, retrying on disconnect.
 */
function runWithConsumerClientRetry(callable $run): void
{
    $lastException = null;
    $maxAttempts = 3;

    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        if ($attempt > 0) {
            usleep(1500000);
        }

        $js = createConsumerTestClient();

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

describe('Consumer Management', function (): void {
    beforeEach(function (): void {
        if (! \LaravelNats\Tests\TestCase::isNatsReachable()) {
            $this->markTestSkipped('NATS server not available');
        }
    });

    describe('consumer CRUD operations', function (): void {
        it('creates a durable consumer on a stream', function (): void {
            runWithConsumerClientRetry(function (JetStreamClient $js): void {
                $streamName = 'consumer-stream-' . uniqid();
                $subject = $streamName . '.>';
                $streamConfig = new StreamConfig($streamName, [$subject]);
                $js->createStream($streamConfig);

                $consumerName = 'test-consumer-' . uniqid();
                $config = (new ConsumerConfig($consumerName))->withFilterSubject($streamName . '.>');

                $info = $js->createConsumer($streamName, $consumerName, $config);

                expect($info)->toBeInstanceOf(ConsumerInfo::class);
                expect($info->getStreamName())->toBe($streamName);
                expect($info->getName())->toBe($consumerName);
                expect($info->getConfig()->getDurableName())->toBe($consumerName);

                try {
                    $js->deleteConsumer($streamName, $consumerName);
                    $js->deleteStream($streamName);
                } catch (\Throwable) {
                }
            });
        });

        it('gets consumer information', function (): void {
            runWithConsumerClientRetry(function (JetStreamClient $js): void {
                $streamName = 'consumer-info-stream-' . uniqid();
                $subject = $streamName . '.>';
                $js->createStream(new StreamConfig($streamName, [$subject]));

                $consumerName = 'info-consumer-' . uniqid();
                $config = new ConsumerConfig($consumerName);
                $js->createConsumer($streamName, $consumerName, $config);

                $info = $js->getConsumerInfo($streamName, $consumerName);

                expect($info->getStreamName())->toBe($streamName);
                expect($info->getName())->toBe($consumerName);

                try {
                    $js->deleteConsumer($streamName, $consumerName);
                    $js->deleteStream($streamName);
                } catch (\Throwable) {
                }
            });
        });

        it('deletes a consumer', function (): void {
            runWithConsumerClientRetry(function (JetStreamClient $js): void {
                $streamName = 'consumer-del-stream-' . uniqid();
                $subject = $streamName . '.>';
                $js->createStream(new StreamConfig($streamName, [$subject]));

                $consumerName = 'del-consumer-' . uniqid();
                $js->createConsumer($streamName, $consumerName, new ConsumerConfig($consumerName));

                $deleted = $js->deleteConsumer($streamName, $consumerName);

                expect($deleted)->toBeTrue();

                expect(fn () => $js->getConsumerInfo($streamName, $consumerName))->toThrow(
                    \LaravelNats\Exceptions\NatsException::class,
                );

                try {
                    $js->deleteStream($streamName);
                } catch (\Throwable) {
                }
            });
        });

        it('lists consumers for a stream', function (): void {
            runWithConsumerClientRetry(function (JetStreamClient $js): void {
                $streamName = 'consumer-list-stream-' . uniqid();
                $subject = $streamName . '.>';
                $js->createStream(new StreamConfig($streamName, [$subject]));

                $name1 = 'list-consumer-a-' . uniqid();
                $name2 = 'list-consumer-b-' . uniqid();
                $js->createConsumer($streamName, $name1, new ConsumerConfig($name1));
                $js->createConsumer($streamName, $name2, new ConsumerConfig($name2));

                $result = $js->listConsumers($streamName);

                expect($result)->toHaveKeys(['total', 'offset', 'limit', 'consumers']);
                expect($result['total'])->toBeGreaterThanOrEqual(2);
                expect($result['consumers'])->toBeArray();
                $names = array_map(fn (ConsumerInfo $c) => $c->getName(), $result['consumers']);
                expect($names)->toContain($name1);
                expect($names)->toContain($name2);

                try {
                    $js->deleteConsumer($streamName, $name1);
                    $js->deleteConsumer($streamName, $name2);
                    $js->deleteStream($streamName);
                } catch (\Throwable) {
                }
            });
        });
    });
});
