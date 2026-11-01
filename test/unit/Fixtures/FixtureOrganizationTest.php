<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Fixtures;

use PHPUnit\Framework\Attributes\CoversNothing;
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

    public function testBrokenIncludeFixtureIncludesAMissingFile(): void
    {
        $page    = Path::join($this->repository('broken-include'), 'docs', 'v1', 'index.adoc');
        $include = $this->firstIncludeTarget($this->filesystem->read($page));

        self::assertNotNull($include);
        self::assertFileDoesNotExist(Path::join($this->repository('broken-include'), 'docs', 'v1', $include));
    }

    public function testEveryManifestEntryPointsAtItsFixtureDirectory(): void
    {
        $manifest = new ManifestLoader()->load(Path::join($this->fixtures, 'manifest.json'));

        foreach ($manifest->repositories() as $entry) {
            $path = $entry->path();

            self::assertNotNull($path, $entry->name());
            self::assertDirectoryExists($path);
            self::assertSame($entry->name(), basename($path));
            self::assertSame('main', $entry->defaultBranch());
            self::assertTrue($entry->onboarded());
        }
    }

    public function testExactlyOneFixtureIsArchived(): void
    {
        $manifest = new ManifestLoader()->load(Path::join($this->fixtures, 'manifest.json'));

        $archived = [];

        foreach ($manifest->repositories() as $entry) {
            if (! $entry->archived()) {
                continue;
            }

            $archived[] = $entry->name();
        }

        self::assertSame(['archived'], $archived);
    }

    public function testFixtureOrganizationContainsExactlyTheDocumentedRepositories(): void
    {
        self::assertSame(self::REPOSITORIES, $this->repositoryDirectories());
    }

    public function testManifestListsExactlyTheFixtureDirectoriesOnDisk(): void
    {
        $manifest = new ManifestLoader()->load(Path::join($this->fixtures, 'manifest.json'));

        $names = $manifest->names();
        sort($names);

        self::assertSame($this->repositoryDirectories(), $names);
    }

    public function testManifestSlugsAreUnique(): void
    {
        $manifest = new ManifestLoader()->load(Path::join($this->fixtures, 'manifest.json'));

        $slugs = [];

        foreach ($manifest->repositories() as $entry) {
            $slugs[] = $entry->slug();
        }

        self::assertSame($slugs, array_values(array_unique($slugs)));
    }

    public function testMarkdownOnlyFixtureContainsNoAsciiDoc(): void
    {
        $files = $this->filesystem->scan(Path::join($this->repository('markdown-only'), 'docs'));

        self::assertNotSame([], $files);

        foreach ($files as $file) {
            self::assertTrue(
                str_ends_with($file, '.md'),
                "The markdown-only fixture must not ship {$file}.",
            );
        }
    }

    public function testMissingEntryFixtureHasNoEntryCandidate(): void
    {
        $docs = Path::join($this->repository('missing-entry'), 'docs', 'v1');

        foreach (SourceSection::fromArray([])->entryCandidates() as $candidate) {
            self::assertFileDoesNotExist(Path::join($docs, $candidate));
        }

        self::assertFileExists(Path::join($docs, 'other.adoc'));
    }

    public function testMixedFixtureHasVersionDirectoriesAndALoosePage(): void
    {
        self::assertSame(['v1', 'v2'], $this->versionDirectories('mixed'));
        self::assertFileExists(Path::join($this->repository('mixed'), 'docs', 'loose.adoc'));
    }

    public function testUnversionedFixtureHasADocsEntryPage(): void
    {
        self::assertFileExists(Path::join($this->repository('unversioned'), 'docs', 'index.adoc'));
        self::assertSame([], $this->versionDirectories('unversioned'));
    }

    public function testVersionedFixtureHasTwoVersionsWithEntryPages(): void
    {
        self::assertFileExists(Path::join($this->repository('versioned'), 'docs', 'v1', 'index.adoc'));
        self::assertFileExists(Path::join($this->repository('versioned'), 'docs', 'v2', 'index.adoc'));
        self::assertFileExists(Path::join($this->repository('versioned'), 'docs', 'v1', 'guide.adoc'));
        self::assertDirectoryExists(Path::join($this->repository('versioned'), 'docs', 'v1', 'reference'));
        self::assertFileExists(Path::join($this->repository('versioned'), 'docs', 'v1', 'reference', 'nested.adoc'));
    }

    protected function setUp(): void
    {
        $this->fixtures   = Path::join(Path::normalise(dirname(__DIR__, 2)), 'fixtures');
        $this->filesystem = new Filesystem();
    }

    private function firstIncludeTarget(string $contents): ?string
    {
        foreach (explode("\n", $contents) as $line) {
            $line = trim($line);

            if (! str_starts_with($line, 'include::')) {
                continue;
            }

            $start = strpos($line, '[');

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

        self::assertIsArray($entries);

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

        self::assertIsArray($entries);

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
