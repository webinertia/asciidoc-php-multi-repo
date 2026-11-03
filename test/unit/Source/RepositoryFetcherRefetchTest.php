<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Source\RepositoryFetcher;
use Webware\AsciidocPhp\MultiRepo\Source\SourceException;
use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use Webware\AsciidocPhp\MultiRepo\Support\ProcessRunner;
use WebwareTest\AsciidocPhp\MultiRepo\Support\SourceFixture;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function array_shift;
use function bin2hex;
use function dirname;
use function random_bytes;
use function trim;

/**
 * Fetching the same repository twice, and fetching it when the remote cannot be read.
 *
 * A repeat fetch follows whatever remote URL the caller resolves now, checks out a revision that
 * moved, and reports the default retry count with the git error trimmed when it cannot fetch at all.
 */
#[CoversClass(RepositoryFetcher::class)]
#[CoversMethod(RepositoryFetcher::class, 'fetch')]
final class RepositoryFetcherRefetchTest extends TestCase
{
    use TempDirectoryTrait;

    private Filesystem $filesystem;

    private ProcessRunner $runner;

    #[Test]
    public function aDefaultFetcherReportsThreeAttempts(): void
    {
        $missing = Path::join($this->tempDirectory, 'absent-repository');

        $fetcher = new RepositoryFetcher(
            runner   : $this->runner,
            remoteUrl: static fn(): string => $missing,
        );

        try {
            $fetcher->fetch(
                SourceFixture::repository(name: 'versioned'),
                SourceFixture::config(),
                Path::join($this->tempDirectory, 'cache'),
            );

            static::fail('Expected a SourceException.');
        } catch (SourceException $exception) {
            static::assertStringContainsString('3 attempt(s)', $exception->error()->message());
        }
    }

    #[Test]
    public function aFetchFailureMessageCarriesNoTrailingWhitespace(): void
    {
        $missing = Path::join($this->tempDirectory, 'absent-repository');

        try {
            $this->fetcher(
                maxAttempts: 1,
                remoteUrl  : static fn(): string => $missing,
            )
                ->fetch(
                    SourceFixture::repository(name: 'versioned'),
                    SourceFixture::config(),
                    Path::join($this->tempDirectory, 'cache'),
                );

            static::fail('Expected a SourceException.');
        } catch (SourceException $exception) {
            $message = $exception->error()->message();

            static::assertStringContainsString('does not appear to be a git repository', $message);
            static::assertStringEndsNotWith("\n", $message);
        }
    }

    #[Test]
    public function aRefetchChecksOutAMovedRevision(): void
    {
        $origin  = $this->gitRepository(['docs/index.adoc' => "= Index\n"]);
        $bare    = $this->bareClone($origin, 'origin.git');
        $cache   = Path::join($this->tempDirectory, 'cache');
        $fetcher = $this->fetcher(remoteUrl: static fn(): string => $bare);

        $fetcher->fetch(SourceFixture::repository(), SourceFixture::config(), $cache);
        $this->commit($origin, ['docs/second.adoc' => "= Second\n"]);
        $this->push($origin, $bare);

        $second = $fetcher->fetch(SourceFixture::repository(), SourceFixture::config(), $cache);

        static::assertTrue($second->checkedOut());
        static::assertContains('docs/second.adoc', $second->files());
    }

    #[Test]
    public function aRefetchFollowsTheNewRemoteUrl(): void
    {
        $first = $this->bareClone(
            $this->gitRepository(['docs/index.adoc' => "= First\n"]),
            'first.git',
        );

        $second = $this->bareClone(
            $this->gitRepository(['docs/index.adoc' => "= Second\n"]),
            'second.git',
        );

        $cache = Path::join($this->tempDirectory, 'cache');
        $urls  = [$first, $second];

        $fetcher = $this->fetcher(remoteUrl: static function () use (&$urls): string {
            return (string) array_shift($urls);
        });

        $fetcher->fetch(SourceFixture::repository(), SourceFixture::config(), $cache);

        $fetched = $fetcher->fetch(SourceFixture::repository(), SourceFixture::config(), $cache);

        static::assertSame($this->head($second), $fetched->revision());
    }

