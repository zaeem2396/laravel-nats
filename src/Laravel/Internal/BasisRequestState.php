<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Internal;

use Basis\Nats\Message\Payload;
use Throwable;

/**
 * @internal
 */
final class BasisRequestState
{
    public bool $done = false;

    public ?Throwable $error = null;

    public ?Payload $payload = null;
}
