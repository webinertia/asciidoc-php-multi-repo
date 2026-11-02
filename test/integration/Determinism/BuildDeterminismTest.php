<?php

declare(strict_types=1);

namespace WebwareTestIntegration\AsciidocPhp\MultiRepo\Determinism;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Build\BuildRunner;
use Webware\AsciidocPhp\MultiRepo\Build\DeterminismGuard;
use Webware\AsciidocPhp\MultiRepo\Build\IncrementalCache;
use Webware\AsciidocPhp\MultiRepo\Cli\Commands\BuildCommand;
use Webware\AsciidocPhp\MultiRepo\Cli\ExitCode;
use Webware\AsciidocPhp\MultiRepo\Cli\Options;
use Webware\AsciidocPhp\MultiRepo\Cli\Output;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfigLoader;
use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function array_diff;
use function array_keys;
use function basename;
use function dirname;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function glob;
use function implode;
use function is_array;
use function ksort;
use function sort;
use function sprintf;
use function str_contains;

/**
 * T061: two builds of the same sources produce the same bytes.
 *
 * A published site is compared, cached and served, so an unexplained diff between two builds is a
 * defect. The test builds the fixture organisation three times and compares recursive SHA-256
 * manifests: two consecutive full builds, and an incremental build against a fresh full build. It
 * also proves the guard is what keeps that true by planting a timestamp in a copy of the output and
 * requiring it to be reported.
 *
 * The incremental comparison is only meaningful if the second build actually reused the cache, so the
 * test also asserts no cache entry was rewritten, which is the same thing as rendering zero pages.
 */
#[CoversClass(BuildRunner::class)]
#[CoversClass(DeterminismGuard::class)]
#[CoversClass(IncrementalCache::class)]
final class BuildDeterminismTest extends TestCase
{
    use TempDirectoryTrait;

    /** @var list<string> */
    private array $stderr = [];

    public function testAnIncrementalBuildProducesTheSameBytesAsAFreshFullBuild(): void
    {
        $incremental = $this->siteDirectory('incremental');
        $full        = $this->siteDirectory('fresh');
        $cache       = Path::join($this->tempDirectory, 'cache-incremental');

        $this->build($incremental, $cache, full: true);

        $before = $this->cacheTimestamps($cache);

        self::assertNotSame([], $before, 'the first build stored nothing in the cache');

        $this->build($incremental, $cache, full: false);
        $this->build($full, Path::join($this->tempDirectory, 'cache-fresh'), full: true);

        self::assertNotSame([], $this->manifest($full), 'the full build published nothing');
        self::assertSame($before, $this->cacheTimestamps($cache), 'the incremental build re-rendered pages');
        self::assertSame([], $this->differences($incremental, $full));
    }

    public function testATimestampInTheOutputIsReported(): void
    {
        $output = $this->siteDirectory('stamped');

        $this->build($output, Path::join($this->tempDirectory, 'cache-stamped'), full: true);

        $page = Path::join($output, 'versioned', 'v1', 'index.html');

        // A template that gained a timestamp produces exactly this, which is why the guard scans the
        // rendered output rather than the template.
        $contents = (string) file_get_contents($page);

        self::assertNotFalse(
            file_put_contents($page, "{$contents}<footer>built 2024-05-06T07:08:09Z</footer>\n"),
        );

        $violations = new DeterminismGuard()->inspect($output);

        self::assertCount(1, $violations);
        self::assertStringContainsString('versioned/v1/index.html line ', $violations[0]);
        self::assertStringContainsString('a build timestamp', $violations[0]);
    }

    public function testTheCleanBuildReportsNoNonDeterminism(): void
    {
        $output = $this->siteDirectory('clean');

        $this->build($output, Path::join($this->tempDirectory, 'cache-clean'), full: true);

        self::assertSame([], new DeterminismGuard()->inspect($output));
        self::assertFalse(
            str_contains(implode("\n", $this->stderr), 'non-deterministic output'),
            implode("\n", $this->stderr),
        );
    }

    public function testTwoConsecutiveFullBuildsProduceTheSameBytes(): void
    {
        $first  = $this->siteDirectory('first');
        $second = $this->siteDirectory('second');

        $this->build($first, Path::join($this->tempDirectory, 'cache-first'), full: true);
        $this->build($second, Path::join($this->tempDirectory, 'cache-second'), full: true);

        self::assertSame([], $this->differences($first, $second));
    }

    protected function setUp(): void
    {
        $this->createTempDirectory();
        $this->stderr = [];
    }

    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }

    private function build(string $output, string $cache, bool $full): void
    {
        $exitCode = new BuildCommand()->execute(
            new Options(
                command : 'build',
                manifest: Path::join(dirname(__DIR__, 3), 'test', 'fixtures', 'manifest.json'),
                out     : $output,
                cache   : $cache,
                full    : $full,
            ),
            new SiteConfigLoader()->load(
                Path::join(dirname(__DIR__, 3), 'test', 'fixtures', 'site.json'),
            ),
            new Output(
                stdout: static function (string $line): void {},
                stderr: function (string $line): void {
                    $this->stderr[] = $line;
                },
            ),
        );

        self::assertSame(ExitCode::Degraded, $exitCode, 'the fixture organisation is expected to degrade');
    }

    /**
     * @return array<string, int>
     */
    private function cacheTimestamps(string $cacheDirectory): array
    {
        $entries = glob(Path::join($cacheDirectory, 'pages', '*.json'));

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
     * @return list<string>
     */
    private function differences(string $left, string $right): array
    {
        $a = $this->manifest($left);
        $b = $this->manifest($right);

        $onlyInA = array_diff(array_keys($a), array_keys($b));
        $onlyInB = array_diff(array_keys($b), array_keys($a));

        $changed = [];
        foreach ($a as $path => $hash) {
            if (! (isset($b[$path]) && $b[$path] !== $hash)) {
                continue;
            }

            $changed[] = $path;
        }

        sort($onlyInA);
        sort($onlyInB);
        sort($changed);

        $differences = [];

        foreach ($onlyInA as $path) {
            $differences[] = sprintf('%s only in the first build', $path);
        }

        foreach ($onlyInB as $path) {
            $differences[] = sprintf('%s only in the second build', $path);
        }

        foreach ($changed as $path) {
            $differences[] = sprintf('%s differs', $path);
        }

        return $differences;
    }

    /**
     * A recursive manifest: every published file keyed by its path, valued by its digest.
     *
     * @return array<string, string>
     */
    private function manifest(string $directory): array
    {
        $filesystem = new Filesystem();
        $manifest   = [];

        foreach ($filesystem->scan($directory) as $relative) {
            $path = Path::join($directory, ...explode('/', $relative));

            $manifest[$relative] = $filesystem->hash($path);
        }

        ksort($manifest, SORT_STRING);

        return $manifest;
    }

    private function siteDirectory(string $name): string
    {
        return Path::join($this->tempDirectory, "site-{$name}");
    }
}
