<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Fixtures;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Config\ManifestLoader;
use Webware\AsciidocPhp\MultiRepo\Config\SourceSection;
use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Path;

use function array_unique;
use function array_values;
use function basename;
use function dirname;
use function explode;
use function is_dir;
use function scandir;
use function sort;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strpos;
use function substr;
use function trim;

/**
 * Guards the shape of the miniature organization every later group's tests rely on.
 */
#[CoversNothing]
final class FixtureOrganizationTest extends TestCase
{
    private const array REPOSITORIES = [
        'archived',
        'broken-include',
        'markdown-only',
        'missing-entry',
        'mixed',
        'unversioned',
        'versioned',
    ];

    private string $fixtures;

    private Filesystem $filesystem;

    #[Test]
    public function brokenIncludeFixtureIncludesAMissingFile(): void
    {
        $page    = Path::join($this->repository('broken-include'), 'docs', 'v1', 'index.adoc');
        $include = $this->firstIncludeTarget($this->filesystem->read($page));

        static::assertNotNull($include);
        static::assertFileDoesNotExist(Path::join($this->repository('broken-include'), 'docs', 'v1', $include));
    }

    #[Test]
    public function everyManifestEntryPointsAtItsFixtureDirectory(): void
    {
        $manifest = new ManifestLoader()->load(Path::join($this->fixtures, 'manifest.json'));

        foreach ($manifest->repositories() as $entry) {
            $path = $entry->path();

            static::assertNotNull($path, $entry->name());
            static::assertDirectoryExists($path);
            static::assertSame($entry->name(), basename($path));
            static::assertSame('main', $entry->defaultBranch());
            static::assertTrue($entry->onboarded());
        }
    }

    #[Test]
    public function exactlyOneFixtureIsArchived(): void
    {
        $manifest = new ManifestLoader()->load(Path::join($this->fixtures, 'manifest.json'));

        $archived = [];

        foreach ($manifest->repositories() as $entry) {
            if (! $entry->archived()) {
                continue;
            }

            $archived[] = $entry->name();
        }

        static::assertSame(['archived'], $archived);
    }

    #[Test]
    public function fixtureOrganizationContainsExactlyTheDocumentedRepositories(): void
    {
        static::assertSame(self::REPOSITORIES, $this->repositoryDirectories());
    }

    #[Test]
    public function manifestListsExactlyTheFixtureDirectoriesOnDisk(): void
    {
        $manifest = new ManifestLoader()->load(Path::join($this->fixtures, 'manifest.json'));

        $names = $manifest->names();
        sort($names);

        static::assertSame($this->repositoryDirectories(), $names);
    }

    #[Test]
    public function manifestSlugsAreUnique(): void
    {
        $manifest = new ManifestLoader()->load(Path::join($this->fixtures, 'manifest.json'));

        $slugs = [];

        foreach ($manifest->repositories() as $entry) {
            $slugs[] = $entry->slug();
        }

        static::assertSame($slugs, array_values(array_unique($slugs)));
    }

    #[Test]
    public function markdownOnlyFixtureContainsNoAsciiDoc(): void
    {
        $files = $this->filesystem->scan(Path::join($this->repository('markdown-only'), 'docs'));

        static::assertNotSame([], $files);

        foreach ($files as $file) {
            static::assertTrue(
                str_ends_with($file, '.md'),
                "The markdown-only fixture must not ship {$file}.",
            );
        }
    }

    #[Test]
    public function missingEntryFixtureHasNoEntryCandidate(): void
    {
        $docs = Path::join($this->repository('missing-entry'), 'docs', 'v1');

        foreach (SourceSection::fromArray([])->entryCandidates() as $candidate) {
            static::assertFileDoesNotExist(Path::join($docs, $candidate));
        }

        static::assertFileExists(Path::join($docs, 'other.adoc'));
    }

    #[Test]
    public function mixedFixtureHasVersionDirectoriesAndALoosePage(): void
    {
        static::assertSame(['v1', 'v2'], $this->versionDirectories('mixed'));
        static::assertFileExists(Path::join($this->repository('mixed'), 'docs', 'loose.adoc'));
    }

    #[Test]
    public function unversionedFixtureHasADocsEntryPage(): void
    {
        static::assertFileExists(Path::join($this->repository('unversioned'), 'docs', 'index.adoc'));
        static::assertSame([], $this->versionDirectories('unversioned'));
    }

    #[Test]
    public function versionedFixtureHasTwoVersionsWithEntryPages(): void
    {
        static::assertFileExists(Path::join($this->repository('versioned'), 'docs', 'v1', 'index.adoc'));
        static::assertFileExists(Path::join($this->repository('versioned'), 'docs', 'v2', 'index.adoc'));
        static::assertFileExists(Path::join($this->repository('versioned'), 'docs', 'v1', 'guide.adoc'));
        static::assertDirectoryExists(Path::join($this->repository('versioned'), 'docs', 'v1', 'reference'));
        static::assertFileExists(Path::join($this->repository('versioned'), 'docs', 'v1', 'reference', 'nested.adoc'));
    }

    protected function setUp(): void
    {
        $this->fixtures   = Path::join(Path::normalise(dirname(__DIR__, levels: 2)), 'fixtures');
        $this->filesystem = new Filesystem();
    }

    private function firstIncludeTarget(string $contents): ?string
    {
        foreach (explode("\n", $contents) as $line) {
            $line = trim($line);

            if (! str_starts_with($line, 'include::')) {
                continue;
            }

            $start = strpos($line, needle: '[');

            if (false === $start) {
                continue;
            }

            return substr($line, strlen('include::'), $start - strlen('include::'));
        }

        return null;
    }

    private function repository(string $name): string
    {
        return Path::join($this->fixtures, 'org', $name);
    }

    /**
     * @return list<string>
     */
    private function repositoryDirectories(): array
    {
        $entries = scandir(Path::join($this->fixtures, 'org'));

        static::assertIsArray($entries);

        $directories = [];

        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            if (is_dir(Path::join($this->fixtures, 'org', $entry))) {
                $directories[] = $entry;
            }
        }

        sort($directories);

        return $directories;
    }

    /**
     * @return list<string>
     */
    private function versionDirectories(string $repository): array
    {
        $entries = scandir(Path::join($this->repository($repository), 'docs'));

        static::assertIsArray($entries);

        $directories = [];

        foreach ($entries as $entry) {
            if (
                ! (
                    str_starts_with($entry, 'v')
                    && is_dir(Path::join($this->repository($repository), 'docs', $entry))
                )
            ) {
                continue;
            }

            $directories[] = $entry;
        }

        sort($directories);

        return $directories;
    }
}
