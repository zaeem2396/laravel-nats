<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use LaravelNats\Connection\ConnectionManager;
use LaravelNats\JetStream\BasisStreamProvisioner;

it('throws for unknown preset', function (): void {
    $config = new Repository([
        'nats_basis' => [
            'jetstream' => [
                'presets' => [],
            ],
        ],
    ]);

    $cm = $this->app->make(ConnectionManager::class);
    $provisioner = new BasisStreamProvisioner($cm, $config);

    expect(fn () => $provisioner->provision('missing'))->toThrow(InvalidArgumentException::class, 'Unknown JetStream preset');
});

it('throws when preset key is empty', function (): void {
    $config = new Repository(['nats_basis' => ['jetstream' => ['presets' => []]]]);
    $cm = $this->app->make(ConnectionManager::class);
    $provisioner = new BasisStreamProvisioner($cm, $config);

    expect(fn () => $provisioner->provision(''))->toThrow(InvalidArgumentException::class, 'non-empty');
});
