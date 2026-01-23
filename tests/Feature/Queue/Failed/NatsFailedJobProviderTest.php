<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LaravelNats\Laravel\Queue\Failed\NatsFailedJobProvider;

beforeEach(function (): void {
    // Mock DB facade
    DB::shouldReceive('connection')
        ->andReturnSelf();
    DB::shouldReceive('table')
        ->andReturnSelf();
});

describe('NatsFailedJobProvider', function (): void {
    describe('log', function (): void {
        it('stores failed job in database', function (): void {
            $tableMock = Mockery::mock();
            $tableMock->shouldReceive('insertGetId')
                ->once()
                ->with(Mockery::on(function ($data) {
                    return $data['uuid'] === 'test-uuid'
                        && $data['connection'] === 'nats'
                        && $data['queue'] === 'default';
                }))
                ->andReturn(1);

            DB::shouldReceive('connection')
                ->with('testing')
                ->andReturnSelf();
            DB::shouldReceive('table')
                ->with('failed_jobs')
                ->andReturn($tableMock);

            $provider = new NatsFailedJobProvider('testing', 'failed_jobs');
            $payload = json_encode(['uuid' => 'test-uuid', 'job' => 'TestJob']);
            $exception = new RuntimeException('Test exception');

            $id = $provider->log('nats', 'default', $payload, $exception);

            expect($id)->toBe(1);
        });

        it('extracts job ID from payload uuid', function (): void {
            $provider = new NatsFailedJobProvider('testing', 'failed_jobs');
            $payload = json_encode(['uuid' => 'custom-uuid-123']);
            $exception = new RuntimeException('Test');

            $id = $provider->log('nats', 'queue', $payload, $exception);

            $job = DB::connection('testing')->table('failed_jobs')->find($id);
            expect($job->uuid)->toBe('custom-uuid-123');
        });

        it('extracts job ID from payload id field', function (): void {
            $provider = new NatsFailedJobProvider('testing', 'failed_jobs');
            $payload = json_encode(['id' => 'job-id-456']);
            $exception = new RuntimeException('Test');

            $id = $provider->log('nats', 'queue', $payload, $exception);

            $job = DB::connection('testing')->table('failed_jobs')->find($id);
            expect($job->uuid)->toBe('job-id-456');
        });

        it('generates UUID from payload hash if no ID found', function (): void {
            $provider = new NatsFailedJobProvider('testing', 'failed_jobs');
            $payload = json_encode(['data' => 'some data']);
            $exception = new RuntimeException('Test');

            $id = $provider->log('nats', 'queue', $payload, $exception);

            $job = DB::connection('testing')->table('failed_jobs')->find($id);
            expect($job->uuid)->toBeString();
            expect(strlen($job->uuid))->toBeGreaterThan(0);
        });
    });

    describe('all', function (): void {
        it('returns all failed jobs ordered by id desc', function (): void {
            $provider = new NatsFailedJobProvider('testing', 'failed_jobs');
            $exception = new RuntimeException('Test');

            $provider->log('nats', 'queue1', json_encode(['uuid' => '1']), $exception);
            $provider->log('nats', 'queue2', json_encode(['uuid' => '2']), $exception);
            $provider->log('nats', 'queue3', json_encode(['uuid' => '3']), $exception);

            $all = $provider->all();

            expect($all)->toBeArray();
            expect(count($all))->toBe(3);
            expect($all[0]['uuid'])->toBe('3');
            expect($all[2]['uuid'])->toBe('1');
        });

        it('returns empty array when no failed jobs', function (): void {
            $provider = new NatsFailedJobProvider('testing', 'failed_jobs');

            $all = $provider->all();

            expect($all)->toBeArray();
            expect(count($all))->toBe(0);
        });
    });

    describe('find', function (): void {
        it('returns failed job by id', function (): void {
            $provider = new NatsFailedJobProvider('testing', 'failed_jobs');
            $exception = new RuntimeException('Test');
            $id = $provider->log('nats', 'queue', json_encode(['uuid' => 'test']), $exception);

            $job = $provider->find($id);

            expect($job)->toBeArray();
            expect($job['uuid'])->toBe('test');
            expect($job['connection'])->toBe('nats');
        });

        it('returns null for non-existent job', function (): void {
            $provider = new NatsFailedJobProvider('testing', 'failed_jobs');

            $job = $provider->find(99999);

            expect($job)->toBeNull();
        });
    });

    describe('forget', function (): void {
        it('deletes failed job by id', function (): void {
            $provider = new NatsFailedJobProvider('testing', 'failed_jobs');
            $exception = new RuntimeException('Test');
            $id = $provider->log('nats', 'queue', json_encode(['uuid' => 'test']), $exception);

            $result = $provider->forget($id);

            expect($result)->toBeTrue();
            expect(DB::connection('testing')->table('failed_jobs')->count())->toBe(0);
        });

        it('returns false for non-existent job', function (): void {
            $provider = new NatsFailedJobProvider('testing', 'failed_jobs');

            $result = $provider->forget(99999);

            expect($result)->toBeFalse();
        });
    });

    describe('flush', function (): void {
        it('deletes all failed jobs', function (): void {
            $provider = new NatsFailedJobProvider('testing', 'failed_jobs');
            $exception = new RuntimeException('Test');

            $provider->log('nats', 'queue1', json_encode(['uuid' => '1']), $exception);
            $provider->log('nats', 'queue2', json_encode(['uuid' => '2']), $exception);

            expect(DB::connection('testing')->table('failed_jobs')->count())->toBe(2);

            $provider->flush();

            expect(DB::connection('testing')->table('failed_jobs')->count())->toBe(0);
        });
    });
});

