<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Queue;

/**
 * RetryConfiguration handles the configuration and calculation of job retry behavior.
 *
 * This class provides:
 * - Configurable maximum attempts
 * - Per-job retry settings
 * - Retry delay calculations
 * - Retry deadline management
 */
class RetryConfiguration
{
    /**
     * Default maximum number of attempts.
     */
    public const DEFAULT_MAX_TRIES = 3;

    /**
     * Default retry delay in seconds.
     */
    public const DEFAULT_RETRY_DELAY = 0;

    /**
     * The maximum number of attempts.
     */
    protected int $maxTries;

    /**
     * The retry delay in seconds.
     */
    protected int $retryDelay;

    /**
     * The Unix timestamp when retries should stop.
     */
    protected ?int $retryUntil;

    /**
     * The maximum number of exceptions before failing.
     */
    protected ?int $maxExceptions;

    /**
     * Create a new retry configuration instance.
     *
     * @param int $maxTries Maximum number of attempts
     * @param int $retryDelay Delay between retries in seconds
     * @param int|null $retryUntil Unix timestamp deadline
     * @param int|null $maxExceptions Maximum exceptions allowed
     */
    public function __construct(
        int $maxTries = self::DEFAULT_MAX_TRIES,
        int $retryDelay = self::DEFAULT_RETRY_DELAY,
        ?int $retryUntil = null,
        ?int $maxExceptions = null,
    ) {
        $this->maxTries = $maxTries;
        $this->retryDelay = $retryDelay;
        $this->retryUntil = $retryUntil;
        $this->maxExceptions = $maxExceptions;
    }

    /**
     * Create a retry configuration from a job payload.
     *
     * @param array<string, mixed> $payload The job payload
     * @param int $defaultMaxTries Default max tries if not in payload
     * @param int $defaultRetryDelay Default retry delay if not in payload
     *
     * @return self
     */
    public static function fromPayload(
        array $payload,
        int $defaultMaxTries = self::DEFAULT_MAX_TRIES,
        int $defaultRetryDelay = self::DEFAULT_RETRY_DELAY,
    ): self {
        return new self(
            maxTries: (int) ($payload['maxTries'] ?? $defaultMaxTries),
            retryDelay: (int) ($payload['retryAfter'] ?? $payload['backoff'] ?? $defaultRetryDelay),
            retryUntil: isset($payload['retryUntil']) ? (int) $payload['retryUntil'] : null,
            maxExceptions: isset($payload['maxExceptions']) ? (int) $payload['maxExceptions'] : null,
        );
    }

    /**
     * Get the maximum number of attempts.
     *
     * @return int
     */
    public function getMaxTries(): int
    {
        return $this->maxTries;
    }

    /**
     * Get the retry delay in seconds.
     *
     * @return int
     */
    public function getRetryDelay(): int
    {
        return $this->retryDelay;
    }

    /**
     * Get the retry until timestamp.
     *
     * @return int|null
     */
    public function getRetryUntil(): ?int
    {
        return $this->retryUntil;
    }

    /**
     * Get the maximum exceptions allowed.
     *
     * @return int|null
     */
    public function getMaxExceptions(): ?int
    {
        return $this->maxExceptions;
    }

    /**
     * Check if the given attempt count has exceeded maximum attempts.
     *
     * @param int $attempts Current attempt count
     *
     * @return bool
     */
    public function hasExceededMaxAttempts(int $attempts): bool
    {
        return $attempts >= $this->maxTries;
    }

    /**
     * Check if the given exception count has exceeded maximum exceptions.
     *
     * @param int $exceptions Current exception count
     *
     * @return bool
     */
    public function hasExceededMaxExceptions(int $exceptions): bool
    {
        if ($this->maxExceptions === null) {
            return false;
        }

        return $exceptions >= $this->maxExceptions;
    }

    /**
     * Check if the retry deadline has passed.
     *
     * @return bool
     */
    public function hasExceededRetryDeadline(): bool
    {
        if ($this->retryUntil === null) {
            return false;
        }

        return time() >= $this->retryUntil;
    }

    /**
     * Determine if a retry is allowed given the current state.
     *
     * @param int $attempts Current attempt count
     * @param int $exceptions Current exception count
     *
     * @return bool
     */
    public function canRetry(int $attempts, int $exceptions = 0): bool
    {
        // Check attempt limit
        if ($this->hasExceededMaxAttempts($attempts)) {
            return false;
        }

        // Check exception limit
        if ($this->hasExceededMaxExceptions($exceptions)) {
            return false;
        }

        // Check time deadline
        if ($this->hasExceededRetryDeadline()) {
            return false;
        }

        return true;
    }

    /**
     * Get the number of remaining attempts.
     *
     * @param int $currentAttempts Current attempt count
     *
     * @return int
     */
    public function getRemainingAttempts(int $currentAttempts): int
    {
        return max(0, $this->maxTries - $currentAttempts);
    }

    /**
     * Check if this is the final attempt.
     *
     * @param int $currentAttempts Current attempt count
     *
     * @return bool
     */
    public function isFinalAttempt(int $currentAttempts): bool
    {
        return $currentAttempts >= $this->maxTries - 1;
    }

    /**
     * Convert the configuration to an array for storage in payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $config = [
            'maxTries' => $this->maxTries,
        ];

        if ($this->retryDelay > 0) {
            $config['retryAfter'] = $this->retryDelay;
        }

        if ($this->retryUntil !== null) {
            $config['retryUntil'] = $this->retryUntil;
        }

        if ($this->maxExceptions !== null) {
            $config['maxExceptions'] = $this->maxExceptions;
        }

        return $config;
    }
}
