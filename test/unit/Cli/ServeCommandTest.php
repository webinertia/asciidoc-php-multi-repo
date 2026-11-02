<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Cli\Commands\ServeCommand;
use Webware\AsciidocPhp\MultiRepo\Cli\ExitCode;
use Webware\AsciidocPhp\MultiRepo\Cli\Options;
use Webware\AsciidocPhp\MultiRepo\Cli\Output;
use Webware\AsciidocPhp\MultiRepo\Cli\Serve\StaticServer;
use Webware\AsciidocPhp\MultiRepo\Cli\Serve\StaticSiteHandler;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfig;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfigLoader;
use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use WebwareTest\AsciidocPhp\MultiRepo\Support\CapturedOutput;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function copy;
use function dirname;
use function fclose;
use function fsockopen;
use function fwrite;
use function getenv;
use function is_resource;
use function putenv;
use function sprintf;
use function stream_get_contents;
use function stream_set_timeout;

#[CoversClass(ServeCommand::class)]
#[CoversMethod(ServeCommand::class, 'execute')]
#[CoversMethod(ServeCommand::class, 'name')]
#[CoversMethod(ServeCommand::class, 'summary')]
final class ServeCommandTest extends TestCase
{
    use TempDirectoryTrait;

    private CapturedOutput $captured;

    private Filesystem $filesystem;

    private string $root = '';

    private string $site = '';

    #[Test]
    public function aHostThatCannotBeBoundIsAConfigurationError(): void
    {
        if (getenv('ASCIIDOC_SERVE_HOST') !== false) {
            static::markTestSkipped('ASCIIDOC_SERVE_HOST overrides the bind address under test.');
        }

        $this->builtOutput();

        $status = new ServeCommand(
            host        : '192.0.2.1',
            requestLimit: 1,
        )->execute(
            new Options(
                command: 'serve',
                port   : 0,
            ),
            $this->config(),
            $this->cliOutput(),
        );

        static::assertSame(ExitCode::ConfigError, $status);
        static::assertStringContainsString('Unable to listen', $this->captured->stderr);
    }

    #[Test]
    public function aMissingOutputDirectoryIsAConfigurationError(): void
    {
        $status = $this->serve(Path::join($this->site, 'build/site'));

        static::assertSame(ExitCode::ConfigError, $status);
        static::assertStringContainsString('does not exist', $this->captured->stderr);
        static::assertStringContainsString('build', $this->captured->stderr);
    }

    #[Test]
    public function anUnreadableOutputPathIsAConfigurationError(): void
    {
        $output = Path::join($this->site, 'build/site');

        $this->filesystem->ensureDirectory(Path::join($this->site, 'build'));
        $this->filesystem->writeAtomic($output, 'a file where the output directory belongs');

        $status = $this->serve($output);

        static::assertSame(ExitCode::ConfigError, $status);
        static::assertStringContainsString('does not exist', $this->captured->stderr);
    }

    #[Test]
    public function aRequestIsAnsweredAndTheLoopReturnsAtTheLimit(): void
    {
        $this->builtOutput();

        $client = null;

        $command = new ServeCommand(
            requestLimit: 1,
            serverFactory: static function (StaticSiteHandler $handler, string $host, int $port) use (
                &$client,
            ): StaticServer {
                $server = new StaticServer($handler, $host, $port);
                $server->start();

                $socket = fsockopen('127.0.0.1', $server->port(), $errorCode, $errorMessage, timeout: 5.0);

                static::assertIsResource($socket, sprintf(
                    'cannot reach the server: %s (%d)',
                    $errorMessage,
                    $errorCode,
                ));

                fwrite($socket, data: "GET /index.html HTTP/1.1\r\nHost: localhost\r\n\r\n");

                stream_set_timeout($socket, seconds: 5);

                $client = $socket;

                return $server;
            },
        );

        $status = $command->execute(
            new Options(
                command: 'serve',
                port   : 0,
            ),
            $this->config(),
            $this->cliOutput(),
        );

        static::assertSame(ExitCode::Success, $status);
        static::assertStringContainsString('serving', $this->captured->stderr);

        static::assertIsResource($client);

        $response = stream_get_contents($client);

        fclose($client);

        static::assertIsString($response);
        static::assertStringStartsWith('HTTP/1.1 200 OK', $response);
        static::assertStringEndsWith('served index', $response);
    }

