<?php

declare(strict_types=1);

namespace WebwareTestIntegration\AsciidocPhp\MultiRepo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Build\BuildRunner;
use Webware\AsciidocPhp\MultiRepo\Build\IncrementalCache;
use Webware\AsciidocPhp\MultiRepo\Cli\Commands\BuildCommand;
use Webware\AsciidocPhp\MultiRepo\Cli\ExitCode;
use Webware\AsciidocPhp\MultiRepo\Cli\Options;
use Webware\AsciidocPhp\MultiRepo\Cli\Output;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfigLoader;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function array_keys;
use function basename;
use function copy;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function glob;
use function in_array;
use function is_array;
use function is_dir;
use function ksort;
use function mkdir;
use function scandir;
use function touch;

/**
 * T058: an incremental build reuses the conversion of every page whose inputs did not change.
 *
 * The fixture organisation is copied to a scratch directory so a single page can be edited without
 * touching the repository. Cache entry modification times are backdated between builds, which makes
 * "was this page rendered again" observable without depending on clock resolution.
 */
#[CoversClass(IncrementalCache::class)]
#[CoversClass(BuildRunner::class)]
final class IncrementalBuildTest extends TestCase
{
    use TempDirectoryTrait;

    private const int BACKDATED = 1_600_000_000;

    private string $workspace;

    #[Test]
    public function anUnchangedRebuildRendersNothing(): void
    {
        $this->build(full: true);

        $this->backdateCache();

        $this->build(full: false);

        self::assertNotSame([], $this->cacheTimestamps(), 'the build stored nothing in the cache');
        self::assertSame([], $this->rewritten(), 'an unchanged rebuild rendered pages again');
    }

    #[Test]
    public function aStylesheetChangeInvalidatesEveryPage(): void
    {
        // The published asset names carry a content hash and every page's cache key covers them, so
        // a stylesheet change reaches every project without the builder tracking it separately.
        $cache = new IncrementalCache();

        $base = ['slug' => 'versioned', 'content' => 'abc', 'assets' => ['site.1.css', 'theme.1.js']];

        $changed = ['assets' => ['site.2.css', 'theme.1.js']] + $base;

        self::assertNotSame($cache->key($base), $cache->key($changed));
        self::assertSame($cache->key($base), $cache->key($base));
    }

    #[Test]
    public function editingOnePageRendersOnlyThatProject(): void
    {
        $this->build(full: true);

        $this->backdateCache();

        $page = Path::join($this->workspace, 'org', 'versioned', 'docs', 'v1', 'guide.adoc');

        self::assertNotFalse(file_put_contents(
            $page,
            (string) file_get_contents($page) . "\nAn added paragraph.\n",
        ));

        $this->build(full: false);

        // A local source is identified by a content digest of the whole repository, so one edited
        // page re-renders that project's pages and nothing else. Five is every page `versioned`
        // publishes; the other five projects stay on their cache entries.
        self::assertCount(5, $this->rewritten(), 'editing one page re-rendered pages outside its project');
    }

    protected function setUp(): void
    {
        $this->createTempDirectory();
        $this->workspace = Path::join($this->tempDirectory, 'fixtures');

        $this->copyDirectory(
            Path::join(dirname(__DIR__, 2), 'test', 'fixtures'),
            $this->workspace,
            ['build', 'search'],
        );
    }

    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }

    private function backdateCache(): void
    {
        foreach (array_keys($this->cacheTimestamps()) as $entry) {
            self::assertTrue(touch(
                Path::join($this->tempDirectory, 'cache', 'pages', $entry),
                self::BACKDATED,
            ));
        }
    }

    private function build(bool $full): void
    {
        $exitCode = new BuildCommand()->execute(
            new Options(
                command : 'build',
                manifest: Path::join($this->workspace, 'manifest.json'),
                out     : Path::join($this->tempDirectory, 'site'),
                cache   : Path::join($this->tempDirectory, 'cache'),
                full    : $full,
            ),
            new SiteConfigLoader()->load(Path::join($this->workspace, 'site.json')),
            new Output(
                stdout: static function (string $line): void {},
                stderr: static function (string $line): void {},
            ),
        );

        self::assertSame(ExitCode::Degraded, $exitCode);
    }

    /**
     * @return array<string, int>
     */
    private function cacheTimestamps(): array
    {
        $entries = glob(Path::join($this->tempDirectory, 'cache', 'pages', '*.json'));

        if (! is_array($entries)) {
            return [];
        }

        $timestamps = [];

        foreach ($entries as $entry) {
            $timestamps[basename($entry)] = (int) filemtime($entry);
        }

        ksort($timestamps, SORT_STRING);

        return $timestamps;
    }

    /**
     * @param list<string> $skip
     */
    private function copyDirectory(string $source, string $target, array $skip = []): void
    {
        if (! is_dir($target)) {
            self::assertTrue(mkdir($target, 0o777, true));
        }

        $entries = scandir($source);

        self::assertIsArray($entries);

        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry || in_array($entry, $skip, true)) {
                continue;
            }

            $from = Path::join($source, $entry);
            $to   = Path::join($target, $entry);

            if (is_dir($from)) {
                $this->copyDirectory($from, $to);

                continue;
            }

            self::assertTrue(copy($from, $to));
        }
    }

    /**
     * The cache entries rewritten since the backdate.
     *
     * @return array<string, int>
     */
    private function rewritten(): array
    {
        $rewritten = [];

        foreach ($this->cacheTimestamps() as $entry => $timestamp) {
            if (self::BACKDATED === $timestamp) {
                continue;
            }

            $rewritten[$entry] = $timestamp;
        }

        return $rewritten;
    }
}
