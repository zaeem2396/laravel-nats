<?php

declare(strict_types=1);

use LaravelNats\Observability\EnvelopeDataRedactor;

it('returns value unchanged when no fragments configured', function (): void {
    $data = ['password' => 'secret', 'ok' => 1];

    expect(EnvelopeDataRedactor::redact($data, []))->toBe($data);
});

it('redacts keys whose names contain configured substrings', function (): void {
    $data = [
        'user' => 'alice',
        'user_password' => 'x',
        'nested' => ['api_token' => 't', 'safe' => 2],
    ];

    $out = EnvelopeDataRedactor::redact($data, ['password', 'token']);

    expect($out['user'])->toBe('alice')
        ->and($out['user_password'])->toBe('[REDACTED]')
        ->and($out['nested']['api_token'])->toBe('[REDACTED]')
        ->and($out['nested']['safe'])->toBe(2);
});

it('matches substrings case-insensitively', function (): void {
    $out = EnvelopeDataRedactor::redact(['MySECRETField' => 'v'], ['secret']);

    expect($out['MySECRETField'])->toBe('[REDACTED]');
});
