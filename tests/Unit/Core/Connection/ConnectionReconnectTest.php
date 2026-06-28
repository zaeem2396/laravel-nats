<?php

declare(strict_types=1);

use LaravelNats\Core\Connection\Connection;
use LaravelNats\Core\Connection\ConnectionConfig;
use LaravelNats\Tests\TestCase;

describe('Connection reconnect', function (): void {
    it('reconnects after disconnect', function (): void {
        TestCase::skipUnlessNatsReachable();

        $connection = new Connection(ConnectionConfig::local());
        $connection->connect();
        $connection->disconnect();

        $connection->connect();

        expect($connection->isConnected())->toBeTrue()
            ->and($connection->getServerInfo())->not->toBeNull();

        $connection->disconnect();
    });

    it('reconnects after markDisconnected cleared the session flag', function (): void {
        TestCase::skipUnlessNatsReachable();

        $connection = new Connection(ConnectionConfig::local());
        $connection->connect();

        $markDisconnected = new ReflectionMethod(Connection::class, 'markDisconnected');
        $markDisconnected->invoke($connection);

        expect($connection->isConnected())->toBeFalse();

        $connection->connect();

        expect($connection->isConnected())->toBeTrue();

        $connection->disconnect();
    });

    it('updates idle time after a successful read', function (): void {
        TestCase::skipUnlessNatsReachable();

        $connection = new Connection(ConnectionConfig::local());
        $connection->connect();

        usleep(100000);

        $idleBefore = $connection->getIdleTime();
        $connection->ping();

        $deadline = microtime(true) + 1.0;
        while (microtime(true) < $deadline) {
            $line = $connection->readLine();
            if ($line !== null && $connection->getParser()->detectType($line) === 'PONG') {
                break;
            }
            usleep(1000);
        }

        expect($connection->getIdleTime())->toBeLessThan($idleBefore);

        $connection->disconnect();
    });
});