    #[Test]
    public function theEnvironmentOverridesTheBindAddress(): void
    {
        $this->builtOutput();

        $previous = getenv('ASCIIDOC_SERVE_HOST');
        $client   = null;
        $host     = null;
        $status   = null;

        putenv('ASCIIDOC_SERVE_HOST=127.0.0.2');

        try {
            $command = new ServeCommand(
                host         : '192.0.2.1',
                requestLimit : 1,
                serverFactory: static function (StaticSiteHandler $handler, string $bind, int $port) use (
                    &$client,
                    &$host,
                ): StaticServer {
                    $host = $bind;

                    $server = new StaticServer($handler, '127.0.0.1', $port);
                    $server->start();

                    $socket = fsockopen('127.0.0.1', $server->port(), $errorCode, $errorMessage, timeout: 5.0);

                    static::assertIsResource($socket, sprintf(
                        'cannot reach the server: %s (%d)',
                        $errorMessage,
                        $errorCode,
                    ));

                    fwrite($socket, data: "GET /index.html HTTP/1.1\r\nHost: localhost\r\n\r\n");

                    stream_set_timeout($socket, seconds: 5);

                    $client = $socket;

                    return $server;
                },
            );

            $status = $command->execute(
                new Options(
                    command: 'serve',
                    port   : 0,
                ),
                $this->config(),
                $this->cliOutput(),
            );
        } finally {
            false === $previous ? putenv('ASCIIDOC_SERVE_HOST') : putenv("ASCIIDOC_SERVE_HOST={$previous}");

            if (is_resource($client)) {
                fclose($client);
            }
        }

        static::assertSame(ExitCode::Success, $status);
        static::assertSame('127.0.0.2', $host, 'ASCIIDOC_SERVE_HOST must replace the configured host.');
    }

    #[Test]
    public function theNameAndSummaryDescribeTheCommand(): void
    {
        $command = new ServeCommand();

        static::assertSame('serve', $command->name());
        static::assertStringContainsString('Serve', $command->summary());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->captured   = new CapturedOutput();
        $this->filesystem = new Filesystem();
        $this->root       = $this->createTempDirectory();
        $this->site       = Path::join($this->root, 'site');

        $this->filesystem->ensureDirectory($this->site);

        copy(
            Path::join(
                dirname(
                    path  : __DIR__,
                    levels: 2,
                ),
                'fixtures',
                'site.json',
            ),
            Path::join($this->site, 'site.json'),
        );
    }

    protected function tearDown(): void
    {
        $this->removeTempDirectory();

        parent::tearDown();
    }

    private function builtOutput(): string
    {
        $output = Path::join($this->site, 'build', 'site');

        $this->filesystem->ensureDirectory($output);
        $this->filesystem->writeAtomic(Path::join($output, 'index.html'), 'served index');

        return $output;
    }

    private function cliOutput(): Output
    {
        return new Output(
            stdout: $this->captured->stdoutWriter(),
            stderr: $this->captured->stderrWriter(),
        );
    }

    private function config(): SiteConfig
    {
        return new SiteConfigLoader()->load(Path::join($this->site, 'site.json'));
    }

    private function serve(?string $output, int $port = 0): ExitCode
    {
        return new ServeCommand(
            host        : '127.0.0.1',
            requestLimit: 1,
        )->execute(
            new Options(
                command: 'serve',
                out    : $output,
                port   : $port,
            ),
            $this->config(),
            $this->cliOutput(),
        );
    }
}
