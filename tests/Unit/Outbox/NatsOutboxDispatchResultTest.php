<?php

declare(strict_types=1);

use LaravelNats\Outbox\NatsOutboxDispatchResult;

it('counts dispatch outcomes', function (): void {
    $result = new NatsOutboxDispatchResult(['a', 'b'], ['c']);

    expect($result->attempted())->toBe(3)
        ->and($result->published())->toBe(2)
        ->and($result->failed())->toBe(1);
});
