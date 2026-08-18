<?php

declare(strict_types=1);

namespace LaravelNats\Subscriber;

use Illuminate\Contracts\Config\Repository;
use LaravelNats\Subscriber\Exceptions\InvalidSubjectException;
use LaravelNats\Support\MixedTypes;

/**
 * Validates NATS subject strings for subscriptions (length, non-empty).
 */
final class SubjectValidator
{
    public function __construct(
        private readonly Repository $config,
    ) {
    }

    /**
     * @throws InvalidSubjectException
     */
    public function validate(string $subject): void
    {
        if ($subject === '') {
            throw InvalidSubjectException::empty();
        }

        $max = MixedTypes::int($this->config->get('nats_basis.subscriber.subject_max_length', 512), 512);
        if ($max > 0 && strlen($subject) > $max) {
            throw InvalidSubjectException::tooLong($max);
        }
    }
}
