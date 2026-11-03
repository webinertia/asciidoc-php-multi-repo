<?php

declare(strict_types=1);

namespace WebwareTestIntegration\AsciidocPhp\MultiRepo\Container;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use Webware\AsciidocPhp\MultiRepo\Support\ProcessResult;
use Webware\AsciidocPhp\MultiRepo\Support\ProcessRunner;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function copy;
use function dirname;
use function getenv;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function json_encode;
use function scandir;
use function sprintf;
use function str_ends_with;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

/**
 * Exercises the container stack end to end: `docker compose run --rm render`.
 *
 * The test is skipped unless `ASCIIDOC_CONTAINER_SMOKE=1` is exported, because it needs a working
 * Docker daemon and a first run builds the runtime image. It is skipped again when the flag is set
 * but the daemon is unreachable, so a machine without Docker still has a green suite.
 *
 * It asserts the two things T065 promises: the rendered tree lands on the host, and the container
 * exits with the build's exit code rather than swallowing it.
 *
 * @internal
 */
#[CoversNothing]
final class ComposeSmokeTest extends TestCase
{
    use TempDirectoryTrait;

    /** Set to a non-empty value to run this test. */
    private const string FLAG = 'ASCIIDOC_CONTAINER_SMOKE';

    /** A first run builds the image, which takes longer than a render. */
    private const float TIMEOUT_SECONDS = 900.0;

    private string $repositoryRoot = '';

    private ProcessRunner $processes;

    #[Test]
    public function renderPropagatesAFailingBuildExitCode(): void
    {
        $site = $this->siteWith(['broken-include']);

        $result = $this->render($site);

        static::assertSame(
            1,
            $result->exitCode(),
            "A degraded build must reach the caller as exit code 1, not 0. {$result->stderr()}",
        );
        static::assertStringContainsString('render-failed', $result->stderr());
    }

    #[Test]
    public function renderWritesTheOutputTreeAndExitsZero(): void
    {
        $site = $this->siteWith(['versioned']);

        $result = $this->render($site);

        static::assertSame(0, $result->exitCode(), $result->stderr());

        static::assertFileExists($this->outputPath($site, 'versioned/v1/index.html'));
        static::assertFileExists($this->outputPath($site, 'versioned/v2/index.html'));
        static::assertFileExists($this->outputPath($site, 'versioned/v1/guide.html'));
        static::assertNotSame([], $this->stylesheets($site), 'The theme assets were not written.');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $flag = getenv(self::FLAG);

        if (! is_string($flag) || '' === $flag) {
            self::markTestSkipped(sprintf('Set %s=1 to run the container smoke test.', self::FLAG));
        }

        $this->repositoryRoot = dirname(
            path  : __DIR__,
            levels: 3,
        );
        $this->processes = new ProcessRunner($this->repositoryRoot);

        if (! $this->dockerIsAvailable()) {
            self::markTestSkipped('The Docker daemon is not reachable.');
        }
    }

    protected function tearDown(): void
    {
        $this->removeTempDirectory();

        parent::tearDown();
    }

    /**
     * @throws RuntimeException when the copy fails
     */
    private function copyFile(string $from, string $to): void
    {
        if (! copy($from, $to)) {
            throw new RuntimeException(sprintf('Unable to copy "%s" to "%s".', $from, $to));
        }
    }

    /**
     * @throws RuntimeException when the copy fails
     */
    private function copyTree(string $from, string $to): void
    {
        if (! is_dir($to)) {
            new Filesystem()->ensureDirectory($to);
        }

        $entries = scandir($from);

        if (false === $entries) {
            throw new RuntimeException(sprintf('Unable to read "%s".', $from));
        }

        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $source      = Path::join($from, $entry);
            $destination = Path::join($to, $entry);

            if (is_dir($source)) {
                $this->copyTree($source, $destination);

                continue;
            }

            if (is_file($source)) {
                $this->copyFile($source, $destination);
            }
        }
    }

    private function dockerIsAvailable(): bool
    {
        try {
            $version = $this->processes->run(['docker', 'version', '--format', '{{.Server.Version}}'], null, 30.0);
            $compose = $this->processes->run(['docker', 'compose', 'version'], null, 30.0);
        } catch (Throwable) {
            return false;
        }

        return $version->successful() && $compose->successful();
    }

    /**
     * @param list<array<string, mixed>> $entries
     *
     * @throws RuntimeException when the manifest cannot be encoded
     */
    private function manifest(array $entries): string
    {
        $document = ['schemaVersion' => 1, 'repositories' => $entries];

        $encoded = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (! is_string($encoded)) {
            throw new RuntimeException('Unable to encode the smoke-test manifest.');
        }

        return "{$encoded}\n";
    }

    private function outputPath(string $site, string $relative): string
    {
        return Path::join($site, 'build', 'site', $relative);
    }

    /**
     * @throws RuntimeException when the container cannot be started
     */
    private function render(string $site): ProcessResult
    {
        $inherited = getenv();

        /** @var array<string, string> $environment */
        $environment = is_array($inherited) ? $inherited : [];

        $result = $this->processes->run(
            [
                'docker',
                'compose',
                '-f',
                Path::join($this->repositoryRoot, 'compose.yml'),
                'run',
                '--rm',
                'render',
            ],
            null,
            self::TIMEOUT_SECONDS,
            [
                ...$environment,
                'SITE_DIR'      => $site,
                'OUTPUT_DIR'    => Path::join($site, 'build', 'site'),
                'OUTPUT_PATH'   => '/site/build/site',
                'SITE_MANIFEST' => 'manifest.json',
            ],
        );

        if ($result->timedOut()) {
            self::fail("{$result->describeFailure()}\n{$result->stderr()}");
        }

        return $result;
    }

    /**
     * Builds a scratch site containing the named fixture repositories and a manifest that names
     * only those, so a fixture that is meant to fail cannot leak into the passing case.
     *
     * @param list<string> $repositories
     *
     * @throws RuntimeException when a fixture cannot be copied
     */
    private function siteWith(array $repositories): string
    {
        $root = $this->createTempDirectory();
        $site = Path::join($root, 'site');

        new Filesystem()->ensureDirectory($site);

        $fixtures = Path::join($this->repositoryRoot, 'test', 'fixtures');

        $this->copyFile(Path::join($fixtures, 'site.json'), Path::join($site, 'site.json'));

        $entries = [];

        foreach ($repositories as $repository) {
            $this->copyTree(
                Path::join($fixtures, 'org', $repository),
                Path::join($site, 'org', $repository),
            );

            $entries[] = [
                'name'          => $repository,
                'slug'          => $repository,
                'defaultBranch' => 'main',
                'archived'      => false,
                'path'          => "org/{$repository}",
            ];
        }

        new Filesystem()->writeAtomic(Path::join($site, 'manifest.json'), $this->manifest($entries));

        return $site;
    }

    /**
     * @return list<string>
     */
    private function stylesheets(string $site): array
    {
        $directory = $this->outputPath($site, 'assets');

        if (! is_dir($directory)) {
            return [];
        }

        $entries = scandir($directory);

        if (false === $entries) {
            return [];
        }

        $stylesheets = [];

        foreach ($entries as $entry) {
            if (! str_ends_with($entry, '.css')) {
                continue;
            }

            $stylesheets[] = $entry;
        }

        return $stylesheets;
    }
}
