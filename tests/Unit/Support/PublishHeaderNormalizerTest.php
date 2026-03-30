<?php

declare(strict_types=1);

use LaravelNats\Support\PublishHeaderNormalizer;

it('normalizes scalars to single-value lists', function (): void {
    $out = PublishHeaderNormalizer::toNamedValues(['a' => '1', 'b' => 2]);

    expect($out)->toBe(['a' => ['1'], 'b' => ['2']]);
});

it('preserves list values', function (): void {
    $out = PublishHeaderNormalizer::toNamedValues(['H' => ['x', 'y']]);

    expect($out)->toBe(['H' => ['x', 'y']]);
});
