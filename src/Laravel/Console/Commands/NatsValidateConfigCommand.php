<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Console\Commands;

use Illuminate\Console\Command;
use LaravelNats\Security\NatsBasisConfigurationValidator;

/**
 * Runs `nats_basis.security` validation (same checks as optional boot validation).
 */
final class NatsValidateConfigCommand extends Command
{
    protected $signature = 'nats:v2:config:validate';

    protected $description = 'Validate nats_basis configuration (security.validate_on_boot rules)';

    public function handle(NatsBasisConfigurationValidator $validator): int
    {
        try {
            $validator->validate($this->laravel->make('config'), $this->laravel, true);

            $this->info('nats_basis configuration is valid.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
