<?php

declare(strict_types=1);

use LaravelNats\Core\JetStream\JetStreamConsumedMessage;
use LaravelNats\Core\Messaging\Message;

describe('JetStreamConsumedMessage', function (): void {
    describe('fromNatsMessage', function (): void {
        it('parses message with ack subject without domain', function (): void {
            $ackSubject = '$JS.ACK.mystream.myconsumer.1.100.50.1234567890000000000.5';
            $msg = Message::fromReceived(
                '_INBOX.abc',
                'hello',
                $ackSubject,
                '1',
                [],
            );

            $jsMsg = JetStreamConsumedMessage::fromNatsMessage($msg);

            expect($jsMsg->getAckSubject())->toBe($ackSubject);
            expect($jsMsg->getStreamName())->toBe('mystream');
            expect($jsMsg->getConsumerName())->toBe('myconsumer');
            expect($jsMsg->getStreamSequence())->toBe(100);
            expect($jsMsg->getConsumerSequence())->toBe(50);
            expect($jsMsg->getPayload())->toBe('hello');
        });

        it('uses Nats-Stream and Nats-Sequence headers when present', function (): void {
            $ackSubject = '$JS.ACK.s1.c1.1.10.20.123.4';
            $msg = Message::fromReceived(
                '_INBOX.x',
                'payload',
                $ackSubject,
                '1',
                [
                    'Nats-Stream' => 'header-stream',
                    'Nats-Sequence' => '999',
                ],
            );

            $jsMsg = JetStreamConsumedMessage::fromNatsMessage($msg);

            expect($jsMsg->getStreamName())->toBe('header-stream');
            expect($jsMsg->getStreamSequence())->toBe(999);
        });

        it('throws when reply-to is missing', function (): void {
            $msg = Message::fromReceived('_INBOX.x', 'data', null, '1', []);

            expect(fn () => JetStreamConsumedMessage::fromNatsMessage($msg))
                ->toThrow(InvalidArgumentException::class, 'reply-to');
        });
    });

    describe('constants', function (): void {
        it('has ack type constants', function (): void {
            expect(JetStreamConsumedMessage::ACK)->toBe('+ACK');
            expect(JetStreamConsumedMessage::NAK)->toBe('-NAK');
            expect(JetStreamConsumedMessage::TERM)->toBe('+TERM');
            expect(JetStreamConsumedMessage::IN_PROGRESS)->toBe('+WPI');
        });
    });

    describe('getNatsMessage', function (): void {
        it('returns the underlying NATS message', function (): void {
            $msg = Message::fromReceived('sub', 'p', '$JS.ACK.a.b.1.1.1.0.0', '1', []);
            $jsMsg = JetStreamConsumedMessage::fromNatsMessage($msg);

            expect($jsMsg->getNatsMessage())->toBe($msg);
        });
    });
});
