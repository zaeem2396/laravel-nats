<?php

declare(strict_types=1);

namespace LaravelNats\JetStream;

use Basis\Nats\Message\Msg;
use LaravelNats\Connection\ConnectionManager;

/**
 * One-shot pull fetch aligned with basis {@see \Basis\Nats\Consumer\Consumer::getQueue}.
 */
final class PullConsumerBatch
{
    public function __construct(
        private readonly ConnectionManager $connections,
    ) {
    }

    /**
     * @return list<Msg>
     */
    public function fetch(
        string $stream,
        string $consumerName,
        int $batch = 10,
        float $expiresSeconds = 0.5,
        ?string $connection = null,
    ): array {
        $manager = new BasisJetStreamManager($this->connections, $connection);
        $client = $manager->client($connection);
        $consumer = $manager->stream($stream, $connection)->getConsumer($consumerName);
        $consumer->create();
        $consumer->setBatching(max(1, $batch));
        $consumer->setExpires($expiresSeconds > 0 ? $expiresSeconds : 0.1);

        $queue = $consumer->getQueue();

        try {
            /** @var list<Msg> $messages */
            $messages = $queue->fetchAll($batch);

            return $messages;
        } finally {
            $client->unsubscribe($queue);
        }
    }
}
