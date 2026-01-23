<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LaravelNats\Laravel\Queue\Failed\NatsFailedJobProvider;

describe('NatsFailedJobProvider', function (): void {
    describe('interface compliance', function (): void {
        it('implements FailedJobProviderInterface', function (): void {
            $provider = new NatsFailedJobProvider('default', 'failed_jobs');

            expect($provider)->toBeInstanceOf(\Illuminate\Queue\Failed\FailedJobProviderInterface::class);
        });
    });

    describe('extractJobId', function (): void {
        it('extracts uuid from payload', function (): void {
            $provider = new NatsFailedJobProvider('default', 'failed_jobs');
            $payload = json_encode(['uuid' => 'test-uuid-123']);

            // Use reflection to test protected method
            $reflection = new ReflectionClass($provider);
            $method = $reflection->getMethod('extractJobId');
            $method->setAccessible(true);

            $id = $method->invoke($provider, $payload);

            expect($id)->toBe('test-uuid-123');
        });

        it('extracts id from payload when uuid not present', function (): void {
            $provider = new NatsFailedJobProvider('default', 'failed_jobs');
            $payload = json_encode(['id' => 'job-id-456']);

            $reflection = new ReflectionClass($provider);
            $method = $reflection->getMethod('extractJobId');
            $method->setAccessible(true);

            $id = $method->invoke($provider, $payload);

            expect($id)->toBe('job-id-456');
        });

        it('generates hash when no id found', function (): void {
            $provider = new NatsFailedJobProvider('default', 'failed_jobs');
            $payload = json_encode(['data' => 'some data']);

            $reflection = new ReflectionClass($provider);
            $method = $reflection->getMethod('extractJobId');
            $method->setAccessible(true);

            $id = $method->invoke($provider, $payload);

            expect($id)->toBeString();
            expect(strlen($id))->toBeGreaterThan(0);
            expect($id)->toBe(md5($payload));
        });
    });

    describe('method signatures', function (): void {
        it('has correct log signature', function (): void {
            $provider = new NatsFailedJobProvider('default', 'failed_jobs');
            $reflection = new ReflectionClass($provider);
            $method = $reflection->getMethod('log');

            expect($method->getNumberOfParameters())->toBe(4);
        });

        it('has correct ids signature', function (): void {
            $provider = new NatsFailedJobProvider('default', 'failed_jobs');
            $reflection = new ReflectionClass($provider);
            $method = $reflection->getMethod('ids');

            expect($method->getNumberOfParameters())->toBe(1);
            expect($method->getParameters()[0]->isOptional())->toBeTrue();
        });

        it('has correct flush signature', function (): void {
            $provider = new NatsFailedJobProvider('default', 'failed_jobs');
            $reflection = new ReflectionClass($provider);
            $method = $reflection->getMethod('flush');

            expect($method->getNumberOfParameters())->toBe(1);
            expect($method->getParameters()[0]->isOptional())->toBeTrue();
        });
    });
});
