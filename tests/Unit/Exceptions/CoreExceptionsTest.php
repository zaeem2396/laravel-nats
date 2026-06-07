<?php

declare(strict_types=1);

use LaravelNats\Exceptions\ConnectionException;
use LaravelNats\Exceptions\NatsException;
use LaravelNats\Exceptions\ProtocolException;
use LaravelNats\Exceptions\PublishException;
use LaravelNats\Exceptions\SerializationException;
use LaravelNats\Exceptions\SubscriptionException;
use LaravelNats\Exceptions\TimeoutException;

describe('ConnectionException', function (): void {
    it('builds timeout message', function (): void {
        $e = ConnectionException::timeout('nats.local', 4222, 2.5);

        expect($e)->toBeInstanceOf(NatsException::class)
            ->and($e->getMessage())->toContain('nats.local')
            ->and($e->getMessage())->toContain('4222')
            ->and($e->getMessage())->toContain('2.5');
    });

    it('builds refused message', function (): void {
        expect(ConnectionException::refused('127.0.0.1', 4222)->getMessage())
            ->toContain('refused');
    });

    it('builds authentication failed message', function (): void {
        expect(ConnectionException::authenticationFailed('bad creds')->getMessage())
            ->toContain('bad creds');
    });

    it('builds static factory messages', function (): void {
        expect(ConnectionException::notConnected()->getMessage())->toContain('Not connected')
            ->and(ConnectionException::writeFailed('x')->getMessage())->toContain('x')
            ->and(ConnectionException::readFailed()->getMessage())->not->toBeEmpty()
            ->and(ConnectionException::tlsFailed('cert')->getMessage())->toContain('cert')
            ->and(ConnectionException::disconnected()->getMessage())->toContain('closed');
    });
});

describe('PublishException', function (): void {
    it('builds invalid subject message', function (): void {
        $e = PublishException::invalidSubject('bad subject', 'spaces');

        expect($e->getMessage())->toContain('bad subject')
            ->and($e->getMessage())->toContain('spaces');
    });

    it('builds message too large message', function (): void {
        expect(PublishException::messageTooLarge(2000, 1024)->getMessage())
            ->toContain('2000')
            ->and(PublishException::messageTooLarge(2000, 1024)->getMessage())->toContain('1024');
    });

    it('builds failed message', function (): void {
        expect(PublishException::failed('orders', 'timeout')->getMessage())
            ->toContain('orders')
            ->and(PublishException::failed('orders', 'timeout')->getMessage())->toContain('timeout');
    });
});

describe('SubscriptionException', function (): void {
    it('builds invalid subject message', function (): void {
        expect(SubscriptionException::invalidSubject('a b', 'space')->getMessage())
            ->toContain('a b');
    });

    it('builds not found and limit messages', function (): void {
        expect(SubscriptionException::notFound('sid-1')->getMessage())->toContain('sid-1')
            ->and(SubscriptionException::limitExceeded(100)->getMessage())->toContain('100');
    });
});

describe('SerializationException', function (): void {
    it('builds serialize and deserialize messages', function (): void {
        expect(SerializationException::serializeFailed('boom')->getMessage())->toContain('boom')
            ->and(SerializationException::deserializeFailed('bad')->getMessage())->toContain('bad');
    });

    it('builds json error message', function (): void {
        expect(SerializationException::jsonError(JSON_ERROR_SYNTAX)->getMessage())->not->toBeEmpty();
    });
});

describe('ProtocolException', function (): void {
    it('builds server and parse messages', function (): void {
        expect(ProtocolException::serverError('ERR')->getMessage())->toContain('ERR')
            ->and(ProtocolException::invalidMessage('garbage')->getMessage())->toContain('garbage')
            ->and(ProtocolException::unknownCommand('FOO')->getMessage())->toContain('FOO')
            ->and(ProtocolException::parseFailed('data', 'reason')->getMessage())->toContain('reason');
    });
});

describe('TimeoutException', function (): void {
    it('builds request and read timeout messages', function (): void {
        expect(TimeoutException::requestTimeout('req.sub', 1.0)->getMessage())
            ->toContain('req.sub')
            ->and(TimeoutException::readTimeout(0.5)->getMessage())->toContain('0.5');
    });
});
