<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use LaravelNats\Connection\ConnectionSelector;

it('returns explicit connection first', function (): void {
    $selector = new ConnectionSelector(new Repository([
        'nats_basis' => [
            'connection_selection' => [
                'subject_prefixes' => [
                    'orders.' => 'orders',
                ],
            ],
        ],
    ]));

    expect($selector->select('explicit', 'orders.created'))->toBe('explicit');
});

it('selects the longest matching subject prefix', function (): void {
    $selector = new ConnectionSelector(new Repository([
        'nats_basis' => [
            'connection_selection' => [
                'subject_prefixes' => [
                    'orders.' => 'orders',
                    'orders.eu.' => 'orders-eu',
                ],
            ],
        ],
    ]));

    expect($selector->select(null, 'orders.eu.created'))->toBe('orders-eu')
        ->and($selector->select(null, 'orders.us.created'))->toBe('orders');
});

it('returns null when there is no match', function (): void {
    $selector = new ConnectionSelector(new Repository([
        'nats_basis' => [
            'connection_selection' => [
                'subject_prefixes' => [
                    'billing.' => 'billing',
                ],
            ],
        ],
    ]));

    expect($selector->select(null, 'orders.created'))->toBeNull()
        ->and($selector->select(null, null))->toBeNull();
});

it('reports whether prefix rules are configured', function (): void {
    $empty = new ConnectionSelector(new Repository([]));
    $configured = new ConnectionSelector(new Repository([
        'nats_basis' => [
            'connection_selection' => [
                'subject_prefixes' => ['orders.' => 'orders'],
            ],
        ],
    ]));

    expect($empty->hasRules())->toBeFalse()
        ->and($configured->hasRules())->toBeTrue();
});
