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
