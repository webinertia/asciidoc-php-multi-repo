<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Cli\Commands\WatchCommand;
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
use function file_get_contents;
use function file_put_contents;
use function implode;
use function is_file;
use function sprintf;
use function str_replace;

/**
 * Drives the watch loop through its two seams, `initialize` and `poll`, against a real site tree.
 *
 * The rebuild is a real `BuildCommand` run over the fixture repositories, so a change that the poll
 * reports is also a change the output tree proves.
 */
#[CoversClass(WatchCommand::class)]
#[CoversMethod(WatchCommand::class, 'execute')]
#[CoversMethod(WatchCommand::class, 'initialize')]
#[CoversMethod(WatchCommand::class, 'name')]
#[CoversMethod(WatchCommand::class, 'poll')]
#[CoversMethod(WatchCommand::class, 'summary')]
final class WatchCommandTest extends TestCase
{
    use TempDirectoryTrait;

    private CapturedOutput $captured;

    private SiteConfig $config;

    private Filesystem $filesystem;

    private string $root = '';

    private string $site = '';

    #[Test]
    public function aDegradedRebuildDoesNotStopWatching(): void
    {
        $command = $this->command();
        $command->initialize($this->options(), $this->config, $this->cliOutput());

        $this->editPage('versioned', 'index.adoc', "include::missing-file.adoc[]\n");

        $first = $command->poll($this->options(), $this->config, $this->cliOutput());

        static::assertFalse($first->isEmpty());
        static::assertTrue($command->poll($this->options(), $this->config, $this->cliOutput())->isEmpty());
    }

    #[Test]
    public function aDeletedPageRebuildsItsProject(): void
    {
        $command = $this->command();
        $command->initialize($this->options(), $this->config, $this->cliOutput());

        $this->filesystem->remove(Path::join($this->site, 'org/versioned/docs/v1/guide.adoc'));

        $change = $command->poll($this->options(), $this->config, $this->cliOutput());

        static::assertSame(['versioned'], $change->projects());
    }

    #[Test]
    public function aPageAddedToAProjectRebuildsThatProject(): void
    {
        $command = $this->command();
        $command->initialize($this->options(), $this->config, $this->cliOutput());

        $this->filesystem->writeAtomic(
            Path::join($this->site, 'org/unversioned/docs/latest/added.adoc'),
            "= Added\n\nA new page.\n",
        );

        $change = $command->poll($this->options(), $this->config, $this->cliOutput());

        static::assertSame(['unversioned'], $change->projects());
    }

    #[Test]
    public function aProjectFilteredOutIsNotWatched(): void
    {
        $command = $this->command();
        $options = $this->options(projects: ['unversioned']);

        $command->initialize($options, $this->config, $this->cliOutput());
        $this->editPage('versioned', 'index.adoc', 'Edited versioned page.');

        static::assertTrue($command->poll($options, $this->config, $this->cliOutput())->isEmpty());
    }

    #[Test]
    public function editingOnePageRebuildsOnlyThatProject(): void
    {
        $command = $this->command();
        $command->initialize($this->options(), $this->config, $this->cliOutput());

        $this->editPage('versioned', 'index.adoc', 'Edited versioned page.');

        $change = $command->poll($this->options(), $this->config, $this->cliOutput());

        static::assertSame(['versioned'], $change->projects());
        static::assertFalse($change->everything());
        static::assertStringContainsString(
            'Edited versioned page.',
            $this->outputContents('versioned/v1/index.html'),
        );
    }

    #[Test]
    public function editingTheSiteConfigurationRebuildsEveryProject(): void
    {
        $command = $this->command();
        $command->initialize($this->options(), $this->config, $this->cliOutput());

        $configPath = Path::join($this->site, 'site.json');
        $contents   = file_get_contents($configPath);

        static::assertIsString($contents);
        file_put_contents($configPath, str_replace(
            search : 'Fixture Site',
            replace: 'Fixture Site Renamed',
            subject: $contents,
        ));

        $change = $command->poll($this->options(), $this->config, $this->cliOutput());

        static::assertTrue($change->everything());
    }

    #[Test]
    public function executeServesForTheCycleBudgetAndReturnsSuccess(): void
    {
        $command = $this->command(cycles: 1);

        static::assertSame(ExitCode::Success, $command->execute($this->options(), $this->config, $this->cliOutput()));
        static::assertStringContainsString('watching', $this->captured->stderr);
    }

