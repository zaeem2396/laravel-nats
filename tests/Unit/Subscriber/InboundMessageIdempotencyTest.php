<?php

declare(strict_types=1);

use LaravelNats\Subscriber\InboundMessage;

describe('InboundMessage idempotencyKey', function (): void {
    it('reads from header first', function (): void {
        $m = new InboundMessage(
            subject: 'x',
            body: json_encode([
                'id' => '1',
                'type' => 'x',
                'version' => 'v1',
                'data' => [],
                'idempotency_key' => 'from-body',
            ]),
            headers: ['Nats-Idempotency-Key' => 'from-header'],
            replyTo: null,
        );

        expect($m->idempotencyKey())->toBe('from-header');
    });

    it('falls back to envelope idempotency_key', function (): void {
        $m = new InboundMessage(
            subject: 'x',
            body: json_encode([
                'id' => '1',
                'type' => 'x',
                'version' => 'v1',
                'data' => [],
                'idempotency_key' => 'from-envelope',
            ]),
            headers: [],
            replyTo: null,
        );

        expect($m->idempotencyKey())->toBe('from-envelope');
    });

    it('returns null when missing', function (): void {
        $m = new InboundMessage(
            subject: 'x',
            body: json_encode([
                'id' => '1',
                'type' => 'x',
                'version' => 'v1',
                'data' => [],
            ]),
            headers: [],
            replyTo: null,
        );

        expect($m->idempotencyKey())->toBeNull();
    });
});
