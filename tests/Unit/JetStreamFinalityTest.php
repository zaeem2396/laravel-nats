<?php

declare(strict_types=1);

it('marks JetStream service classes as final', function (): void {
    expect((new ReflectionClass(\LaravelNats\JetStream\BasisJetStreamManager::class))->isFinal())->toBeTrue()
        ->and((new ReflectionClass(\LaravelNats\JetStream\BasisJetStreamPublisher::class))->isFinal())->toBeTrue()
        ->and((new ReflectionClass(\LaravelNats\JetStream\PullConsumerBatch::class))->isFinal())->toBeTrue()
        ->and((new ReflectionClass(\LaravelNats\JetStream\BasisStreamProvisioner::class))->isFinal())->toBeTrue();
});
