<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Queue;

/**
 * BackoffStrategy calculates delays between retry attempts.
 *
 * Supports:
 * - Fixed delay (same delay for every retry)
 * - Linear backoff (array of specific delays per attempt)
 * - Exponential backoff (delay doubles with each attempt)
 * - Custom callbacks for complex backoff logic
 */
class BackoffStrategy
{
    /**
     * Strategy type constants.
     */
    public const STRATEGY_FIXED = 'fixed';

    public const STRATEGY_LINEAR = 'linear';

    public const STRATEGY_EXPONENTIAL = 'exponential';

    /**
     * Default exponential backoff settings.
     */
    public const DEFAULT_BASE_DELAY = 1;

    public const DEFAULT_MULTIPLIER = 2.0;

    public const DEFAULT_MAX_DELAY = 3600; // 1 hour

    /**
     * The strategy type.
     */
    protected string $type;

    /**
     * Fixed delay in seconds.
     */
    protected int $fixedDelay;

    /**
     * Array of delays for linear backoff.
     *
     * @var array<int, int>
     */
    protected array $linearDelays;

    /**
     * Base delay for exponential backoff.
     */
    protected int $baseDelay;

    /**
     * Multiplier for exponential backoff.
     */
    protected float $multiplier;

    /**
     * Maximum delay cap.
     */
    protected int $maxDelay;

    /**
     * Random jitter percentage (0-100).
     */
    protected int $jitterPercent;

    /**
     * Create a new backoff strategy instance.
     *
     * @param string $type Strategy type
     * @param int $fixedDelay Fixed delay in seconds
     * @param array<int, int> $linearDelays Linear delays array
     * @param int $baseDelay Base delay for exponential
     * @param float $multiplier Exponential multiplier
     * @param int $maxDelay Maximum delay cap
     * @param int $jitterPercent Jitter percentage (0-100)
     */
    public function __construct(
        string $type = self::STRATEGY_FIXED,
        int $fixedDelay = 0,
        array $linearDelays = [],
        int $baseDelay = self::DEFAULT_BASE_DELAY,
        float $multiplier = self::DEFAULT_MULTIPLIER,
        int $maxDelay = self::DEFAULT_MAX_DELAY,
        int $jitterPercent = 0,
    ) {
        $this->type = $type;
        $this->fixedDelay = $fixedDelay;
        $this->linearDelays = $linearDelays;
        $this->baseDelay = $baseDelay;
        $this->multiplier = $multiplier;
        $this->maxDelay = $maxDelay;
        $this->jitterPercent = max(0, min(100, $jitterPercent));
    }

    /**
     * Create a fixed delay strategy.
     *
     * @param int $delay Delay in seconds
     *
     * @return self
     */
    public static function fixed(int $delay): self
    {
        return new self(
            type: self::STRATEGY_FIXED,
            fixedDelay: $delay,
        );
    }

    /**
     * Create a linear backoff strategy from an array of delays.
     *
     * @param array<int, int> $delays Array of delays in seconds
     *
     * @return self
     */
    public static function linear(array $delays): self
    {
        return new self(
            type: self::STRATEGY_LINEAR,
            linearDelays: array_values($delays),
        );
    }

    /**
     * Create an exponential backoff strategy.
     *
     * @param int $baseDelay Initial delay in seconds
     * @param float $multiplier Growth multiplier (typically 2.0)
     * @param int $maxDelay Maximum delay cap
     * @param int $jitterPercent Random jitter percentage
     *
     * @return self
     */
    public static function exponential(
        int $baseDelay = self::DEFAULT_BASE_DELAY,
        float $multiplier = self::DEFAULT_MULTIPLIER,
        int $maxDelay = self::DEFAULT_MAX_DELAY,
        int $jitterPercent = 0,
    ): self {
        return new self(
            type: self::STRATEGY_EXPONENTIAL,
            baseDelay: $baseDelay,
            multiplier: $multiplier,
            maxDelay: $maxDelay,
            jitterPercent: $jitterPercent,
        );
    }

