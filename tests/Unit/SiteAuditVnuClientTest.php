<?php

namespace Tests\Unit;

use App\Services\SiteAudit\SiteAuditHtmlChecker;
use App\Services\SiteAudit\SiteAuditHtmlParser;
use App\Services\SiteAudit\SiteAuditVnuClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class SiteAuditVnuClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'site_audit.vnu_url' => 'http://127.0.0.1:8877/',
            'site_audit.vnu_timeout' => 2,
            'site_audit.vnu_connect_timeout' => 1,
            'site_audit.vnu_sample_max' => 10,
            'site_audit.vnu_max_bytes' => 1_500_000,
        ]);
    }

    public function test_validate_parses_errors_only(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'messages' => [
                    ['type' => 'info', 'message' => 'noise'],
                    ['type' => 'error', 'lastLine' => 12, 'message' => '  Bad  tag  '],
                    ['type' => 'error', 'message' => 'Bad tag'],
                ],
            ])),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock), 'http_errors' => false]);
        $vnu = new SiteAuditVnuClient($client);

        $result = $vnu->validate('<html></html>');

        $this->assertTrue($result['ok']);
        $this->assertNull($result['error']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame(12, $result['errors'][0]['line']);
        $this->assertSame('error', $result['errors'][0]['level']);
        $this->assertSame('Bad tag', $result['errors'][0]['message']);
    }

    public function test_validate_many_runs_parallel_posts(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode([
                'messages' => [['type' => 'error', 'message' => 'A']],
            ])),
            new Response(200, [], json_encode([
                'messages' => [['type' => 'error', 'message' => 'B']],
            ])),
            new Response(200, [], json_encode(['messages' => []])),
        ]));
        $stack->push(Middleware::history($history));
        $client = new Client(['handler' => $stack, 'http_errors' => false]);
        $vnu = new SiteAuditVnuClient($client);

        $out = $vnu->validateMany([
            0 => '<p>a',
            2 => '<p>b',
            5 => '<p>c',
        ], 3);

        $this->assertCount(3, $history);
        $this->assertTrue($out[0]['ok']);
        $this->assertSame('A', $out[0]['errors'][0]['message']);
        $this->assertSame('B', $out[2]['errors'][0]['message']);
        $this->assertSame([], $out[5]['errors']);
    }

    public function test_parser_uses_vnu_precomputed_without_http(): void
    {
        $parser = new SiteAuditHtmlParser();
        $parsed = $parser->parse(
            '<!doctype html><html><head><title>T</title></head><body><p>ok</p></body></html>',
            'https://example.com/',
            [
                'html_checker' => SiteAuditHtmlChecker::HTML5,
                'vnu_precomputed' => [
                    'ok' => true,
                    'errors' => [
                        ['line' => 3, 'level' => 'error', 'message' => 'Precomputed err'],
                    ],
                    'error' => null,
                ],
            ]
        );

        $this->assertSame(SiteAuditHtmlChecker::HTML5, $parsed['html_checker']);
        $this->assertFalse($parsed['html_checker_fallback']);
        $this->assertGreaterThanOrEqual(1, $parsed['html_error_count']);
        $found = false;
        foreach ($parsed['html_error_samples'] as $row) {
            if (($row['message'] ?? '') === 'Precomputed err') {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }
}
