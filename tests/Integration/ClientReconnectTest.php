<?php

declare(strict_types=1);

use LaravelNats\Core\Client;
use LaravelNats\Core\Connection\ConnectionConfig;
use LaravelNats\Core\Messaging\Message;
use LaravelNats\Tests\TestCase;

describe('Core Client reconnect', function (): void {
    beforeEach(function (): void {
        if (! TestCase::isNatsReachable()) {
            $this->markTestSkipped('NATS server not available');
        }
    });

    it('reconnects and can publish again', function (): void {
        $client = new Client(ConnectionConfig::local());
        $client->connect();

        $subject = 'reconnect.test.'.uniqid();
        $received = null;

        $client->subscribe($subject, function (Message $message) use (&$received): void {
            $received = $message->getPayload();
        });

        $client->reconnect();

        $client->subscribe($subject, function (Message $message) use (&$received): void {
            $received = $message->getPayload();
        });

        $client->publish($subject, 'after-reconnect');
        $client->process(0.5);

        expect($received)->toBe('after-reconnect');

        $client->disconnect();
    });

    it('clears subscription bookkeeping on reconnect', function (): void {
        $client = new Client(ConnectionConfig::local());
        $client->connect();

        $sid = $client->subscribe('reconnect.sub.'.uniqid(), static function (): void {});
        expect($client->hasSubscription($sid))->toBeTrue();

        $client->reconnect();

        expect($client->hasSubscription($sid))->toBeFalse()
            ->and($client->getSubscriptionCount())->toBe(0);

        $client->disconnect();
    });
});
