<?php

declare(strict_types=1);

use Basis\Nats\Client;
use Basis\Nats\Configuration as BasisConfiguration;
use Illuminate\Config\Repository;
use LaravelNats\Connection\ConnectionManager;
use LaravelNats\Exceptions\PublishException;
use LaravelNats\Observability\NullNatsMetrics;
use LaravelNats\Publisher\NatsPublisher;
use LaravelNats\Security\Exceptions\SubjectNotAllowedException;
use LaravelNats\Security\SubjectAclChecker;

function makePublisherConfig(bool $aclEnabled = true): Repository
{
    return new Repository([
        'nats_basis' => [
            'default' => 'default',
            'connections' => [
                'default' => [
                    'host' => '127.0.0.1',
                    'port' => 4222,
                ],
            ],
            'envelope_version' => 'v1',
            'observability' => ['metrics_enabled' => false],
            'acl' => [
                'enabled' => $aclEnabled,
                'allowed_publish_prefixes' => ['orders.'],
            ],
        ],
    ]);
}

function makePublisher(?ConnectionManager $connections = null, ?Repository $config = null): NatsPublisher
{
    $config ??= makePublisherConfig();

    return new NatsPublisher(
        $connections ?? new ConnectionManager($config),
        $config,
        new NullNatsMetrics,
        new SubjectAclChecker($config),
    );
}

function publisherWithDisconnectedClient(): NatsPublisher
{
    $config = makePublisherConfig(false);
    $manager = new ConnectionManager($config);
    $basis = new Client(new BasisConfiguration(host: '127.0.0.1', port: 4222));
    $ref = new ReflectionProperty(ConnectionManager::class, 'clients');
    $ref->setAccessible(true);
    $ref->setValue($manager, ['default' => $basis]);

    return makePublisher($manager, $config);
}

describe('NatsPublisher', function (): void {
    it('rejects publish when ACL denies the subject', function (): void {
        $publisher = makePublisher();

        expect(fn () => $publisher->publish('secret.topic', ['id' => 1]))
            ->toThrow(SubjectNotAllowedException::class);
    });

    it('wraps disconnected client errors as PublishException', function (): void {
        $publisher = publisherWithDisconnectedClient();

        expect(fn () => $publisher->publish('orders.created', ['id' => 1]))
            ->toThrow(PublishException::class);
    });
});