    /**
     * Create a strategy from a Laravel job backoff configuration.
     *
     * @param array<int>|int|null $backoff The backoff configuration
     * @param int $defaultDelay Default delay if backoff is null
     *
     * @return self
     */
    public static function fromBackoff(array|int|null $backoff, int $defaultDelay = 0): self
    {
        if ($backoff === null) {
            return self::fixed($defaultDelay);
        }

        if (is_int($backoff)) {
            return self::fixed($backoff);
        }

        if (is_array($backoff) && count($backoff) > 0) {
            return self::linear($backoff);
        }

        return self::fixed($defaultDelay);
    }

    /**
     * Calculate the delay for a given attempt.
     *
     * @param int $attempt The attempt number (1-indexed)
     *
     * @return int Delay in seconds
     */
    public function getDelay(int $attempt): int
    {
        $delay = match ($this->type) {
            self::STRATEGY_LINEAR => $this->calculateLinearDelay($attempt),
            self::STRATEGY_EXPONENTIAL => $this->calculateExponentialDelay($attempt),
            default => $this->fixedDelay,
        };

        // Apply jitter if configured
        if ($this->jitterPercent > 0) {
            $delay = $this->applyJitter($delay);
        }

        // Apply max delay cap
        return min($delay, $this->maxDelay);
    }

    /**
     * Get the strategy type.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the maximum delay cap.
     *
     * @return int
     */
    public function getMaxDelay(): int
    {
        return $this->maxDelay;
    }

    /**
     * Get the jitter percentage.
     *
     * @return int
     */
    public function getJitterPercent(): int
    {
        return $this->jitterPercent;
    }

    /**
     * Create a new instance with jitter enabled.
     *
     * @param int $percent Jitter percentage (0-100)
     *
     * @return self
     */
    public function withJitter(int $percent): self
    {
        $clone = clone $this;
        $clone->jitterPercent = max(0, min(100, $percent));

        return $clone;
    }

    /**
     * Create a new instance with a maximum delay cap.
     *
     * @param int $maxDelay Maximum delay in seconds
     *
     * @return self
     */
    public function withMaxDelay(int $maxDelay): self
    {
        $clone = clone $this;
        $clone->maxDelay = $maxDelay;

        return $clone;
    }

    /**
     * Generate a sequence of delays for the given number of attempts.
     *
     * @param int $attempts Number of attempts
     *
     * @return array<int, int> Array of delays
     */
    public function getDelaySequence(int $attempts): array
    {
        $sequence = [];

        for ($i = 1; $i <= $attempts; $i++) {
            $sequence[] = $this->getDelay($i);
        }

        return $sequence;
    }

    /**
     * Get total delay for all attempts.
     *
     * @param int $attempts Number of attempts
     *
     * @return int Total delay in seconds
     */
    public function getTotalDelay(int $attempts): int
    {
        return array_sum($this->getDelaySequence($attempts));
    }

    /**
     * Calculate linear delay for an attempt.
     *
     * @param int $attempt The attempt number (1-indexed)
     *
     * @return int Delay in seconds
     */
    protected function calculateLinearDelay(int $attempt): int
    {
        if (empty($this->linearDelays)) {
            return $this->fixedDelay;
        }

        // Convert to 0-indexed
        $index = $attempt - 1;

        // Use the last delay if attempts exceed array length
        if ($index >= count($this->linearDelays)) {
            return $this->linearDelays[count($this->linearDelays) - 1];
        }

        return $this->linearDelays[$index] ?? 0;
    }

    /**
     * Calculate exponential delay for an attempt.
     *
     * Formula: baseDelay * (multiplier ^ (attempt - 1))
     *
     * @param int $attempt The attempt number (1-indexed)
     *
     * @return int Delay in seconds
     */
    protected function calculateExponentialDelay(int $attempt): int
    {
        $exponent = max(0, $attempt - 1);
        $delay = $this->baseDelay * pow($this->multiplier, $exponent);

        return (int) min($delay, $this->maxDelay);
    }

    /**
     * Apply random jitter to a delay.
     *
     * @param int $delay The base delay
     *
     * @return int The delay with jitter applied
     */
    protected function applyJitter(int $delay): int
    {
        if ($delay === 0) {
            return 0;
        }

        $jitterAmount = (int) ($delay * ($this->jitterPercent / 100));
        $jitter = random_int(-$jitterAmount, $jitterAmount);

        return max(0, $delay + $jitter);
    }
}
