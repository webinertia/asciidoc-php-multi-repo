<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Test\Unit\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhpMultiRepo\Config\ConfigException;
use Webware\AsciidocPhpMultiRepo\Config\ManifestLoader;
use Webware\AsciidocPhpMultiRepo\Support\Filesystem;
use Webware\AsciidocPhpMultiRepo\Support\Path;
use Webware\AsciidocPhpMultiRepo\Test\Support\TempDirectoryTrait;

use function dirname;
use function json_encode;

#[CoversClass(ManifestLoader::class)]
final class ManifestLoaderTest extends TestCase
{
    use TempDirectoryTrait;

    private Filesystem $filesystem;

    public function testAbsoluteEntryPathsArePreserved(): void
    {
        $absolute = Path::join($this->tempDirectory, 'elsewhere');

        $path = $this->writeManifest([
            'schemaVersion' => 1,
            'repositories'  => [['name' => 'only', 'path' => $absolute]],
        ]);

        self::assertSame(
            $absolute,
            new ManifestLoader()
                ->load($path)
                ->repositories()[0]->path(),
        );
    }

    public function testBundledSchemaIsShippedWithThePackage(): void
    {
        $loader = new ManifestLoader();

        self::assertFileExists($loader->schemaPath());
        self::assertSame(
            Path::join(Path::normalise(dirname(__DIR__, 3)), 'config', 'manifest.schema.json'),
            $loader->schemaPath(),
        );
    }

    public function testEmptyRepositoryListFails(): void
    {
        $path = $this->writeManifest([
            'schemaVersion' => 1,
            'repositories'  => [],
        ]);

        try {
            new ManifestLoader()->load($path);
            self::fail('Expected a ConfigException.');
        } catch (ConfigException $exception) {
            self::assertSame('/repositories', $exception->pointer());
        }
    }

    public function testEntryDefaultsAreApplied(): void
    {
        $path = $this->writeManifest([
            'schemaVersion' => 1,
            'repositories'  => [['name' => 'only']],
        ]);

        $entry = new ManifestLoader()
            ->load($path)
            ->repositories()[0];

        self::assertSame('only', $entry->name());
        self::assertNull($entry->slug());
        self::assertSame('main', $entry->defaultBranch());
        self::assertFalse($entry->archived());
        self::assertTrue($entry->onboarded());
        self::assertNull($entry->path());
    }

    public function testLoadsTheFixtureManifest(): void
    {
        $path     = Path::join(Path::normalise(dirname(__DIR__, 2)), 'fixtures', 'manifest.json');
        $manifest = new ManifestLoader()->load($path);

        self::assertSame(1, $manifest->schemaVersion());
        self::assertSame(Path::normalise($path), $manifest->path());
        self::assertCount(7, $manifest->repositories());
        self::assertSame('versioned', $manifest->names()[0]);
    }

    public function testMissingFileFails(): void
    {
        $path = Path::join($this->tempDirectory, 'absent.json');

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage($path);

        new ManifestLoader()->load($path);
    }

    public function testRelativeEntryPathsResolveAgainstTheManifestDirectory(): void
    {
        $path = $this->writeManifest([
            'schemaVersion' => 1,
            'repositories'  => [['name' => 'only', 'path' => 'org/only']],
        ]);

        $entry = new ManifestLoader()
            ->load($path)
            ->repositories()[0];

        self::assertSame(Path::join($this->tempDirectory, 'org', 'only'), $entry->path());
    }

    public function testUnknownEntryKeyFailsAtTheEntryPointer(): void
    {
        $path = $this->writeManifest([
            'schemaVersion' => 1,
            'repositories'  => [['name' => 'only', 'typo' => true]],
        ]);

        try {
            new ManifestLoader()->load($path);
            self::fail('Expected a ConfigException.');
        } catch (ConfigException $exception) {
            self::assertSame('/repositories/0/typo', $exception->pointer());
        }
    }

    public function testUnknownRootKeyFails(): void
    {
        $path = $this->writeManifest([
            'schemaVersion' => 1,
            'repositories'  => [['name' => 'only']],
            'typo'          => true,
        ]);

        try {
            new ManifestLoader()->load($path);
            self::fail('Expected a ConfigException.');
        } catch (ConfigException $exception) {
            self::assertSame('/typo', $exception->pointer());
            self::assertStringContainsString('/typo', $exception->getMessage());
        }
    }

    public function testUnsupportedSchemaVersionFails(): void
    {
        $path = $this->writeManifest([
            'schemaVersion' => 2,
            'repositories'  => [['name' => 'only']],
        ]);

        try {
            new ManifestLoader()->load($path);
            self::fail('Expected a ConfigException.');
        } catch (ConfigException $exception) {
            self::assertSame('/schemaVersion', $exception->pointer());
        }
    }

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->createTempDirectory();
    }

    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }

    /**
     * @param array<string, mixed> $document
     */
    private function writeManifest(array $document): string
    {
        $path = Path::join($this->tempDirectory, 'manifest.json');

        $this->filesystem->writeAtomic(
            $path,
            json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
        );

        return $path;
    }
}
