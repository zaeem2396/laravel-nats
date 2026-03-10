<?php

declare(strict_types=1);

namespace LaravelNats\Contracts\JetStream;

use LaravelNats\Core\JetStream\JetStreamConsumedMessage;

/**
 * JetStreamMessageHandlerInterface defines the contract for JetStream pull consumer handlers (Phase 4.3).
 *
 * Handlers are invoked by the nats:consume:stream command for each message fetched from a JetStream
 * pull consumer. The handler is resolved from the container (DI). The command acks the message
 * after the handler returns successfully; on exception it can nak or leave for redelivery.
 */
interface JetStreamMessageHandlerInterface
{
    /**
     * Handle a consumed JetStream message.
     *
     * @param JetStreamConsumedMessage $message The message from CONSUMER.MSG.NEXT (stream, consumer, payload, ack subject)
     */
    public function handle(JetStreamConsumedMessage $message): void;
}
