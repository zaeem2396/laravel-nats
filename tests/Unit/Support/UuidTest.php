<?php

declare(strict_types=1);

use LaravelNats\Support\Uuid;

it('generates RFC 4122 version 4 UUIDs', function (): void {
    $uuid = Uuid::v4();

    expect($uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});

it('generates unique values', function (): void {
    $a = Uuid::v4();
    $b = Uuid::v4();

    expect($a)->not->toBe($b);
});
