<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('registers NatsV2 JetStream Artisan commands', function (): void {
    $commands = Artisan::all();

    expect($commands)->toHaveKey('nats:v2:jetstream:info')
        ->and($commands)->toHaveKey('nats:v2:jetstream:streams')
        ->and($commands)->toHaveKey('nats:v2:jetstream:pull')
        ->and($commands)->toHaveKey('nats:v2:jetstream:provision');
});
