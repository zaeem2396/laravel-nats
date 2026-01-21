<?php

declare(strict_types=1);

use LaravelNats\Laravel\Queue\RetryConfiguration;

describe('RetryConfiguration', function (): void {
    describe('constructor', function (): void {
        it('creates instance with default values', function (): void {
            $config = new RetryConfiguration();

            expect($config->getMaxTries())->toBe(3);
            expect($config->getRetryDelay())->toBe(0);
            expect($config->getRetryUntil())->toBeNull();
            expect($config->getMaxExceptions())->toBeNull();
        });

        it('creates instance with custom values', function (): void {
            $config = new RetryConfiguration(
                maxTries: 5,
                retryDelay: 30,
                retryUntil: 1700000000,
                maxExceptions: 2,
            );

            expect($config->getMaxTries())->toBe(5);
            expect($config->getRetryDelay())->toBe(30);
            expect($config->getRetryUntil())->toBe(1700000000);
            expect($config->getMaxExceptions())->toBe(2);
        });
    });

    describe('fromPayload', function (): void {
        it('creates instance from payload with maxTries', function (): void {
            $payload = ['maxTries' => 5];
            $config = RetryConfiguration::fromPayload($payload);

            expect($config->getMaxTries())->toBe(5);
        });

        it('creates instance from payload with retryAfter', function (): void {
            $payload = ['retryAfter' => 120];
            $config = RetryConfiguration::fromPayload($payload);

            expect($config->getRetryDelay())->toBe(120);
        });

        it('creates instance from payload with backoff', function (): void {
            $payload = ['backoff' => 60];
            $config = RetryConfiguration::fromPayload($payload);

            expect($config->getRetryDelay())->toBe(60);
        });

        it('prefers retryAfter over backoff', function (): void {
            $payload = ['retryAfter' => 30, 'backoff' => 60];
            $config = RetryConfiguration::fromPayload($payload);

            expect($config->getRetryDelay())->toBe(30);
        });

        it('creates instance from payload with retryUntil', function (): void {
            $payload = ['retryUntil' => 1700000000];
            $config = RetryConfiguration::fromPayload($payload);

            expect($config->getRetryUntil())->toBe(1700000000);
        });

        it('creates instance from payload with maxExceptions', function (): void {
            $payload = ['maxExceptions' => 2];
            $config = RetryConfiguration::fromPayload($payload);

            expect($config->getMaxExceptions())->toBe(2);
        });

        it('uses default values for missing fields', function (): void {
            $payload = [];
            $config = RetryConfiguration::fromPayload($payload, 10, 45);

            expect($config->getMaxTries())->toBe(10);
            expect($config->getRetryDelay())->toBe(45);
        });

        it('creates from complete Laravel job payload', function (): void {
            $payload = [
                'uuid' => 'test-uuid',
                'displayName' => 'App\\Jobs\\TestJob',
                'maxTries' => 5,
                'maxExceptions' => 3,
                'retryAfter' => 60,
                'retryUntil' => 1700000000,
            ];
            $config = RetryConfiguration::fromPayload($payload);

            expect($config->getMaxTries())->toBe(5);
            expect($config->getMaxExceptions())->toBe(3);
            expect($config->getRetryDelay())->toBe(60);
            expect($config->getRetryUntil())->toBe(1700000000);
        });
    });

    describe('hasExceededMaxAttempts', function (): void {
        it('returns false when attempts are below max', function (): void {
            $config = new RetryConfiguration(maxTries: 5);

            expect($config->hasExceededMaxAttempts(2))->toBeFalse();
            expect($config->hasExceededMaxAttempts(4))->toBeFalse();
        });

        it('returns true when attempts equal max', function (): void {
            $config = new RetryConfiguration(maxTries: 3);

            expect($config->hasExceededMaxAttempts(3))->toBeTrue();
        });

        it('returns true when attempts exceed max', function (): void {
            $config = new RetryConfiguration(maxTries: 3);

            expect($config->hasExceededMaxAttempts(5))->toBeTrue();
        });
    });

    describe('hasExceededMaxExceptions', function (): void {
        it('returns false when maxExceptions is null', function (): void {
            $config = new RetryConfiguration(maxExceptions: null);

            expect($config->hasExceededMaxExceptions(100))->toBeFalse();
        });

        it('returns false when exceptions are below max', function (): void {
            $config = new RetryConfiguration(maxExceptions: 5);

            expect($config->hasExceededMaxExceptions(2))->toBeFalse();
        });

        it('returns true when exceptions equal max', function (): void {
            $config = new RetryConfiguration(maxExceptions: 3);

            expect($config->hasExceededMaxExceptions(3))->toBeTrue();
        });

        it('returns true when exceptions exceed max', function (): void {
            $config = new RetryConfiguration(maxExceptions: 3);

            expect($config->hasExceededMaxExceptions(5))->toBeTrue();
        });
    });

    describe('hasExceededRetryDeadline', function (): void {
        it('returns false when retryUntil is null', function (): void {
            $config = new RetryConfiguration(retryUntil: null);

            expect($config->hasExceededRetryDeadline())->toBeFalse();
        });

        it('returns false when deadline is in future', function (): void {
            $futureTime = time() + 3600; // 1 hour from now
            $config = new RetryConfiguration(retryUntil: $futureTime);

            expect($config->hasExceededRetryDeadline())->toBeFalse();
        });

        it('returns true when deadline has passed', function (): void {
            $pastTime = time() - 3600; // 1 hour ago
            $config = new RetryConfiguration(retryUntil: $pastTime);

            expect($config->hasExceededRetryDeadline())->toBeTrue();
        });
    });

    describe('canRetry', function (): void {
        it('returns true when all conditions allow retry', function (): void {
            $config = new RetryConfiguration(
                maxTries: 5,
                maxExceptions: 3,
                retryUntil: time() + 3600,
            );

            expect($config->canRetry(2, 1))->toBeTrue();
        });

        it('returns false when max attempts exceeded', function (): void {
            $config = new RetryConfiguration(maxTries: 3);

            expect($config->canRetry(5))->toBeFalse();
        });

        it('returns false when max exceptions exceeded', function (): void {
            $config = new RetryConfiguration(maxTries: 10, maxExceptions: 2);

            expect($config->canRetry(1, 5))->toBeFalse();
        });

        it('returns false when deadline passed', function (): void {
            $pastTime = time() - 3600;
            $config = new RetryConfiguration(maxTries: 10, retryUntil: $pastTime);

            expect($config->canRetry(1))->toBeFalse();
        });
    });

    describe('getRemainingAttempts', function (): void {
        it('returns correct remaining attempts', function (): void {
            $config = new RetryConfiguration(maxTries: 5);

            expect($config->getRemainingAttempts(1))->toBe(4);
            expect($config->getRemainingAttempts(3))->toBe(2);
            expect($config->getRemainingAttempts(5))->toBe(0);
        });

        it('returns zero when attempts exceed max', function (): void {
            $config = new RetryConfiguration(maxTries: 3);

            expect($config->getRemainingAttempts(10))->toBe(0);
        });
    });

    describe('isFinalAttempt', function (): void {
        it('returns false when not final attempt', function (): void {
            $config = new RetryConfiguration(maxTries: 5);

            expect($config->isFinalAttempt(1))->toBeFalse();
            expect($config->isFinalAttempt(3))->toBeFalse();
        });

        it('returns true when on final attempt', function (): void {
            $config = new RetryConfiguration(maxTries: 5);

            expect($config->isFinalAttempt(4))->toBeTrue(); // 4 is 0-indexed 5th attempt
        });

        it('returns true when beyond final attempt', function (): void {
            $config = new RetryConfiguration(maxTries: 3);

            expect($config->isFinalAttempt(5))->toBeTrue();
        });
    });

    describe('toArray', function (): void {
        it('returns array with maxTries', function (): void {
            $config = new RetryConfiguration(maxTries: 5);

            expect($config->toArray())->toHaveKey('maxTries', 5);
        });

        it('includes retryAfter when set', function (): void {
            $config = new RetryConfiguration(retryDelay: 30);
            $array = $config->toArray();

            expect($array)->toHaveKey('retryAfter', 30);
        });

        it('excludes retryAfter when zero', function (): void {
            $config = new RetryConfiguration(retryDelay: 0);
            $array = $config->toArray();

            expect($array)->not->toHaveKey('retryAfter');
        });

        it('includes retryUntil when set', function (): void {
            $config = new RetryConfiguration(retryUntil: 1700000000);
            $array = $config->toArray();

            expect($array)->toHaveKey('retryUntil', 1700000000);
        });

        it('excludes retryUntil when null', function (): void {
            $config = new RetryConfiguration(retryUntil: null);
            $array = $config->toArray();

            expect($array)->not->toHaveKey('retryUntil');
        });

        it('includes maxExceptions when set', function (): void {
            $config = new RetryConfiguration(maxExceptions: 2);
            $array = $config->toArray();

            expect($array)->toHaveKey('maxExceptions', 2);
        });

        it('excludes maxExceptions when null', function (): void {
            $config = new RetryConfiguration(maxExceptions: null);
            $array = $config->toArray();

            expect($array)->not->toHaveKey('maxExceptions');
        });

        it('returns complete array', function (): void {
            $config = new RetryConfiguration(
                maxTries: 5,
                retryDelay: 30,
                retryUntil: 1700000000,
                maxExceptions: 2,
            );

            $array = $config->toArray();

            expect($array)->toBe([
                'maxTries' => 5,
                'retryAfter' => 30,
                'retryUntil' => 1700000000,
                'maxExceptions' => 2,
            ]);
        });
    });

    describe('constants', function (): void {
        it('has default max tries constant', function (): void {
            expect(RetryConfiguration::DEFAULT_MAX_TRIES)->toBe(3);
        });

        it('has default retry delay constant', function (): void {
            expect(RetryConfiguration::DEFAULT_RETRY_DELAY)->toBe(0);
        });
    });
});

