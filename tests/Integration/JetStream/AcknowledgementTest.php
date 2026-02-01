<?php

declare(strict_types=1);

use LaravelNats\Core\JetStream\ConsumerConfig;
use LaravelNats\Core\JetStream\JetStreamClient;
use LaravelNats\Core\JetStream\JetStreamConsumedMessage;
use LaravelNats\Core\JetStream\StreamConfig;
use LaravelNats\Exceptions\ConnectionException;

function createAckTestClient(): JetStreamClient
{
    $client = \LaravelNats\Tests\TestCase::createConnectedJetStreamClient();

    return new JetStreamClient($client);
}

function runWithAckClientRetry(callable $run): void
{
    $lastException = null;
    $maxAttempts = 3;

    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        if ($attempt > 0) {
            usleep(1500000);
        }

        $js = createAckTestClient();

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

describe('Acknowledgement (pull consumer)', function (): void {
    beforeEach(function (): void {
        if (! \LaravelNats\Tests\TestCase::isNatsReachable()) {
            $this->markTestSkipped('NATS server not available');
        }
    });

    it('fetches next message and acks it', function (): void {
        runWithAckClientRetry(function (JetStreamClient $js): void {
            $streamName = 'ack-stream-' . uniqid();
            $subject = $streamName . '.>';
            $js->createStream(new StreamConfig($streamName, [$subject]));

            $consumerName = 'ack-consumer-' . uniqid();
            $config = (new ConsumerConfig($consumerName))
                ->withAckPolicy(ConsumerConfig::ACK_EXPLICIT)
                ->withDeliverPolicy(ConsumerConfig::DELIVER_ALL);
            $js->createConsumer($streamName, $consumerName, $config);

            $js->getClient()->publish($streamName . '.evt', ['test' => 'ack-me']);

            usleep(200000);

            $msg = $js->fetchNextMessage($streamName, $consumerName, 5.0);

            expect($msg)->toBeInstanceOf(JetStreamConsumedMessage::class);
            expect($msg->getStreamName())->toBe($streamName);
            expect($msg->getConsumerName())->toBe($consumerName);
            expect($msg->getAckSubject())->not->toBe('');

            $js->ack($msg);

            $noMsg = $js->fetchNextMessage($streamName, $consumerName, 2.0, true);

            expect($noMsg)->toBeNull();

            try {
                $js->deleteConsumer($streamName, $consumerName);
                $js->deleteStream($streamName);
            } catch (\Throwable) {
            }
        });
    });

    it('returns null when no_wait and no message', function (): void {
        runWithAckClientRetry(function (JetStreamClient $js): void {
            $streamName = 'ack-nowait-stream-' . uniqid();
            $subject = $streamName . '.>';
            $js->createStream(new StreamConfig($streamName, [$subject]));

            $consumerName = 'ack-nowait-consumer-' . uniqid();
            $config = (new ConsumerConfig($consumerName))->withAckPolicy(ConsumerConfig::ACK_EXPLICIT);
            $js->createConsumer($streamName, $consumerName, $config);

            $msg = $js->fetchNextMessage($streamName, $consumerName, 2.0, true);

            expect($msg)->toBeNull();

            try {
                $js->deleteConsumer($streamName, $consumerName);
                $js->deleteStream($streamName);
            } catch (\Throwable) {
            }
        });
    });
});
