<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('registers nats:ping Artisan command', function (): void {
    $commands = Artisan::all();

    expect($commands)->toHaveKey('nats:ping');
});
