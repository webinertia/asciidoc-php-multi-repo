<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhpMultiRepo\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhpMultiRepo\Report\ReasonCode;
use Webware\AsciidocPhpMultiRepo\Source\RepositoryFetcher;
use Webware\AsciidocPhpMultiRepo\Source\SourceException;
use Webware\AsciidocPhpMultiRepo\Support\Filesystem;
use Webware\AsciidocPhpMultiRepo\Support\Path;
use Webware\AsciidocPhpMultiRepo\Support\ProcessRunner;
use WebwareTest\AsciidocPhpMultiRepo\Support\SourceFixture;
use WebwareTest\AsciidocPhpMultiRepo\Support\TempDirectoryTrait;

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

    public function testALocalGitRepositoryReportsItsCommit(): void
    {
        $root   = $this->gitRepository(['docs/index.adoc' => "= Index\n"]);
        $commit = $this->head($root);

        $fetched = $this->fetcher()->fetch(
            SourceFixture::repository(path: $root),
            SourceFixture::config(),
            "{$this->tempDirectory}/cache",
        );

        self::assertSame($commit, $fetched->revision());
        self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $fetched->revision());
    }

    public function testALocalPathIsUsedInPlace(): void
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

        self::assertSame($root, $fetched->rootPath());
        self::assertSame(['README.md', 'docs/v1/index.adoc'], $fetched->files());
        self::assertTrue($fetched->hasDocumentation());
        self::assertSame(1, $fetched->attempts());
        self::assertSame('docs', $fetched->documentationDirectory());
    }

    public function testAnUnreachableRemoteFailsWithTheFetchFailedReasonCode(): void
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

            self::assertSame(ReasonCode::FetchFailed, $error->reasonCode());
            self::assertStringContainsString('versioned', $error->message());
            self::assertStringContainsString('1 attempt(s)', $error->message());
            self::assertNotNull($error->correctiveAction());
        }
    }

    public function testAPlainDirectoryReportsAContentDigest(): void
    {
        $root = $this->repository(['docs/index.adoc' => "= Index\n"]);

        $fetched = $this->fetcher()->fetch(
            SourceFixture::repository(path: $root),
            SourceFixture::config(),
            "{$this->tempDirectory}/cache",
        );

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $fetched->revision());
    }

    public function testARemoteIsClonedIntoTheCacheDirectory(): void
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

        self::assertSame(Path::join($cache, 'versioned'), $fetched->rootPath());
        self::assertSame(['docs/v1/index.adoc'], $fetched->files());
        self::assertSame($this->head($origin), $fetched->revision());
        self::assertSame(1, $fetched->attempts());
        self::assertTrue($fetched->checkedOut());
    }

    public function testARepositoryWithoutDocumentationIsStillFetched(): void
    {
        $root = $this->repository(['README.md' => '# Example']);

        $fetched = $this->fetcher()->fetch(
            SourceFixture::repository(path: $root),
            SourceFixture::config(),
            "{$this->tempDirectory}/cache",
        );

        self::assertFalse($fetched->hasDocumentation());
        self::assertSame(['README.md'], $fetched->files());
    }

    public function testARetryCountBelowOneIsClampedToOne(): void
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
            self::assertStringContainsString('1 attempt(s)', $exception->error()->message());
        }
    }

    public function testASecondFetchOfAnUnchangedRevisionDoesNotCheckOutAgain(): void
    {
        $origin = $this->gitRepository(['docs/index.adoc' => "= Index\n"]);
        $bare   = $this->bareClone($origin);
        $cache  = Path::join($this->tempDirectory, 'cache');

        $fetcher = $this->fetcher(remoteUrl: static fn(): string => $bare);
        $first   = $fetcher->fetch(SourceFixture::repository(), SourceFixture::config(), $cache);
        $second  = $fetcher->fetch(SourceFixture::repository(), SourceFixture::config(), $cache);

        self::assertTrue($first->checkedOut());
        self::assertFalse($second->checkedOut());
        self::assertSame($first->revision(), $second->revision());
    }

    public function testTheConfiguredRetryCountIsUsed(): void
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
            self::assertStringContainsString('3 attempt(s)', $exception->error()->message());
        }
    }

    public function testTheContentDigestChangesWithTheContent(): void
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

        self::assertNotSame($before, $after);
    }

    public function testTheContentDigestIsStableAcrossFetches(): void
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

        self::assertSame($first, $second);
    }

    public function testTheDefaultRefIsTheRepositoryDefaultBranch(): void
    {
        $origin = $this->gitRepository(['docs/index.adoc' => "= Index\n"]);
        $bare   = $this->bareClone($origin);

        $fetched = $this->fetcher(remoteUrl: static fn(): string => $bare)
            ->fetch(
                SourceFixture::repository(),
                SourceFixture::config(),
                Path::join($this->tempDirectory, 'cache'),
            );

        self::assertSame($this->head($origin), $fetched->revision());
    }

    public function testTheDocumentationDirectoryComesFromTheConfiguration(): void
    {
        $root = $this->repository(['documentation/index.adoc' => "= Index\n"]);

        $fetched = $this->fetcher()->fetch(
            SourceFixture::repository(path: $root),
            SourceFixture::config(['source' => ['directory' => 'documentation']]),
            "{$this->tempDirectory}/cache",
        );

        self::assertSame('documentation', $fetched->documentationDirectory());
        self::assertTrue($fetched->hasDocumentation());
    }

    public function testTheGitMetadataDirectoryIsNotPartOfTheFileList(): void
    {
        $root = $this->gitRepository(['docs/index.adoc' => "= Index\n"]);

        $fetched = $this->fetcher()->fetch(
            SourceFixture::repository(path: $root),
            SourceFixture::config(),
            "{$this->tempDirectory}/cache",
        );

        self::assertSame(['docs/index.adoc'], $fetched->files());
    }

    public function testTheRefArgumentOverridesTheDefaultBranch(): void
    {
        $origin = $this->gitRepository(['docs/index.adoc' => "= Index\n"]);
        $this->runner->mustRun(['git', 'tag', 'v9.9.9'], $origin, 60.0);
        $bare = $this->bareClone($origin);

        $fetched = $this->fetcher(remoteUrl: static fn(): string => $bare)
            ->fetch(
                SourceFixture::repository(),
                SourceFixture::config(),
                Path::join($this->tempDirectory, 'cache'),
                'v9.9.9',
            );

        self::assertSame($this->head($origin), $fetched->revision());
    }

    public function testTheRemoteUrlIsBuiltFromTheOrganizationAndRepositoryName(): void
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

        self::assertSame(['fixture-org', 'message-bus'], $seen);
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
