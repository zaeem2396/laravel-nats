<?php

declare(strict_types=1);

/**
 * ============================================================================
 * COMMAND BUILDER UNIT TESTS
 * ============================================================================
 *
 * These tests verify the NATS command builder correctly formats all
 * client-side protocol commands. Proper formatting is essential for
 * the NATS server to understand our messages.
 *
 * Each command ends with CRLF (\r\n) as per the NATS protocol.
 * ============================================================================
 */

use LaravelNats\Core\Protocol\CommandBuilder;

beforeEach(function (): void {
    $this->builder = new CommandBuilder();
});

describe('CONNECT command', function (): void {
    it('builds basic CONNECT', function (): void {
        $command = $this->builder->connect([
            'verbose' => false,
            'pedantic' => false,
            'name' => 'test-client',
            'lang' => 'php',
            'version' => '0.1.0',
        ]);

        expect($command)->toStartWith('CONNECT ')
            ->and($command)->toEndWith("\r\n")
            ->and($command)->toContain('"name":"test-client"')
            ->and($command)->toContain('"lang":"php"');
    });

    it('includes auth credentials', function (): void {
        $command = $this->builder->connect([
            'user' => 'testuser',
            'pass' => 'testpass',
        ]);

        expect($command)->toContain('"user":"testuser"')
            ->and($command)->toContain('"pass":"testpass"');
    });

    it('includes auth token', function (): void {
        $command = $this->builder->connect([
            'auth_token' => 'secret-token',
        ]);

        expect($command)->toContain('"auth_token":"secret-token"');
    });
});

describe('PUB command', function (): void {
    it('builds PUB without reply-to', function (): void {
        $command = $this->builder->publish('orders.created', '{"id":123}');

        expect($command)->toBe("PUB orders.created 10\r\n{\"id\":123}\r\n");
    });

    it('builds PUB with reply-to', function (): void {
        $command = $this->builder->publish('api.users', '{}', '_INBOX.reply123');

        expect($command)->toBe("PUB api.users _INBOX.reply123 2\r\n{}\r\n");
    });

    it('handles empty payload', function (): void {
        $command = $this->builder->publish('events.ping', '');

        expect($command)->toBe("PUB events.ping 0\r\n\r\n");
    });

    it('handles large payload', function (): void {
        $largePayload = str_repeat('x', 10000);
        $command = $this->builder->publish('data.large', $largePayload);

        expect($command)->toContain('PUB data.large 10000')
            ->and(strlen($command))->toBe(10000 + strlen("PUB data.large 10000\r\n") + 2);
    });
});

describe('HPUB command', function (): void {
    it('builds HPUB with headers', function (): void {
        $command = $this->builder->publishWithHeaders(
            'orders.created',
            '{"id":123}',
            ['Content-Type' => 'application/json'],
        );

        expect($command)->toStartWith('HPUB orders.created ')
            ->and($command)->toContain('NATS/1.0')
            ->and($command)->toContain('Content-Type: application/json')
            ->and($command)->toContain('{"id":123}')
            ->and($command)->toEndWith("\r\n");
    });

    it('builds HPUB with multiple headers', function (): void {
        $command = $this->builder->publishWithHeaders(
            'events.test',
            'payload',
            [
                'X-Request-Id' => 'abc123',
                'X-Trace-Id' => 'xyz789',
            ],
        );

        expect($command)->toContain('X-Request-Id: abc123')
            ->and($command)->toContain('X-Trace-Id: xyz789');
    });

    it('builds HPUB with reply-to', function (): void {
        $command = $this->builder->publishWithHeaders(
            'api.request',
            '{}',
            ['X-Custom' => 'value'],
            '_INBOX.reply456',
        );

        expect($command)->toContain('HPUB api.request _INBOX.reply456');
    });
});

describe('SUB command', function (): void {
    it('builds SUB without queue', function (): void {
        $command = $this->builder->subscribe('orders.created', '1');

        expect($command)->toBe("SUB orders.created 1\r\n");
    });

    it('builds SUB with queue group', function (): void {
        $command = $this->builder->subscribe('orders.created', '2', 'workers');

        expect($command)->toBe("SUB orders.created workers 2\r\n");
    });

    it('builds SUB with wildcard subject', function (): void {
        $command = $this->builder->subscribe('orders.*', '3');

        expect($command)->toBe("SUB orders.* 3\r\n");
    });

    it('builds SUB with > wildcard', function (): void {
        $command = $this->builder->subscribe('events.>', '4');

        expect($command)->toBe("SUB events.> 4\r\n");
    });
});

describe('UNSUB command', function (): void {
    it('builds UNSUB without max messages', function (): void {
        $command = $this->builder->unsubscribe('5');

        expect($command)->toBe("UNSUB 5\r\n");
    });

    it('builds UNSUB with max messages', function (): void {
        $command = $this->builder->unsubscribe('6', 10);

        expect($command)->toBe("UNSUB 6 10\r\n");
    });
});

describe('PING/PONG commands', function (): void {
    it('builds PING', function (): void {
        $command = $this->builder->ping();

        expect($command)->toBe("PING\r\n");
    });

    it('builds PONG', function (): void {
        $command = $this->builder->pong();

        expect($command)->toBe("PONG\r\n");
    });
});
