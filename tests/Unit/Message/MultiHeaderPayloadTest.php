<?php

declare(strict_types=1);

use LaravelNats\Message\MultiHeaderPayload;

it('renders multi-value headers per NATS HPUB example shape', function (): void {
    $p = new MultiHeaderPayload('Yum!', [
        'BREAKFAST' => ['donut', 'eggs'],
    ]);

    $wire = $p->render();

    expect($wire)->toContain("NATS/1.0\r\n")
        ->and($wire)->toContain("BREAKFAST: donut\r\n")
        ->and($wire)->toContain("BREAKFAST: eggs\r\n")
        ->and($wire)->toEndWith('Yum!');
});
