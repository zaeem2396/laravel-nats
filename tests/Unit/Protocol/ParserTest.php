<?php

declare(strict_types=1);

/**
 * ============================================================================
 * PARSER UNIT TESTS
 * ============================================================================
 *
 * These tests verify the NATS protocol parser correctly handles all
 * protocol message types. The parser is critical for:
 *
 * - Parsing INFO messages from the server
 * - Parsing MSG/HMSG for message delivery
 * - Validating subjects
 * - Detecting message types
 *
 * All tests run without a NATS server (pure unit tests).
 * ============================================================================
 */

use LaravelNats\Core\Protocol\Parser;
use LaravelNats\Core\Protocol\ServerInfo;
use LaravelNats\Exceptions\ProtocolException;

// Create a fresh parser for each test
beforeEach(function (): void {
    $this->parser = new Parser();
});

describe('INFO parsing', function (): void {
    it('parses a valid INFO message', function (): void {
        $infoJson = json_encode([
            'server_id' => 'TEST123',
            'server_name' => 'test-nats',
            'version' => '2.10.0',
            'proto' => 1,
            'host' => '0.0.0.0',
            'port' => 4222,
            'max_payload' => 1048576,
            'headers' => true,
            'jetstream' => true,
        ]);

        $line = 'INFO ' . $infoJson;

        $info = $this->parser->parseInfo($line);

        expect($info)->toBeInstanceOf(ServerInfo::class)
            ->and($info->serverId)->toBe('TEST123')
            ->and($info->serverName)->toBe('test-nats')
            ->and($info->version)->toBe('2.10.0')
            ->and($info->proto)->toBe(1)
            ->and($info->maxPayload)->toBe(1048576)
            ->and($info->headersSupported)->toBeTrue()
            ->and($info->jetStreamEnabled)->toBeTrue();
    });

    it('handles minimal INFO message', function (): void {
        $line = 'INFO {}';

        $info = $this->parser->parseInfo($line);

        expect($info)->toBeInstanceOf(ServerInfo::class)
            ->and($info->serverId)->toBe('')
            ->and($info->proto)->toBe(1)
            ->and($info->maxPayload)->toBe(1048576); // default
    });

    it('throws on invalid INFO format', function (): void {
        $this->parser->parseInfo('INVALID');
    })->throws(ProtocolException::class);

    it('throws on invalid JSON in INFO', function (): void {
        $this->parser->parseInfo('INFO {invalid json}');
    })->throws(ProtocolException::class);
});

describe('MSG parsing', function (): void {
    it('parses MSG without reply-to', function (): void {
        $line = 'MSG orders.created 1 45';

        $result = $this->parser->parseMsg($line);

        expect($result)->toBe([
            'subject' => 'orders.created',
            'sid' => '1',
            'replyTo' => null,
            'size' => 45,
        ]);
    });

    it('parses MSG with reply-to', function (): void {
        $line = 'MSG api.users.get 5 _INBOX.abc123 128';

        $result = $this->parser->parseMsg($line);

        expect($result)->toBe([
            'subject' => 'api.users.get',
            'sid' => '5',
            'replyTo' => '_INBOX.abc123',
            'size' => 128,
        ]);
    });

    it('throws on invalid MSG format', function (): void {
        $this->parser->parseMsg('MSG invalid');
    })->throws(ProtocolException::class);
});

describe('HMSG parsing', function (): void {
    it('parses HMSG without reply-to', function (): void {
        $line = 'HMSG orders.created 1 22 67';

        $result = $this->parser->parseHmsg($line);

        expect($result)->toBe([
            'subject' => 'orders.created',
            'sid' => '1',
            'replyTo' => null,
            'headerSize' => 22,
            'totalSize' => 67,
        ]);
    });

    it('parses HMSG with reply-to', function (): void {
        $line = 'HMSG api.users.get 5 _INBOX.xyz789 30 100';

        $result = $this->parser->parseHmsg($line);

        expect($result)->toBe([
            'subject' => 'api.users.get',
            'sid' => '5',
            'replyTo' => '_INBOX.xyz789',
            'headerSize' => 30,
            'totalSize' => 100,
        ]);
    });
});

