<?php

declare(strict_types=1);

use Basis\Nats\Message\Payload;
use LaravelNats\Subscriber\InboundMessage;

it('builds from basis payload', function (): void {
    $p = new Payload('{"a":1}', [], 'test.subject', null);
    $m = InboundMessage::fromPayload($p, 'reply.inbox');

    expect($m->subject)->toBe('test.subject')
        ->and($m->body)->toBe('{"a":1}')
        ->and($m->replyTo)->toBe('reply.inbox');
});

it('decodes envelope-shaped json', function (): void {
    $body = json_encode([
        'id' => '550e8400-e29b-41d4-a716-446655440000',
        'type' => 'x.y',
        'version' => 'v1',
        'data' => ['k' => 1],
    ], JSON_THROW_ON_ERROR);

    $p = new Payload($body, [], 'x.y', null);
    $m = InboundMessage::fromPayload($p, null);

    $env = $m->envelopePayload();
    expect($env)->toBeArray()
        ->and($env['data'])->toBe(['k' => 1]);
});

it('exposes request and correlation ids from headers case-insensitively', function (): void {
    $p = new Payload('{}', [
        'X-Request-Id' => 'rid-1',
        'nats-correlation-id' => 'cid-1',
    ], 'subj', null);
    $m = InboundMessage::fromPayload($p, null);

    expect($m->requestId())->toBe('rid-1')
        ->and($m->correlationId())->toBe('cid-1');
});

it('exposes W3C trace context headers', function (): void {
    $p = new Payload('{}', [
        'TraceParent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01',
        'tracestate' => 'rojo=00f067aa0ba902b7',
    ], 'subj', null);
    $m = InboundMessage::fromPayload($p, null);

    expect($m->traceParent())->toBe('00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01')
        ->and($m->traceState())->toBe('rojo=00f067aa0ba902b7');
});
