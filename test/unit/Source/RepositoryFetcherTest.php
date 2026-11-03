<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Report\ReasonCode;
use Webware\AsciidocPhp\MultiRepo\Source\RepositoryFetcher;
use Webware\AsciidocPhp\MultiRepo\Source\SourceException;
use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use Webware\AsciidocPhp\MultiRepo\Support\ProcessRunner;
use WebwareTest\AsciidocPhp\MultiRepo\Support\SourceFixture;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function bin2hex;
use function dirname;
use function random_bytes;
use function trim;

/**
 * Fetching: a local path is used in place, a remote is cloned shallowly with retries, and the
 * resolved revision is recorded either way.
 */
#[CoversClass(RepositoryFetcher::class)]
#[CoversMethod(RepositoryFetcher::class, 'fetch')]
final class RepositoryFetcherTest extends TestCase
{
    use TempDirectoryTrait;

    private Filesystem $filesystem;

    private ProcessRunner $runner;

    #[Test]
    public function aLocalGitRepositoryReportsItsCommit(): void
    {
        $root   = $this->gitRepository(['docs/index.adoc' => "= Index\n"]);
        $commit = $this->head($root);

        $fetched = $this->fetcher()->fetch(
            SourceFixture::repository(path: $root),
            SourceFixture::config(),
            "{$this->tempDirectory}/cache",
        );

        static::assertSame($commit, $fetched->revision());
        static::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $fetched->revision());
    }

    #[Test]
    public function aLocalPathIsUsedInPlace(): void
    {
        $root = $this->repository([
            'README.md'          => '# Example',
            'docs/v1/index.adoc' => "= Index\n",
        ]);

        $fetched = $this->fetcher()->fetch(
            SourceFixture::repository(path: $root),
            SourceFixture::config(),
            "{$this->tempDirectory}/cache",
        );

        static::assertSame($root, $fetched->rootPath());
        static::assertSame(['README.md', 'docs/v1/index.adoc'], $fetched->files());
        static::assertTrue($fetched->hasDocumentation());
        static::assertSame(1, $fetched->attempts());
        static::assertSame('docs', $fetched->documentationDirectory());
    }

    #[Test]
    public function anUnreachableRemoteFailsWithTheFetchFailedReasonCode(): void
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

            self::fail('Expected a SourceException.');
        } catch (SourceException $exception) {
            $error = $exception->error();

            static::assertSame(ReasonCode::FetchFailed, $error->reasonCode());
            static::assertStringContainsString('versioned', $error->message());
            static::assertStringContainsString('1 attempt(s)', $error->message());
            static::assertNotNull($error->correctiveAction());
        }
    }

    #[Test]
    public function aPlainDirectoryReportsAContentDigest(): void
    {
        $root = $this->repository(['docs/index.adoc' => "= Index\n"]);

        $fetched = $this->fetcher()->fetch(
            SourceFixture::repository(path: $root),
            SourceFixture::config(),
            "{$this->tempDirectory}/cache",
        );

        static::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $fetched->revision());
    }

    #[Test]
    public function aRemoteIsClonedIntoTheCacheDirectory(): void
    {
        $origin = $this->gitRepository(['docs/v1/index.adoc' => "= Index\n"]);
        $bare   = $this->bareClone($origin);
        $cache  = Path::join($this->tempDirectory, 'cache');

        $fetched = $this->fetcher(remoteUrl: static fn(string $organization, string $repository): string => $bare)
            ->fetch(
                SourceFixture::repository(
                    name: 'versioned',
                    slug: 'versioned',
                ),
                SourceFixture::config(),
                $cache,
            );

        static::assertSame(Path::join($cache, 'versioned'), $fetched->rootPath());
        static::assertSame(['docs/v1/index.adoc'], $fetched->files());
        static::assertSame($this->head($origin), $fetched->revision());
        static::assertSame(1, $fetched->attempts());
        static::assertTrue($fetched->checkedOut());
    }

    #[Test]
    public function aRepositoryWithoutDocumentationIsStillFetched(): void
    {
        $root = $this->repository(['README.md' => '# Example']);

        $fetched = $this->fetcher()->fetch(
            SourceFixture::repository(path: $root),
            SourceFixture::config(),
            "{$this->tempDirectory}/cache",
        );

        static::assertFalse($fetched->hasDocumentation());
        static::assertSame(['README.md'], $fetched->files());
    }

    #[Test]
    public function aRetryCountBelowOneIsClampedToOne(): void
    {
        $missing = Path::join($this->tempDirectory, 'absent-repository');

        try {
            $this->fetcher(
                maxAttempts: 0,
                remoteUrl  : static fn(): string => $missing,
            )
                ->fetch(
                    SourceFixture::repository(name: 'versioned'),
                    SourceFixture::config(),
                    Path::join($this->tempDirectory, 'cache'),
                );

            self::fail('Expected a SourceException.');
        } catch (SourceException $exception) {
            static::assertStringContainsString('1 attempt(s)', $exception->error()->message());
        }
    }

    #[Test]
    public function aSecondFetchOfAnUnchangedRevisionDoesNotCheckOutAgain(): void
    {
        $origin = $this->gitRepository(['docs/index.adoc' => "= Index\n"]);
        $bare   = $this->bareClone($origin);
        $cache  = Path::join($this->tempDirectory, 'cache');

        $fetcher = $this->fetcher(remoteUrl: static fn(): string => $bare);
        $first   = $fetcher->fetch(SourceFixture::repository(), SourceFixture::config(), $cache);
        $second  = $fetcher->fetch(SourceFixture::repository(), SourceFixture::config(), $cache);

        static::assertTrue($first->checkedOut());
        static::assertFalse($second->checkedOut());
        static::assertSame($first->revision(), $second->revision());
    }

    #[Test]
    public function theConfiguredRetryCountIsUsed(): void
    {
        $missing = Path::join($this->tempDirectory, 'absent-repository');

        try {
            $this->fetcher(
                maxAttempts: 3,
                remoteUrl  : static fn(): string => $missing,
            )
                ->fetch(
                    SourceFixture::repository(name: 'versioned'),
                    SourceFixture::config(),
                    Path::join($this->tempDirectory, 'cache'),
                );

            self::fail('Expected a SourceException.');
        } catch (SourceException $exception) {
            static::assertStringContainsString('3 attempt(s)', $exception->error()->message());
        }
    }

    #[Test]
    public function theContentDigestChangesWithTheContent(): void
    {
        $root = $this->repository(['docs/index.adoc' => "= Index\n"]);

        $before = $this
            ->fetcher()
            ->fetch(
                SourceFixture::repository(path: $root),
                SourceFixture::config(),
                "{$this->tempDirectory}/cache",
            )
            ->revision();

        $this->filesystem->writeAtomic(Path::join($root, 'docs/index.adoc'), "= Changed\n");

        $after = $this
            ->fetcher()
            ->fetch(
                SourceFixture::repository(path: $root),
                SourceFixture::config(),
                "{$this->tempDirectory}/cache",
            )
            ->revision();

        static::assertNotSame($before, $after);
    }

    #[Test]
    public function theContentDigestIsStableAcrossFetches(): void
    {
        $root = $this->repository(['docs/index.adoc' => "= Index\n"]);

        $first = $this
            ->fetcher()
            ->fetch(
                SourceFixture::repository(path: $root),
                SourceFixture::config(),
                "{$this->tempDirectory}/cache",
            )
            ->revision();

        $second = $this
            ->fetcher()
            ->fetch(
                SourceFixture::repository(path: $root),
                SourceFixture::config(),
                "{$this->tempDirectory}/cache",
            )
            ->revision();

        static::assertSame($first, $second);
    }

    #[Test]
    public function theDefaultRefIsTheRepositoryDefaultBranch(): void
    {
        $origin = $this->gitRepository(['docs/index.adoc' => "= Index\n"]);
        $bare   = $this->bareClone($origin);

        $fetched = $this->fetcher(remoteUrl: static fn(): string => $bare)
            ->fetch(
                SourceFixture::repository(),
                SourceFixture::config(),
                Path::join($this->tempDirectory, 'cache'),
            );

        static::assertSame($this->head($origin), $fetched->revision());
    }

    #[Test]
    public function theDocumentationDirectoryComesFromTheConfiguration(): void
    {
        $root = $this->repository(['documentation/index.adoc' => "= Index\n"]);

        $fetched = $this->fetcher()->fetch(
            SourceFixture::repository(path: $root),
            SourceFixture::config(['source' => ['directory' => 'documentation']]),
            "{$this->tempDirectory}/cache",
        );

        static::assertSame('documentation', $fetched->documentationDirectory());
        static::assertTrue($fetched->hasDocumentation());
    }

    #[Test]
    public function theGitMetadataDirectoryIsNotPartOfTheFileList(): void
    {
        $root = $this->gitRepository(['docs/index.adoc' => "= Index\n"]);

        $fetched = $this->fetcher()->fetch(
            SourceFixture::repository(path: $root),
            SourceFixture::config(),
            "{$this->tempDirectory}/cache",
        );

        static::assertSame(['docs/index.adoc'], $fetched->files());
    }

    #[Test]
    public function theRefArgumentOverridesTheDefaultBranch(): void
    {
        $origin = $this->gitRepository(['docs/index.adoc' => "= Index\n"]);
        $tagged = $this->head($origin);

        $this->runner->mustRun(['git', 'tag', 'v9.9.9'], $origin, 60.0);
        $this->runner->mustRun(
            [
                'git',
                '-c',
                'user.name=Fixture',
                '-c',
                'user.email=fixture@example.test',
                'commit',
                '--quiet',
                '--allow-empty',
                '-m',
                'After the tag',
            ],
            $origin,
            60.0,
        );

        $bare = $this->bareClone($origin);

        $fetched = $this->fetcher(remoteUrl: static fn(): string => $bare)
            ->fetch(
                SourceFixture::repository(),
                SourceFixture::config(),
                Path::join($this->tempDirectory, 'cache'),
                'v9.9.9',
            );

        static::assertSame($tagged, $fetched->revision());
    }

    #[Test]
    public function theRemoteUrlIsBuiltFromTheOrganizationAndRepositoryName(): void
    {
        $origin = $this->gitRepository(['docs/index.adoc' => "= Index\n"]);
        $bare   = $this->bareClone($origin);
        $seen   = [];

        $fetcher = new RepositoryFetcher(
            runner   : $this->runner,
            remoteUrl: static function (string $organization, string $repository) use ($bare, &$seen): string {
                $seen = [$organization, $repository];

                return $bare;
            },
        );

        $fetcher->fetch(
            SourceFixture::repository(
                name: 'message-bus',
                slug: 'message-bus',
            ),
            SourceFixture::config(),
            Path::join($this->tempDirectory, 'cache'),
        );

        static::assertSame(['fixture-org', 'message-bus'], $seen);
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

    private function bareClone(string $origin): string
    {
        $bare = Path::join($this->tempDirectory, 'origin.git');

        $this->runner->mustRun(
            ['git', 'clone', '--bare', '--quiet', $origin, $bare],
            $this->tempDirectory,
            60.0,
        );

        return $bare;
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
        $result = $this->runner->mustRun(['git', 'rev-parse', 'HEAD'], $root, 60.0);

        return trim($result->stdout());
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
