<?php

declare(strict_types=1);
use LaravelNats\JetStream\BasisJetStreamManager;
use LaravelNats\JetStream\BasisJetStreamPublisher;
use LaravelNats\JetStream\BasisStreamProvisioner;
use LaravelNats\JetStream\PullConsumerBatch;

it('marks JetStream service classes as final', function (): void {
    expect((new ReflectionClass(BasisJetStreamManager::class))->isFinal())->toBeTrue()
        ->and((new ReflectionClass(BasisJetStreamPublisher::class))->isFinal())->toBeTrue()
        ->and((new ReflectionClass(PullConsumerBatch::class))->isFinal())->toBeTrue()
        ->and((new ReflectionClass(BasisStreamProvisioner::class))->isFinal())->toBeTrue();
});
