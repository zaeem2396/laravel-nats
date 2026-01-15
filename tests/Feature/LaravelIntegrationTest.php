<?php

declare(strict_types=1);

use LaravelNats\Contracts\Messaging\MessageInterface;
use LaravelNats\Core\Client;
use LaravelNats\Laravel\Facades\Nats;

beforeEach(function (): void {
    // Skip tests if NATS server is not available
    if (! $this->isNatsAvailable()) {
        $this->markTestSkipped('NATS server not available at localhost:4222');
    }
});

it('can connect via facade', function (): void {
    $connection = Nats::connection();

    expect($connection)->toBeInstanceOf(Client::class)
        ->and($connection->isConnected())->toBeTrue();

    Nats::disconnect();
});

it('can publish and subscribe via facade', function (): void {
    $received = null;
    $subject = 'laravel.test.' . uniqid();

    Nats::subscribe($subject, function (MessageInterface $message) use (&$received): void {
        $received = $message->getDecodedPayload();
    });

    Nats::publish($subject, ['test' => 'data']);
    Nats::process(0.5);

    expect($received)->toBe(['test' => 'data']);

    Nats::disconnect();
});

it('can use request/reply via facade', function (): void {
    $subject = 'laravel.echo.' . uniqid();

    // Set up responder - replies with echo of the request
    Nats::subscribe($subject, function (MessageInterface $message): void {
        $replyTo = $message->getReplyTo();
        if ($replyTo !== null) {
            Nats::publish($replyTo, ['echo' => $message->getDecodedPayload()]);
        }
    });

    // Send request with reply-to using publishRaw
    $response = null;
    $replySubject = '_INBOX.' . uniqid();

    Nats::subscribe($replySubject, function (MessageInterface $msg) use (&$response): void {
        $response = $msg->getDecodedPayload();
    });

    // Publish with reply-to using the raw method
    $payload = json_encode(['request' => 'data']);
    Nats::connection()->publishRaw($subject, $payload, $replySubject);

    // Process messages (responder receives, sends reply, we receive reply)
    Nats::process(0.5);
    Nats::process(0.5);

    expect($response)->toBeArray()
        ->and($response)->toHaveKey('echo');

    Nats::disconnect();
});

it('can subscribe with queue group via facade', function (): void {
    $received = [];
    $subject = 'laravel.queue.' . uniqid();
    $queueGroup = 'workers';

    // Create two queue subscribers
    Nats::subscribe($subject, function (MessageInterface $message) use (&$received): void {
        $received[] = 'worker1:' . $message->getPayload();
    }, $queueGroup);

    Nats::subscribe($subject, function (MessageInterface $message) use (&$received): void {
        $received[] = 'worker2:' . $message->getPayload();
    }, $queueGroup);

    // Publish messages
    for ($i = 1; $i <= 5; ++$i) {
        Nats::publish($subject, "msg{$i}");
    }

    // Process
    Nats::process(0.5);

    // With queue groups, each message should only be delivered to one subscriber
    expect($received)->toHaveCount(5);

    Nats::disconnect();
});

it('tracks multiple connections', function (): void {
    // Configure a second connection
    $this->app->make('config')->set('nats.connections.secondary', [
        'host' => 'localhost',
        'port' => 4222,
    ]);

    $default = Nats::connection('default');
    $secondary = Nats::connection('secondary');

    expect(Nats::getConnections())->toHaveCount(2)
        ->and($default)->toBeInstanceOf(Client::class)
        ->and($secondary)->toBeInstanceOf(Client::class);

    Nats::disconnectAll();

    expect(Nats::getConnections())->toBeEmpty();
});

it('can reconnect via facade', function (): void {
    $connection1 = Nats::connection();
    expect($connection1->isConnected())->toBeTrue();

    $connection2 = Nats::reconnect();
    expect($connection2->isConnected())->toBeTrue();

    // Should be a new connection instance
    expect($connection1)->not->toBe($connection2);

    Nats::disconnect();
});

it('resolves Client from container', function (): void {
    $client = $this->app->make(Client::class);

    expect($client)->toBeInstanceOf(Client::class)
        ->and($client->isConnected())->toBeTrue();

    $client->disconnect();
});

it('can unsubscribe via facade', function (): void {
    $subject = 'laravel.unsub.' . uniqid();
    $received = 0;

    $sid = Nats::subscribe($subject, function () use (&$received): void {
        ++$received;
    });

    Nats::publish($subject, 'msg1');
    Nats::process(0.3);

    // Unsubscribe
    Nats::unsubscribe($sid);

    Nats::publish($subject, 'msg2');
    Nats::process(0.3);

    // Should only have received the first message
    expect($received)->toBe(1);

    Nats::disconnect();
});
