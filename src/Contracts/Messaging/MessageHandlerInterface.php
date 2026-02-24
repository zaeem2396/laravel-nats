<?php

declare(strict_types=1);

namespace LaravelNats\Contracts\Messaging;

/**
 * MessageHandlerInterface defines the contract for NATS message handlers (Phase 4.2).
 *
 * Handlers are invoked by the nats:consume command for each message received
 * on the subscribed subject(s). The handler is resolved from the container
 * so it can use dependency injection. Use --handler=YourHandlerClass when
 * running nats:consume.
 */
interface MessageHandlerInterface
{
    /**
     * Handle a received NATS message.
     *
     * @param MessageInterface $message The received message (subject, payload, reply-to, etc.)
     */
    public function handle(MessageInterface $message): void;
}
