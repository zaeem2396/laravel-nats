<?php

declare(strict_types=1);

use LaravelNats\Laravel\Queue\BackoffStrategy;

describe('BackoffStrategy', function (): void {
    describe('fixed strategy', function (): void {
        it('creates fixed strategy with delay', function (): void {
            $strategy = BackoffStrategy::fixed(30);

            expect($strategy->getType())->toBe(BackoffStrategy::STRATEGY_FIXED);
            expect($strategy->getDelay(1))->toBe(30);
            expect($strategy->getDelay(5))->toBe(30);
            expect($strategy->getDelay(100))->toBe(30);
        });

        it('returns zero for zero delay', function (): void {
            $strategy = BackoffStrategy::fixed(0);

            expect($strategy->getDelay(1))->toBe(0);
        });
    });

    describe('linear strategy', function (): void {
        it('creates linear strategy from array', function (): void {
            $strategy = BackoffStrategy::linear([10, 30, 60, 120]);

            expect($strategy->getType())->toBe(BackoffStrategy::STRATEGY_LINEAR);
        });

        it('returns delays based on attempt index', function (): void {
            $strategy = BackoffStrategy::linear([10, 30, 60, 120]);

            expect($strategy->getDelay(1))->toBe(10);
            expect($strategy->getDelay(2))->toBe(30);
            expect($strategy->getDelay(3))->toBe(60);
            expect($strategy->getDelay(4))->toBe(120);
        });

        it('uses last delay for attempts beyond array length', function (): void {
            $strategy = BackoffStrategy::linear([10, 30, 60]);

            expect($strategy->getDelay(4))->toBe(60);
            expect($strategy->getDelay(10))->toBe(60);
        });

        it('handles empty array', function (): void {
            $strategy = BackoffStrategy::linear([]);

            expect($strategy->getDelay(1))->toBe(0);
        });

        it('handles single element array', function (): void {
            $strategy = BackoffStrategy::linear([45]);

            expect($strategy->getDelay(1))->toBe(45);
            expect($strategy->getDelay(5))->toBe(45);
        });
    });

    describe('exponential strategy', function (): void {
        it('creates exponential strategy with defaults', function (): void {
            $strategy = BackoffStrategy::exponential();

            expect($strategy->getType())->toBe(BackoffStrategy::STRATEGY_EXPONENTIAL);
        });

        it('calculates exponential delays', function (): void {
            // With base=1 and multiplier=2: 1, 2, 4, 8, 16...
            $strategy = BackoffStrategy::exponential(1, 2.0, 3600);

            expect($strategy->getDelay(1))->toBe(1);   // 1 * 2^0 = 1
            expect($strategy->getDelay(2))->toBe(2);   // 1 * 2^1 = 2
            expect($strategy->getDelay(3))->toBe(4);   // 1 * 2^2 = 4
            expect($strategy->getDelay(4))->toBe(8);   // 1 * 2^3 = 8
            expect($strategy->getDelay(5))->toBe(16);  // 1 * 2^4 = 16
        });

        it('respects custom base delay', function (): void {
            // With base=5 and multiplier=2: 5, 10, 20, 40...
            $strategy = BackoffStrategy::exponential(5, 2.0, 3600);

            expect($strategy->getDelay(1))->toBe(5);
            expect($strategy->getDelay(2))->toBe(10);
            expect($strategy->getDelay(3))->toBe(20);
        });

        it('respects custom multiplier', function (): void {
            // With base=10 and multiplier=3: 10, 30, 90, 270...
            $strategy = BackoffStrategy::exponential(10, 3.0, 3600);

            expect($strategy->getDelay(1))->toBe(10);
            expect($strategy->getDelay(2))->toBe(30);
            expect($strategy->getDelay(3))->toBe(90);
        });

        it('caps delay at max delay', function (): void {
            $strategy = BackoffStrategy::exponential(1, 2.0, 100);

            expect($strategy->getDelay(10))->toBe(100); // Would be 512, capped at 100
        });
    });

    describe('fromBackoff factory', function (): void {
        it('creates fixed strategy from null', function (): void {
            $strategy = BackoffStrategy::fromBackoff(null, 60);

            expect($strategy->getType())->toBe(BackoffStrategy::STRATEGY_FIXED);
            expect($strategy->getDelay(1))->toBe(60);
        });

        it('creates fixed strategy from integer', function (): void {
            $strategy = BackoffStrategy::fromBackoff(30, 60);

            expect($strategy->getType())->toBe(BackoffStrategy::STRATEGY_FIXED);
            expect($strategy->getDelay(1))->toBe(30);
        });

        it('creates linear strategy from array', function (): void {
            $strategy = BackoffStrategy::fromBackoff([10, 20, 30], 60);

            expect($strategy->getType())->toBe(BackoffStrategy::STRATEGY_LINEAR);
            expect($strategy->getDelay(1))->toBe(10);
            expect($strategy->getDelay(2))->toBe(20);
        });

        it('uses default delay for empty array', function (): void {
            $strategy = BackoffStrategy::fromBackoff([], 60);

            expect($strategy->getType())->toBe(BackoffStrategy::STRATEGY_FIXED);
            expect($strategy->getDelay(1))->toBe(60);
        });
    });

    describe('jitter', function (): void {
        it('returns zero for zero delay with jitter', function (): void {
            $strategy = BackoffStrategy::fixed(0)->withJitter(20);

            expect($strategy->getDelay(1))->toBe(0);
        });

        it('applies jitter within expected range', function (): void {
            $strategy = BackoffStrategy::fixed(100)->withJitter(20);
            $delays = [];

            // Run multiple times to test randomness
            for ($i = 0; $i < 100; $i++) {
                $delays[] = $strategy->getDelay(1);
            }

            // All delays should be between 80 and 120 (100 ± 20%)
            foreach ($delays as $delay) {
                expect($delay)->toBeGreaterThanOrEqual(80);
                expect($delay)->toBeLessThanOrEqual(120);
            }
        });

        it('clamps jitter percentage', function (): void {
            $strategy1 = BackoffStrategy::fixed(100)->withJitter(-10);
            expect($strategy1->getJitterPercent())->toBe(0);

            $strategy2 = BackoffStrategy::fixed(100)->withJitter(150);
            expect($strategy2->getJitterPercent())->toBe(100);
        });
    });

    describe('withMaxDelay', function (): void {
        it('creates new instance with max delay', function (): void {
            $original = BackoffStrategy::exponential(1, 2.0, 3600);
            $modified = $original->withMaxDelay(100);

            expect($modified->getMaxDelay())->toBe(100);
            expect($original->getMaxDelay())->toBe(3600); // Original unchanged
        });

        it('caps exponential delay at new max', function (): void {
            $strategy = BackoffStrategy::exponential(1, 2.0, 3600)->withMaxDelay(50);

            expect($strategy->getDelay(10))->toBe(50); // Would be 512
        });
    });

    describe('getDelaySequence', function (): void {
        it('returns sequence of delays for fixed strategy', function (): void {
            $strategy = BackoffStrategy::fixed(30);

            expect($strategy->getDelaySequence(3))->toBe([30, 30, 30]);
        });

        it('returns sequence of delays for linear strategy', function (): void {
            $strategy = BackoffStrategy::linear([10, 20, 30, 40]);

            expect($strategy->getDelaySequence(4))->toBe([10, 20, 30, 40]);
        });

        it('returns sequence of delays for exponential strategy', function (): void {
            $strategy = BackoffStrategy::exponential(1, 2.0, 1000);

            expect($strategy->getDelaySequence(5))->toBe([1, 2, 4, 8, 16]);
        });

        it('returns empty array for zero attempts', function (): void {
            $strategy = BackoffStrategy::fixed(30);

            expect($strategy->getDelaySequence(0))->toBe([]);
        });
    });

    describe('getTotalDelay', function (): void {
        it('calculates total delay for fixed strategy', function (): void {
            $strategy = BackoffStrategy::fixed(30);

            expect($strategy->getTotalDelay(3))->toBe(90);
        });

        it('calculates total delay for linear strategy', function (): void {
            $strategy = BackoffStrategy::linear([10, 20, 30]);

            expect($strategy->getTotalDelay(3))->toBe(60);
        });

        it('calculates total delay for exponential strategy', function (): void {
            $strategy = BackoffStrategy::exponential(1, 2.0, 1000);

            expect($strategy->getTotalDelay(5))->toBe(31); // 1+2+4+8+16
        });

        it('returns zero for zero attempts', function (): void {
            $strategy = BackoffStrategy::fixed(30);

            expect($strategy->getTotalDelay(0))->toBe(0);
        });
    });

    describe('constants', function (): void {
        it('has strategy type constants', function (): void {
            expect(BackoffStrategy::STRATEGY_FIXED)->toBe('fixed');
            expect(BackoffStrategy::STRATEGY_LINEAR)->toBe('linear');
            expect(BackoffStrategy::STRATEGY_EXPONENTIAL)->toBe('exponential');
        });

        it('has default constants', function (): void {
            expect(BackoffStrategy::DEFAULT_BASE_DELAY)->toBe(1);
            expect(BackoffStrategy::DEFAULT_MULTIPLIER)->toBe(2.0);
            expect(BackoffStrategy::DEFAULT_MAX_DELAY)->toBe(3600);
        });
    });

    describe('immutability', function (): void {
        it('withJitter returns new instance', function (): void {
            $original = BackoffStrategy::fixed(100);
            $modified = $original->withJitter(20);

            expect($original)->not->toBe($modified);
            expect($original->getJitterPercent())->toBe(0);
            expect($modified->getJitterPercent())->toBe(20);
        });

        it('withMaxDelay returns new instance', function (): void {
            $original = BackoffStrategy::exponential();
            $modified = $original->withMaxDelay(100);

            expect($original)->not->toBe($modified);
            expect($original->getMaxDelay())->toBe(3600);
            expect($modified->getMaxDelay())->toBe(100);
        });
    });
});
