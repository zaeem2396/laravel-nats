<?php

declare(strict_types=1);

/**
 * ============================================================================
 * MESSAGE UNIT TESTS
 * ============================================================================
 *
 * These tests verify the Message value object correctly handles all
 * message properties and operations.
 *
 * Message is immutable and encapsulates:
 * - Subject (destination)
 * - Payload (content)
 * - Reply-to (for request/reply)
 * - Headers (metadata)
 * - SID (subscription identifier)
 * ============================================================================
 */

use LaravelNats\Core\Messaging\Message;
use LaravelNats\Core\Serialization\JsonSerializer;

describe('basic properties', function (): void {
    it('stores subject', function (): void {
        $message = new Message(subject: 'orders.created', payload: '{}');

        expect($message->getSubject())->toBe('orders.created');
    });

    it('stores payload', function (): void {
        $message = new Message(subject: 'test', payload: '{"id":123}');

        expect($message->getPayload())->toBe('{"id":123}');
    });

    it('stores reply-to', function (): void {
        $message = new Message(
            subject: 'test',
            payload: '{}',
            replyTo: '_INBOX.abc123',
        );

        expect($message->getReplyTo())->toBe('_INBOX.abc123');
    });

    it('returns null for no reply-to', function (): void {
        $message = new Message(subject: 'test', payload: '{}');

        expect($message->getReplyTo())->toBeNull();
    });

    it('stores SID', function (): void {
        $message = new Message(
            subject: 'test',
            payload: '{}',
            sid: '42',
        );

        expect($message->getSid())->toBe('42');
    });
});

describe('headers', function (): void {
    it('stores headers', function (): void {
        $message = new Message(
            subject: 'test',
            payload: '{}',
            headers: ['X-Custom' => 'value', 'Content-Type' => 'application/json'],
        );

        expect($message->getHeaders())->toBe([
            'X-Custom' => 'value',
            'Content-Type' => 'application/json',
        ]);
    });

    it('retrieves single header', function (): void {
        $message = new Message(
            subject: 'test',
            payload: '{}',
            headers: ['X-Request-Id' => 'abc123'],
        );

        expect($message->getHeader('X-Request-Id'))->toBe('abc123');
    });

    it('returns default for missing header', function (): void {
        $message = new Message(subject: 'test', payload: '{}');

        expect($message->getHeader('X-Missing', 'default'))->toBe('default');
    });

    it('detects presence of headers', function (): void {
        $withHeaders = new Message(
            subject: 'test',
            payload: '{}',
            headers: ['X-Custom' => 'value'],
        );
        $withoutHeaders = new Message(subject: 'test', payload: '{}');

        expect($withHeaders->hasHeaders())->toBeTrue()
            ->and($withoutHeaders->hasHeaders())->toBeFalse();
    });
});

describe('payload size', function (): void {
    it('returns payload size in bytes', function (): void {
        $message = new Message(subject: 'test', payload: '0123456789');

        expect($message->getSize())->toBe(10);
    });

    it('handles empty payload', function (): void {
        $message = new Message(subject: 'test', payload: '');

        expect($message->getSize())->toBe(0);
    });

    it('handles unicode correctly', function (): void {
        // 🚀 is 4 bytes in UTF-8
        $message = new Message(subject: 'test', payload: '🚀');

        expect($message->getSize())->toBe(4);
    });
});

describe('reply detection', function (): void {
    it('detects message expecting reply', function (): void {
        $message = new Message(
            subject: 'test',
            payload: '{}',
            replyTo: '_INBOX.reply',
        );

        expect($message->expectsReply())->toBeTrue();
    });

    it('detects message not expecting reply', function (): void {
        $message = new Message(subject: 'test', payload: '{}');

        expect($message->expectsReply())->toBeFalse();
    });
});

describe('decoded payload', function (): void {
    it('decodes JSON with serializer', function (): void {
        $serializer = new JsonSerializer();
        $message = new Message(
            subject: 'test',
            payload: '{"id":123,"name":"Test"}',
            serializer: $serializer,
        );

        expect($message->getDecodedPayload())->toBe([
            'id' => 123,
            'name' => 'Test',
        ]);
    });

    it('decodes JSON without serializer', function (): void {
        $message = new Message(
            subject: 'test',
            payload: '{"id":456}',
        );

        // Falls back to json_decode
        expect($message->getDecodedPayload())->toBe(['id' => 456]);
    });

    it('returns raw string for non-JSON', function (): void {
        $message = new Message(
            subject: 'test',
            payload: 'plain text message',
        );

        expect($message->getDecodedPayload())->toBe('plain text message');
    });

    it('caches decoded payload', function (): void {
        $serializer = new JsonSerializer();
        $message = new Message(
            subject: 'test',
            payload: '{"id":789}',
            serializer: $serializer,
        );

        // Call twice - should return same cached result
        $first = $message->getDecodedPayload();
        $second = $message->getDecodedPayload();

        expect($first)->toBe($second);
    });
});

describe('factory methods', function (): void {
    it('creates message for publishing', function (): void {
        $serializer = new JsonSerializer();
        $message = Message::create(
            subject: 'orders.created',
            payload: ['id' => 123],
            serializer: $serializer,
            headers: ['X-Source' => 'test'],
        );

        expect($message->getSubject())->toBe('orders.created')
            ->and($message->getPayload())->toBe('{"id":123}')
            ->and($message->getHeader('X-Source'))->toBe('test');
    });

    it('creates message from received data', function (): void {
        $message = Message::fromReceived(
            subject: 'orders.created',
            payload: '{"id":456}',
            replyTo: '_INBOX.reply',
            sid: '5',
            headers: ['X-Custom' => 'value'],
        );

        expect($message->getSubject())->toBe('orders.created')
            ->and($message->getSid())->toBe('5')
            ->and($message->getReplyTo())->toBe('_INBOX.reply');
    });
});

describe('reply creation', function (): void {
    it('creates reply message', function (): void {
        $serializer = new JsonSerializer();
        $original = new Message(
            subject: 'api.request',
            payload: '{}',
            replyTo: '_INBOX.abc123',
        );

        $reply = $original->createReply(['status' => 'ok'], $serializer);

        expect($reply)->not->toBeNull()
            ->and($reply->getSubject())->toBe('_INBOX.abc123')
            ->and($reply->getPayload())->toBe('{"status":"ok"}');
    });

    it('returns null when no reply-to', function (): void {
        $serializer = new JsonSerializer();
        $original = new Message(subject: 'test', payload: '{}');

        $reply = $original->createReply(['data' => 'test'], $serializer);

        expect($reply)->toBeNull();
    });
});
