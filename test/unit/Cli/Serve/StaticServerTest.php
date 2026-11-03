<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Cli\Serve;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Cli\Serve\StaticServer;
use Webware\AsciidocPhp\MultiRepo\Cli\Serve\StaticSiteHandler;
use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function fclose;
use function fsockopen;
use function fwrite;
use function sprintf;
use function stream_get_contents;

#[CoversClass(StaticServer::class)]
#[CoversMethod(StaticServer::class, 'isStarted')]
#[CoversMethod(StaticServer::class, 'port')]
#[CoversMethod(StaticServer::class, 'serveOnce')]
#[CoversMethod(StaticServer::class, 'start')]
#[CoversMethod(StaticServer::class, 'stop')]
#[CoversMethod(StaticServer::class, 'url')]
final class StaticServerTest extends TestCase
{
    use TempDirectoryTrait;

    private string $root = '';

    #[Test]
    public function aConnectionClosedWithoutARequestIsAnsweredWith400(): void
    {
        $server = $this->server();
        $server->start();

        try {
            $socket = fsockopen('127.0.0.1', $server->port(), $errorCode, $errorMessage, timeout: 5.0);

            static::assertIsResource($socket, sprintf('cannot connect: %s (%d)', $errorMessage, $errorCode));

            fclose($socket);

            static::assertTrue($server->serveOnce(2.0));
        } finally {
            $server->stop();
        }
    }

    #[Test]
    public function aMalformedRequestLineIsAnsweredWith400(): void
    {
        $server = $this->server();
        $server->start();

        try {
            $response = $this->request($server, "nonsense\r\n\r\n");
        } finally {
            $server->stop();
        }

        static::assertStringStartsWith('HTTP/1.1 400 Bad Request', $response);
    }

    #[Test]
    public function anUnknownPathIsAnsweredWith404(): void
    {
        $server = $this->server();
        $server->start();

        try {
            $response = $this->request($server, "GET /missing HTTP/1.1\r\nHost: localhost\r\n\r\n");
        } finally {
            $server->stop();
        }

        static::assertStringStartsWith('HTTP/1.1 404 Not Found', $response);
    }

    #[Test]
    public function getIsAnsweredWithTheResolvedDocument(): void
    {
        $server = $this->server();
        $server->start();

        try {
            $response = $this->request($server, "GET / HTTP/1.1\r\nHost: localhost\r\n\r\n");
        } finally {
            $server->stop();
        }

        static::assertStringStartsWith('HTTP/1.1 200 OK', $response);
        static::assertStringContainsString('Content-Type: text/html; charset=utf-8', $response);
        static::assertStringEndsWith('served index', $response);
    }

    #[Test]
    public function headIsAnsweredWithoutABody(): void
    {
        $server = $this->server();
        $server->start();

        try {
            $response = $this->request($server, "HEAD / HTTP/1.1\r\nHost: localhost\r\n\r\n");
        } finally {
            $server->stop();
        }

        static::assertStringStartsWith('HTTP/1.1 200 OK', $response);
        static::assertStringContainsString('Content-Length: 12', $response);
        static::assertStringEndsWith("\r\n\r\n", $response);
    }

    #[Test]
    public function serveOnceReportsFalseBeforeTheServerIsStarted(): void
    {
        static::assertFalse($this->server()->serveOnce(0.05));
    }

    #[Test]
    public function serveOnceReportsFalseWhenNothingConnects(): void
    {
        $server = $this->server();

        $server->start();

        try {
            static::assertFalse($server->serveOnce(0.05));
        } finally {
            $server->stop();
        }
    }

    #[Test]
    public function startingBindsAnEphemeralPort(): void
    {
        $server = $this->server();

        static::assertFalse($server->isStarted());
        static::assertSame(0, $server->port());

        $server->start();

        try {
            static::assertTrue($server->isStarted());
            static::assertGreaterThan(0, $server->port());
        } finally {
            $server->stop();
        }
    }

    #[Test]
    public function startingTwiceIsIdempotent(): void
    {
        $server = $this->server();

        $server->start();
        $port = $server->port();
        $server->start();

        try {
            static::assertSame($port, $server->port());
        } finally {
            $server->stop();
        }
    }

    #[Test]
    public function stoppingReleasesThePort(): void
    {
        $server = $this->server();

        $server->start();
        $server->stop();

        static::assertFalse($server->isStarted());
    }

    #[Test]
    public function theUrlKeepsAnExplicitHost(): void
    {
        $server = new StaticServer(new StaticSiteHandler($this->root, '/'), '127.0.0.1', 8080);
        $server->start();

        try {
            static::assertSame('http://127.0.0.1:8080/', $server->url());
        } finally {
            $server->stop();
        }
    }

    #[Test]
    public function theUrlSubstitutesLocalhostForAWildcardHost(): void
    {
        $server = new StaticServer(new StaticSiteHandler($this->root, '/'), '0.0.0.0', 8080);
        $server->start();

        try {
            static::assertSame('http://localhost:8080/', $server->url());
        } finally {
            $server->stop();
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $filesystem = new Filesystem();
        $this->root = $this->createTempDirectory();
        $index      = Path::join($this->root, 'index.html');

        $filesystem->ensureDirectory($this->root);
        $filesystem->writeAtomic($index, 'served index');
    }

    protected function tearDown(): void
    {
        $this->removeTempDirectory();

        parent::tearDown();
    }

    private function request(StaticServer $server, string $raw): string
    {
        $socket = fsockopen('127.0.0.1', $server->port(), $errno, $error, timeout: 5.0);

        static::assertIsResource($socket, sprintf(
            'cannot connect to port %d: %s (%d)',
            $server->port(),
            $error,
            $errno,
        ));

        fwrite($socket, $raw);
        static::assertTrue($server->serveOnce(2.0));

        $response = stream_get_contents($socket);
        fclose($socket);

        static::assertIsString($response);

        return $response;
    }

    private function server(): StaticServer
    {
        return new StaticServer(new StaticSiteHandler($this->root, '/'));
    }
}
