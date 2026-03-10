<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LaravelNats\Laravel\Console\Commands\NatsConsumeCommand;
use LaravelNats\Laravel\Console\Commands\NatsConsumeStreamCommand;
use LaravelNats\Laravel\Console\Commands\NatsWorkCommand;

describe('NATS Artisan commands (JetStream + nats:work + nats:consume + nats:consume:stream — Phase 4.3)', function (): void {
    it('defines nats:consume command (Phase 4.2 — Subject-Based Consumer)', function (): void {
        $refl = new \ReflectionClass(NatsConsumeCommand::class);
        $defaults = $refl->getDefaultProperties();
        expect(isset($defaults['signature']) && str_contains((string) $defaults['signature'], 'nats:consume'))->toBeTrue();
    });

    it('defines nats:work command (Phase 4.1)', function (): void {
        $refl = new \ReflectionClass(NatsWorkCommand::class);
        $defaults = $refl->getDefaultProperties();
        expect(isset($defaults['signature']) && str_contains((string) $defaults['signature'], 'nats:work'))->toBeTrue();
    });

    it('registers nats:work command (Phase 4.1 — v1.1.1)', function (): void {
        $commands = Artisan::all();

        expect(array_key_exists('nats:work', $commands))->toBeTrue();
    });

    it('registers nats:consume command (Phase 4.2 — v1.1.1)', function (): void {
        $commands = Artisan::all();

        expect(array_key_exists('nats:consume', $commands))->toBeTrue();
    });

    it('defines nats:consume:stream command (Phase 4.3 — JetStream Consumer Worker)', function (): void {
        $refl = new \ReflectionClass(NatsConsumeStreamCommand::class);
        $defaults = $refl->getDefaultProperties();
        expect(isset($defaults['signature']) && str_contains((string) $defaults['signature'], 'nats:consume:stream'))->toBeTrue();
    });

    it('registers nats:consume:stream command (Phase 4.3)', function (): void {
        $commands = Artisan::all();

        expect(array_key_exists('nats:consume:stream', $commands))->toBeTrue();
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
