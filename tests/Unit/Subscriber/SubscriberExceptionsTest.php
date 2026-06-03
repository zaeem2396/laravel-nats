<?php

declare(strict_types=1);

use LaravelNats\Security\Exceptions\NatsConfigurationException;
use LaravelNats\Security\Exceptions\SubjectNotAllowedException;
use LaravelNats\Subscriber\Exceptions\SubscriptionConflictException;
use LaravelNats\Subscriber\Exceptions\SubscriptionNotFoundException;

it('builds subscription not found message', function (): void {
    expect(SubscriptionNotFoundException::forId('abc')->getMessage())
        ->toContain('abc');
});

it('builds subscription conflict message', function (): void {
    expect(SubscriptionConflictException::duplicate('dup.sub', 'workers', 'default')->getMessage())
        ->toContain('dup.sub')
        ->and(SubscriptionConflictException::duplicate('dup.sub', null, 'default')->getMessage())
        ->toContain('no queue group');
});

it('builds subject not allowed messages', function (): void {
    expect(SubjectNotAllowedException::publish('secret')->getMessage())
        ->toContain('secret')
        ->and(SubjectNotAllowedException::subscribe('secret')->getMessage())
        ->toContain('subscribe');
});

it('builds configuration exception messages', function (): void {
    expect(NatsConfigurationException::forConnection('default', 'missing host')->getMessage())
        ->toContain('default')
        ->and(NatsConfigurationException::global('invalid config')->getMessage())
        ->toContain('invalid config');
});
