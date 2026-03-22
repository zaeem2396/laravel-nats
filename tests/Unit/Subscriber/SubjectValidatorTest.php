<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use LaravelNats\Subscriber\Exceptions\InvalidSubjectException;
use LaravelNats\Subscriber\SubjectValidator;

it('rejects empty subject', function (): void {
    $v = new SubjectValidator(new Repository(['nats_basis' => ['subscriber' => ['subject_max_length' => 512]]]));

    expect(fn () => $v->validate(''))->toThrow(InvalidSubjectException::class);
});

it('accepts valid subject', function (): void {
    $v = new SubjectValidator(new Repository(['nats_basis' => ['subscriber' => ['subject_max_length' => 512]]]));

    $v->validate('orders.created');

    expect(true)->toBeTrue();
});
