<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Cli\Commands\CleanCommand;
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
use function is_dir;
use function is_file;

#[CoversClass(CleanCommand::class)]
#[CoversMethod(CleanCommand::class, 'execute')]
#[CoversMethod(CleanCommand::class, 'name')]
#[CoversMethod(CleanCommand::class, 'summary')]
final class CleanCommandTest extends TestCase
{
    use TempDirectoryTrait;

    private CapturedOutput $captured;

    private Filesystem $filesystem;

    private string $root = '';

    private string $site = '';

    #[Test]
    public function anAbsentTargetIsReportedAsAbsentRatherThanFailing(): void
    {
        $exit = $this->clean(out: Path::join($this->site, 'build', 'site'));

        static::assertSame(ExitCode::Success, $exit, $this->captured->stderr);
        static::assertStringContainsString('absent', $this->captured->stdout);
    }

    #[Test]
    public function anOutputOutsideTheConfigDirectoryIsRefusedWithExitTwo(): void
    {
        $outside = $this->outside();

        $exit = $this->clean(out: $outside);

        static::assertSame(ExitCode::ConfigError, $exit);
        static::assertTrue(is_dir($outside), 'A refused path must survive.');
        static::assertStringContainsString('outside the config directory', $this->captured->stderr);
        static::assertStringContainsString('--force', $this->captured->stderr);
        static::assertSame('', $this->captured->stdout);
    }

    #[Test]
    public function forceRemovesAPathOutsideTheConfigDirectory(): void
    {
        $outside = $this->outside();

        $exit = $this->clean(
            out  : $outside,
            force: true,
        );

        static::assertSame(ExitCode::Success, $exit, $this->captured->stderr);
        static::assertFalse(is_dir($outside));
    }

    #[Test]
    public function theCacheDirectoryIsRemoved(): void
    {
        $cache = $this->directory('build/cache/nested');

        $exit = $this->clean();

        static::assertSame(ExitCode::Success, $exit, $this->captured->stderr);
        static::assertFalse(is_dir($cache));
        static::assertFalse(is_dir(Path::join($this->site, 'build', 'cache')));
        static::assertStringContainsString('cache directory: removed', $this->captured->stdout);
    }

    #[Test]
    public function theCacheIsNotRemovedWhenAnOutputTargetIsRefused(): void
    {
        $cache   = $this->directory('build/cache');
        $outside = $this->outside();

        $this->clean(out: $outside);

        static::assertTrue(is_dir($cache), 'A refusal must be decided before anything is removed.');
    }

    #[Test]
    public function theCommandNamesItself(): void
    {
        $command = new CleanCommand();

        static::assertSame('clean', $command->name());
        static::assertStringContainsString('--out', $command->summary());
    }

    #[Test]
    public function theConfigDirectoryItselfIsRefusedEvenWithForce(): void
    {
        $exit = $this->clean(
            out  : $this->site,
            force: true,
        );

        static::assertSame(ExitCode::ConfigError, $exit);
        static::assertTrue(is_file(Path::join($this->site, 'site.json')));
        static::assertStringContainsString('config directory itself', $this->captured->stderr);
    }

    #[Test]
    public function theOutputDirectoryIsLeftAloneWithoutOut(): void
    {
        $output = $this->directory('build/site');

        $this->clean();

        static::assertTrue(is_dir($output), 'clean must not remove the output directory without --out.');
    }

    #[Test]
    public function theOutputDirectoryIsRemovedWhenOutIsGiven(): void
    {
        $output = $this->directory('build/site');
        $this->filesystem->writeAtomic(Path::join($output, 'index.html'), '<html></html>');

        $exit = $this->clean(out: $output);

        static::assertSame(ExitCode::Success, $exit, $this->captured->stderr);
        static::assertFalse(is_dir($output));
        static::assertStringContainsString('output directory: removed', $this->captured->stdout);
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

    private function clean(?string $out = null, bool $force = false): ExitCode
    {
        return new CleanCommand($this->filesystem)->execute(
            new Options(
                command: 'clean',
                out    : $out,
                force  : $force,
            ),
            $this->config(),
            new Output(
                stdout: $this->captured->stdoutWriter(),
                stderr: $this->captured->stderrWriter(),
            ),
        );
    }

    private function config(): SiteConfig
    {
        return new SiteConfigLoader()->load(Path::join($this->site, 'site.json'));
    }

    private function directory(string $relative): string
    {
        $path = Path::join($this->site, $relative);

        $this->filesystem->ensureDirectory($path);

        return $path;
    }

    /**
     * A directory that exists but is not under the config directory, so the refusal path is the
     * only thing that can stop its removal.
     */
    private function outside(): string
    {
        $path = Path::join($this->root, 'elsewhere');

        $this->filesystem->ensureDirectory($path);

        return $path;
    }
}