    #[Test]
    public function theContentDigestCoversEveryFileInTheDirectory(): void
    {
        $root = $this->repository([
            'docs/alpha.adoc' => "= Alpha\n",
            'docs/bravo.adoc' => "= Bravo\n",
            'docs/zulu.adoc'  => "= Zulu\n",
        ]);

        $cache = Path::join($this->tempDirectory, 'cache');

        $before = $this
            ->fetcher()
            ->fetch(SourceFixture::repository(path: $root), SourceFixture::config(), $cache)
            ->revision();

        $this->filesystem->writeAtomic(Path::join($root, 'docs/alpha.adoc'), "= Bravo\n");
        $this->filesystem->writeAtomic(Path::join($root, 'docs/bravo.adoc'), "= Alpha\n");

        $after = $this
            ->fetcher()
            ->fetch(SourceFixture::repository(path: $root), SourceFixture::config(), $cache)
            ->revision();

        static::assertNotSame($before, $after);
    }

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->runner     = new ProcessRunner();
        $this->createTempDirectory();
    }

    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }

    private function bareClone(string $origin, string $name): string
    {
        $bare = Path::join($this->tempDirectory, $name);

        $this->runner->mustRun(
            ['git', 'clone', '--bare', '--quiet', $origin, $bare],
            $this->tempDirectory,
            60.0,
        );

        return $bare;
    }

    /**
     * @param array<string, string> $files
     */
    private function commit(string $root, array $files): string
    {
        foreach ($files as $relative => $contents) {
            $absolute = Path::join($root, $relative);

            $this->filesystem->ensureDirectory(dirname($absolute));
            $this->filesystem->writeAtomic($absolute, $contents);
        }

        $this->runner->mustRun(['git', 'add', '--all'], $root, 60.0);
        $this->runner->mustRun(
            [
                'git',
                '-c',
                'user.name=Fixture',
                '-c',
                'user.email=fixture@example.test',
                'commit',
                '--quiet',
                '-m',
                'Second commit',
            ],
            $root,
            60.0,
        );

        return $this->head($root);
    }

    /**
     * @param null|callable(string, string): string $remoteUrl
     */
    private function fetcher(int $maxAttempts = 3, ?callable $remoteUrl = null): RepositoryFetcher
    {
        return new RepositoryFetcher(
            runner     : $this->runner,
            maxAttempts: $maxAttempts,
            remoteUrl  : null === $remoteUrl ? null : $remoteUrl(...),
        );
    }

    private function gitRepository(array $files): string
    {
        $root = $this->repository($files);

        $this->runner->mustRun(['git', 'init', '--quiet', '--initial-branch=main'], $root, 60.0);
        $this->runner->mustRun(['git', 'add', '--all'], $root, 60.0);
        $this->runner->mustRun(
            [
                'git',
                '-c',
                'user.name=Fixture',
                '-c',
                'user.email=fixture@example.test',
                'commit',
                '--quiet',
                '-m',
                'Initial commit',
            ],
            $root,
            60.0,
        );

        return $root;
    }

    private function head(string $root): string
    {
        return trim(
            $this->runner
                ->mustRun(['git', 'rev-parse', 'HEAD'], $root, 60.0)
                ->stdout(),
        );
    }

    private function push(string $origin, string $bare): void
    {
        $this->runner->mustRun(['git', 'push', '--quiet', $bare, 'main'], $origin, 60.0);
    }

    /**
     * @param array<string, string> $files
     */
    private function repository(array $files): string
    {
        $root = Path::join($this->tempDirectory, 'repository-' . bin2hex(random_bytes(6)));

        $this->filesystem->ensureDirectory($root);

        foreach ($files as $relative => $contents) {
            $absolute = Path::join($root, $relative);

            $this->filesystem->ensureDirectory(dirname($absolute));
            $this->filesystem->writeAtomic($absolute, $contents);
        }

        return $root;
    }
}
