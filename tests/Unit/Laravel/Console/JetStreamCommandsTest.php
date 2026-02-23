<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LaravelNats\Laravel\Console\Commands\NatsWorkCommand;

describe('NATS Artisan commands (JetStream + nats:work)', function (): void {
    it('defines nats:work command (Phase 4.1)', function (): void {
        $refl = new \ReflectionClass(NatsWorkCommand::class);
        $defaults = $refl->getDefaultProperties();
        expect(isset($defaults['signature']) && str_contains((string) $defaults['signature'], 'nats:work'))->toBeTrue();
    });

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
