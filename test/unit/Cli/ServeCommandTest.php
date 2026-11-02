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
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfig;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfigLoader;
use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use WebwareTest\AsciidocPhp\MultiRepo\Support\CapturedOutput;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function copy;
use function dirname;

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

    private function config(): SiteConfig
    {
        return new SiteConfigLoader()->load(Path::join($this->site, 'site.json'));
    }

    private function serve(string $output, int $port = 0): ExitCode
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
            new Output(
                stdout: $this->captured->stdoutWriter(),
                stderr: $this->captured->stderrWriter(),
            ),
        );
    }
}