describe('header parsing', function (): void {
    it('parses standard headers', function (): void {
        $headerData = "NATS/1.0\r\nContent-Type: application/json\r\nX-Custom: value\r\n\r\n";

        $headers = $this->parser->parseHeaders($headerData);

        expect($headers)->toBe([
            'Content-Type' => 'application/json',
            'X-Custom' => 'value',
        ]);
    });

    it('handles empty headers', function (): void {
        $headerData = "NATS/1.0\r\n\r\n";

        $headers = $this->parser->parseHeaders($headerData);

        expect($headers)->toBe([]);
    });
});

describe('error parsing', function (): void {
    it('parses error with single quotes', function (): void {
        $line = "-ERR 'Unknown Protocol Operation'";

        $error = $this->parser->parseError($line);

        expect($error)->toBe('Unknown Protocol Operation');
    });

    it('parses error with double quotes', function (): void {
        $line = '-ERR "Authorization Violation"';

        $error = $this->parser->parseError($line);

        expect($error)->toBe('Authorization Violation');
    });

    it('parses error without quotes', function (): void {
        $line = '-ERR Permissions Violation';

        $error = $this->parser->parseError($line);

        expect($error)->toBe('Permissions Violation');
    });
});

describe('type detection', function (): void {
    it('detects INFO', function (): void {
        expect($this->parser->detectType('INFO {"server_id":"test"}'))->toBe('INFO');
    });

    it('detects MSG', function (): void {
        expect($this->parser->detectType('MSG foo 1 10'))->toBe('MSG');
    });

    it('detects HMSG', function (): void {
        expect($this->parser->detectType('HMSG foo 1 5 15'))->toBe('HMSG');
    });

    it('detects PING', function (): void {
        expect($this->parser->detectType('PING'))->toBe('PING');
    });

    it('detects PONG', function (): void {
        expect($this->parser->detectType('PONG'))->toBe('PONG');
    });

    it('detects +OK', function (): void {
        expect($this->parser->detectType('+OK'))->toBe('+OK');
    });

    it('detects -ERR', function (): void {
        expect($this->parser->detectType('-ERR some error'))->toBe('-ERR');
    });

    it('returns UNKNOWN for unrecognized', function (): void {
        expect($this->parser->detectType('SOMETHING_ELSE'))->toBe('UNKNOWN');
    });
});

describe('subject validation', function (): void {
    // Valid subjects
    it('accepts simple subject', function (): void {
        expect('orders')->toBeValidSubject();
    });

    it('accepts dotted subject', function (): void {
        expect('orders.created')->toBeValidSubject();
    });

    it('accepts multi-level subject', function (): void {
        expect('orders.us.east.created')->toBeValidSubject();
    });

    it('accepts single-token wildcard asterisk', function (): void {
        expect('orders.*')->toBeValidSubject();
    });

    it('accepts multi-token wildcard greater-than', function (): void {
        expect('orders.>')->toBeValidSubject();
    });

    it('accepts * in middle', function (): void {
        expect('orders.*.created')->toBeValidSubject();
    });

    // Invalid subjects
    it('rejects empty subject', function (): void {
        expect('')->toBeInvalidSubject();
    });

    it('rejects subject with space', function (): void {
        expect('orders created')->toBeInvalidSubject();
    });

    it('rejects subject with tab', function (): void {
        expect("orders\tcreated")->toBeInvalidSubject();
    });

    it('rejects double dots', function (): void {
        expect('orders..created')->toBeInvalidSubject();
    });

    it('rejects > not at end', function (): void {
        expect('orders.>.created')->toBeInvalidSubject();
    });

    it('rejects partial wildcard', function (): void {
        expect('orders.create*')->toBeInvalidSubject();
    });

    // Without wildcards
    it('rejects asterisk when wildcards disabled', function (): void {
        $parser = new Parser();
        expect($parser->isValidSubject('orders.*', false))->toBeFalse();
    });

    it('rejects greater-than when wildcards disabled', function (): void {
        $parser = new Parser();
        expect($parser->isValidSubject('orders.>', false))->toBeFalse();
    });
});
