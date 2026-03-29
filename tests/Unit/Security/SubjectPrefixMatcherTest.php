<?php

declare(strict_types=1);

use LaravelNats\Security\SubjectPrefixMatcher;

it('allows exact match', function (): void {
    expect(SubjectPrefixMatcher::isAllowed('orders.created', ['orders.created']))->toBeTrue();
});

it('allows prefix with trailing dot', function (): void {
    expect(SubjectPrefixMatcher::isAllowed('orders.created', ['orders.']))->toBeTrue()
        ->and(SubjectPrefixMatcher::isAllowed('orders', ['orders.']))->toBeFalse();
});

it('allows namespace without trailing dot', function (): void {
    expect(SubjectPrefixMatcher::isAllowed('orders', ['orders']))->toBeTrue()
        ->and(SubjectPrefixMatcher::isAllowed('orders.x', ['orders']))->toBeTrue();
});

it('denies when no prefix matches', function (): void {
    expect(SubjectPrefixMatcher::isAllowed('other.x', ['orders.']))->toBeFalse();
});

it('denies empty subject', function (): void {
    expect(SubjectPrefixMatcher::isAllowed('', ['a']))->toBeFalse();
});
