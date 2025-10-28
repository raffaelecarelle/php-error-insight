<?php

declare(strict_types=1);

namespace PhpErrorInsight\Tests;

use PHPUnit\Framework\TestCase;

use function is_resource;
use function sprintf;

use const PHP_BINARY;

final class E2EJsonContentTypeTest extends TestCase
{
    /** @var resource|null */
    private $serverProc;

    private int $port = 0;

    protected function tearDown(): void
    {
        if (is_resource($this->serverProc)) {
            @proc_terminate($this->serverProc);
            @proc_close($this->serverProc);
        }

        parent::tearDown();
    }

    public function testForcesJsonWhenHttpContentTypeIsJson(): void
    {
        $docRoot = realpath(__DIR__ . '/..'); // project root
        $this->assertNotFalse($docRoot, 'Docroot should resolve');

        $this->port = $this->startPhpServer($docRoot);
        $this->assertGreaterThan(0, $this->port, 'Server must start on some port');

        // Build HTTP context with JSON Content-Type header
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);

        $url = sprintf('http://127.0.0.1:%d/tests/fixtures/json_request.php', $this->port);

        $body = @file_get_contents($url, false, $ctx);
        $this->assertNotFalse($body, 'HTTP request to built-in server failed');

        // Collect response headers
        $headers = $http_response_header;
        $headersString = implode("\n", $headers);

        // Assert HTTP status code is 500
        $this->assertMatchesRegularExpression('/^HTTP\/[0-9.]+\s+500\b/m', $headersString, 'Expected HTTP 500 status');

        // Assert Content-Type header is JSON
        $this->assertMatchesRegularExpression('/^Content-Type:\s*application\/json;\s*charset=utf-8\b/im', $headersString, 'Expected JSON content type header');

        // Assert body is valid JSON and contains the original message
        $decoded = json_decode($body, true);
        $this->assertIsArray($decoded, 'Body must be JSON');
        $this->assertSame('Forced JSON via Content-Type', $decoded['original']['message'] ?? null);
        $this->assertArrayHasKey('trace', $decoded);
    }

    private function startPhpServer(string $docRoot, int $attempts = 10): int
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        for ($i = 0; $i < $attempts; ++$i) {
            $port = random_int(20000, 40000);
            $cmd = sprintf('%s -S 127.0.0.1:%d -t %s', escapeshellarg(PHP_BINARY), $port, escapeshellarg($docRoot));
            $proc = @proc_open($cmd, $descriptorSpec, $pipes, $docRoot);

            if (!is_resource($proc)) {
                continue; // try next port
            }

            // Give the server a moment to start
            usleep(250_000);

            // Probe server
            $ctx = stream_context_create(['http' => ['timeout' => 1]]);
            $probe = @file_get_contents(sprintf('http://127.0.0.1:%d/', $port), false, $ctx);
            // Even if 404, server is up if headers are set
            if (preg_grep('/^HTTP\//', $http_response_header)) {
                $this->serverProc = $proc;
                // Close pipes; keep process handle to terminate later
                foreach ($pipes as $p) {
                    if (is_resource($p)) {
                        @fclose($p);
                    }
                }

                return $port;
            }

            // Could not verify, kill and retry
            @proc_terminate($proc);
            @proc_close($proc);
        }

        $this->fail('Could not start PHP built-in server');
    }
}
