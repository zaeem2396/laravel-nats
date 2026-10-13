<?php

declare(strict_types=1);

use Basis\Nats\Client as BasisClient;
use Basis\Nats\Message\Payload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use LaravelNats\Connection\ConnectionManager;
use LaravelNats\Laravel\Facades\Nats;
use LaravelNats\Laravel\Facades\NatsV2;
use LaravelNats\Laravel\NatsManager;
use LaravelNats\Laravel\NatsV2Gateway;
use LaravelNats\Laravel\Providers\NatsServiceProvider;
use LaravelNats\Laravel\Queue\BasisNatsQueue;
use LaravelNats\Laravel\Queue\NatsJob;
use Mockery\MockInterface;

/*
| Framework-level checks run against every Laravel major in the CI matrix (10-13).
| No NATS server is needed: the basis client is swapped for a Mockery spy.
*/

final class LaravelNatsCompatPingJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public static int $handled = 0;

    public function __construct(public string $orderId = 'ord-1') {}

    public function handle(): void
    {
        self::$handled++;
    }
}

/**
 * @return array{0: MockInterface, 1: ArrayObject<int, array{subject: string, payload: string}>}
 */
function compatSpyBasisClient(Application $app): array
{
    $published = new ArrayObject;
    $client = Mockery::spy(BasisClient::class);
    $client->shouldReceive('publish')->andReturnUsing(function (string $subject, mixed $payload) use ($client, $published) {
        $published[] = ['subject' => $subject, 'payload' => (string) $payload];

        return $client;
    });

    (new ReflectionProperty(ConnectionManager::class, 'clients'))
        ->setValue($app->make(ConnectionManager::class), ['default' => $client]);

    return [$client, $published];
}

beforeEach(function (): void {
    config()->set('queue.connections.nats_basis', [
        'driver' => 'nats_basis',
        'queue' => 'compat',
    ]);
    LaravelNatsCompatPingJob::$handled = 0;
});

it('runs on a supported Laravel major version', function (): void {
    $major = (int) explode('.', $this->app->version())[0];

    expect($major)->toBeGreaterThanOrEqual(10)->toBeLessThanOrEqual(13);
});

it('resolves the nats_basis queue connection through the queue manager', function (): void {
    expect(Queue::connection('nats_basis'))->toBeInstanceOf(BasisNatsQueue::class);
});

it('dispatches a queued job through the bus onto the nats_basis subject', function (): void {
    [, $published] = compatSpyBasisClient($this->app);

    LaravelNatsCompatPingJob::dispatch('ord-42')->onConnection('nats_basis');

    expect($published)->toHaveCount(1)
        ->and($published[0]['subject'])->toBe('laravel.queue.compat');

    $payload = json_decode($published[0]['payload'], true);

    expect($payload)->toHaveKeys(['uuid', 'displayName', 'job', 'data'])
        ->and($payload['displayName'])->toBe(LaravelNatsCompatPingJob::class)
        ->and($payload['data']['commandName'])->toBe(LaravelNatsCompatPingJob::class);
});

it('fires a dispatched job payload through NatsJob with the framework call handler', function (): void {
    [, $published] = compatSpyBasisClient($this->app);
    LaravelNatsCompatPingJob::dispatch()->onConnection('nats_basis');

    $queue = Queue::connection('nats_basis');
    $job = new NatsJob($this->app, $queue, $published[0]['payload'], 'nats_basis', 'compat');

    expect($job->uuid())->toBe($job->getJobId())
        ->and($job->resolveName())->toBe(LaravelNatsCompatPingJob::class);

    $job->fire();

    expect(LaravelNatsCompatPingJob::$handled)->toBe(1)
        ->and($job->isDeleted())->toBeTrue();
});

it('pops a published job back off the nats_basis queue', function (): void {
    [$client, $published] = compatSpyBasisClient($this->app);
    LaravelNatsCompatPingJob::dispatch()->onConnection('nats_basis');

    $handler = null;
    $client->shouldReceive('subscribe')->andReturnUsing(function (string $subject, callable $cb) use (&$handler, $client) {
        $handler = $cb;

        return $client;
    });
    $client->shouldReceive('process')->andReturnUsing(function () use (&$handler, $published) {
        $handler(new Payload($published[0]['payload']), null);

        return null;
    });

    $job = Queue::connection('nats_basis')->pop();

    expect($job)->toBeInstanceOf(NatsJob::class)
        ->and($job->getConnectionName())->toBe('nats_basis')
        ->and($job->getQueue())->toBe('compat')
        ->and($job->resolveName())->toBe(LaravelNatsCompatPingJob::class);
});

it('registers the package Artisan commands', function (): void {
    expect(array_keys(Artisan::all()))->toContain(
        'nats:ping',
        'nats:work',
        'nats:v2:listen',
        'nats:v2:config:validate',
        'nats:v2:jetstream:pull',
    );
});

it('registers the nats-config publish tag for both config files', function (): void {
    $paths = ServiceProvider::pathsToPublish(NatsServiceProvider::class, 'nats-config');

    expect($paths)->toHaveCount(2)
        ->and(array_map('basename', array_values($paths)))->toBe(['nats.php', 'nats_basis.php']);
});

it('resolves both facades', function (): void {
    expect(Nats::getFacadeRoot())->toBeInstanceOf(NatsManager::class)
        ->and(NatsV2::getFacadeRoot())->toBeInstanceOf(NatsV2Gateway::class);
});