    #[Test]
    public function initializeBuildsOnceAndKeepsWatching(): void
    {
        static::assertNull($this->command()->initialize($this->options(), $this->config, $this->cliOutput()));

        static::assertFileExists(Path::join($this->outputDirectory(), 'versioned/v1/index.html'));
        static::assertFileExists(Path::join($this->outputDirectory(), 'unversioned/latest/index.html'));
    }

    #[Test]
    public function pollingAnUnchangedTreeReportsNothing(): void
    {
        $command = $this->command();
        $command->initialize($this->options(), $this->config, $this->cliOutput());

        static::assertTrue($command->poll($this->options(), $this->config, $this->cliOutput())->isEmpty());
    }

    #[Test]
    public function pollingBeforeInitializeIsHarmless(): void
    {
        static::assertTrue($this->command()->poll($this->options(), $this->config, $this->cliOutput())->isEmpty());
    }

    #[Test]
    public function theInitialBuildFailureStopsTheLoop(): void
    {
        $this->filesystem->remove(Path::join($this->site, 'manifest.json'));

        static::assertSame(
            ExitCode::ConfigError,
            $this->command()->initialize($this->options(), $this->config, $this->cliOutput()),
        );
        static::assertStringContainsString('manifest.json', $this->captured->stderr);
    }

    #[Test]
    public function theNameAndSummaryDescribeTheCommand(): void
    {
        $command = $this->command();

        static::assertSame('watch', $command->name());
        static::assertStringContainsString('Rebuild', $command->summary());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->captured   = new CapturedOutput();
        $this->filesystem = new Filesystem();
        $this->root       = $this->createTempDirectory();
        $this->site       = Path::join($this->root, 'site');

        $this->filesystem->ensureDirectory($this->site);
        $this->repository('versioned');
        $this->repository('unversioned');
        $this->writeManifest(['versioned', 'unversioned']);

        $this->config = new SiteConfigLoader()->load(Path::join($this->site, 'site.json'));
    }

    protected function tearDown(): void
    {
        $this->removeTempDirectory();

        parent::tearDown();
    }

    private function cliOutput(): Output
    {
        return new Output(
            stdout: $this->captured->stdoutWriter(),
            stderr: $this->captured->stderrWriter(),
        );
    }

    private function command(int $cycles = 0): WatchCommand
    {
        return new WatchCommand(
            cycles  : $cycles,
            debounce: 0.0,
        );
    }

    private function copyTree(string $source, string $destination): void
    {
        $this->filesystem->ensureDirectory($destination);

        foreach ($this->filesystem->scan($source) as $relative) {
            $target = Path::join($destination, $relative);

            $this->filesystem->ensureDirectory(dirname($target));
            copy(Path::join($source, $relative), $target);
        }
    }

    private function editPage(string $repository, string $relative, string $body): void
    {
        $path = Path::join($this->site, 'org', $repository, 'docs/v1', $relative);

        if (! is_file($path)) {
            $path = Path::join($this->site, 'org', $repository, 'docs/latest', $relative);
        }

        $this->filesystem->writeAtomic($path, sprintf("= Edited\n\n%s\n", $body));
    }

    private function options(array $projects = []): Options
    {
        return new Options(
            command : 'watch',
            config  : Path::join($this->site, 'site.json'),
            out     : $this->outputDirectory(),
            projects: $projects,
            manifest: Path::join($this->site, 'manifest.json'),
            port    : 0,
        );
    }

    private function outputContents(string $relative): string
    {
        $contents = file_get_contents(Path::join($this->outputDirectory(), $relative));

        self::assertIsString($contents);

        return $contents;
    }

    private function outputDirectory(): string
    {
        return Path::join($this->site, 'build/site');
    }

    private function repository(string $name): void
    {
        $source = Path::join(
            dirname(
                path  : __DIR__,
                levels: 2,
            ),
            'fixtures',
            'org',
            $name,
        );
        $destination = Path::join($this->site, 'org', $name);

        $this->copyTree($source, $destination);
    }

    /**
     * @param list<string> $repositories
     */
    private function writeManifest(array $repositories): void
    {
        $entries = [];

        foreach ($repositories as $name) {
            $entries[] = sprintf(
                '        {"name": "%1$s", "slug": "%1$s", "defaultBranch": "main", "path": "%2$s"}',
                $name,
                str_replace(
                    search : '\\',
                    replace: '/',
                    subject: Path::join($this->site, 'org', $name),
                ),
            );
        }

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

        file_put_contents(
            Path::join($this->site, 'manifest.json'),
            sprintf("{\n    \"schemaVersion\": 1,\n    \"repositories\": [\n%s\n    ]\n}\n", implode(",\n", $entries)),
        );
    }
}
