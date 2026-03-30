<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use LaravelNats\Security\Exceptions\SubjectNotAllowedException;
use LaravelNats\Security\SubjectAclChecker;

it('allows publish when ACL is disabled', function (): void {
    $c = new SubjectAclChecker(new Repository([
        'nats_basis' => [
            'acl' => [
                'enabled' => false,
                'allowed_publish_prefixes' => [],
            ],
        ],
    ]));

    $c->assertPublishAllowed('anything.goes');
    expect(true)->toBeTrue();
});

it('denies publish when enabled with empty allowlist', function (): void {
    $c = new SubjectAclChecker(new Repository([
        'nats_basis' => [
            'acl' => [
                'enabled' => true,
                'allowed_publish_prefixes' => [],
            ],
        ],
    ]));

    expect(fn () => $c->assertPublishAllowed('orders.created'))
        ->toThrow(SubjectNotAllowedException::class);
});

it('allows publish when subject matches prefix rule', function (): void {
    $c = new SubjectAclChecker(new Repository([
        'nats_basis' => [
            'acl' => [
                'enabled' => true,
                'allowed_publish_prefixes' => ['orders.'],
            ],
        ],
    ]));

    $c->assertPublishAllowed('orders.created');
    expect(true)->toBeTrue();
});

it('denies subscribe when enabled with empty subscribe allowlist', function (): void {
    $c = new SubjectAclChecker(new Repository([
        'nats_basis' => [
            'acl' => [
                'enabled' => true,
                'allowed_subscribe_prefixes' => [],
            ],
        ],
    ]));

    expect(fn () => $c->assertSubscribeAllowed('events.>'))
        ->toThrow(SubjectNotAllowedException::class);
});

it('allows subscribe when subject matches', function (): void {
    $c = new SubjectAclChecker(new Repository([
        'nats_basis' => [
            'acl' => [
                'enabled' => true,
                'allowed_subscribe_prefixes' => ['events.'],
            ],
        ],
    ]));

    $c->assertSubscribeAllowed('events.>');
    expect(true)->toBeTrue();
});
