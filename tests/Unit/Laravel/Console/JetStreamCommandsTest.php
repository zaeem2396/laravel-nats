<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

describe('JetStream Artisan commands', function (): void {
    it('registers nats:stream:purge command', function (): void {
        $commands = Artisan::all();

        expect(array_key_exists('nats:stream:purge', $commands))->toBeTrue();
    });

    it('registers nats:stream:update command', function (): void {
        $commands = Artisan::all();

        expect(array_key_exists('nats:stream:update', $commands))->toBeTrue();
    });

    it('registers nats:jetstream:status command', function (): void {
        $commands = Artisan::all();

        expect(array_key_exists('nats:jetstream:status', $commands))->toBeTrue();
    });
});
