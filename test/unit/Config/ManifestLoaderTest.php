<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Config\ConfigException;
use Webware\AsciidocPhp\MultiRepo\Config\ManifestLoader;
use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function dirname;
use function json_encode;

#[CoversClass(ManifestLoader::class)]
final class ManifestLoaderTest extends TestCase
{
    use TempDirectoryTrait;

    private Filesystem $filesystem;

    #[Test]
    public function absoluteEntryPathsArePreserved(): void
    {
        $absolute = Path::join($this->tempDirectory, 'elsewhere');

        $path = $this->writeManifest([
            'schemaVersion' => 1,
            'repositories'  => [['name' => 'only', 'path' => $absolute]],
        ]);

        static::assertSame(
            $absolute,
            new ManifestLoader()
                ->load($path)
                ->repositories()[0]->path(),
        );
    }

    #[Test]
    public function bundledSchemaIsShippedWithThePackage(): void
    {
        $loader = new ManifestLoader();

        static::assertFileExists($loader->schemaPath());
        static::assertSame(
            Path::join(Path::normalise(dirname(__DIR__, levels: 3)), 'config', 'manifest.schema.json'),
            $loader->schemaPath(),
        );
    }

    #[Test]
    public function emptyRepositoryListFails(): void
    {
        $path = $this->writeManifest([
            'schemaVersion' => 1,
            'repositories'  => [],
        ]);

        try {
            new ManifestLoader()->load($path);
            self::fail('Expected a ConfigException.');
        } catch (ConfigException $exception) {
            static::assertSame('/repositories', $exception->pointer());
        }
    }

    #[Test]
    public function entryDefaultsAreApplied(): void
    {
        $path = $this->writeManifest([
            'schemaVersion' => 1,
            'repositories'  => [['name' => 'only']],
        ]);

        $entry = new ManifestLoader()
            ->load($path)
            ->repositories()[0];

        static::assertSame('only', $entry->name());
        static::assertNull($entry->slug());
        static::assertSame('main', $entry->defaultBranch());
        static::assertFalse($entry->archived());
        static::assertTrue($entry->onboarded());
        static::assertNull($entry->path());
    }

    #[Test]
    public function loadsTheFixtureManifest(): void
    {
        $path     = Path::join(Path::normalise(dirname(__DIR__, levels: 2)), 'fixtures', 'manifest.json');
        $manifest = new ManifestLoader()->load($path);

        static::assertSame(1, $manifest->schemaVersion());
        static::assertSame(Path::normalise($path), $manifest->path());
        static::assertCount(7, $manifest->repositories());
        static::assertSame('versioned', $manifest->names()[0]);
    }

    #[Test]
    public function missingFileFails(): void
    {
        $path = Path::join($this->tempDirectory, 'absent.json');

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage($path);

        new ManifestLoader()->load($path);
    }

    #[Test]
    public function relativeEntryPathsResolveAgainstTheManifestDirectory(): void
    {
        $path = $this->writeManifest([
            'schemaVersion' => 1,
            'repositories'  => [['name' => 'only', 'path' => 'org/only']],
        ]);

        $entry = new ManifestLoader()
            ->load($path)
            ->repositories()[0];

        static::assertSame(Path::join($this->tempDirectory, 'org', 'only'), $entry->path());
    }

    #[Test]
    public function unknownEntryKeyFailsAtTheEntryPointer(): void
    {
        $path = $this->writeManifest([
            'schemaVersion' => 1,
            'repositories'  => [['name' => 'only', 'typo' => true]],
        ]);

        try {
            new ManifestLoader()->load($path);
            self::fail('Expected a ConfigException.');
        } catch (ConfigException $exception) {
            static::assertSame('/repositories/0/typo', $exception->pointer());
        }
    }

    #[Test]
    public function unknownRootKeyFails(): void
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
            static::assertSame('/typo', $exception->pointer());
            static::assertStringContainsString('/typo', $exception->getMessage());
        }
    }

    #[Test]
    public function unsupportedSchemaVersionFails(): void
    {
        $path = $this->writeManifest([
            'schemaVersion' => 2,
            'repositories'  => [['name' => 'only']],
        ]);

        try {
            new ManifestLoader()->load($path);
            self::fail('Expected a ConfigException.');
        } catch (ConfigException $exception) {
            static::assertSame('/schemaVersion', $exception->pointer());
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
