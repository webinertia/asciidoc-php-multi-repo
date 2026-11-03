<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Config\ConfigException;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfigLoader;
use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function array_is_list;
use function array_keys;
use function dirname;
use function file_get_contents;
use function getcwd;
use function json_decode;
use function json_encode;

#[CoversClass(SiteConfigLoader::class)]
final class SiteConfigLoaderTest extends TestCase
{
    use TempDirectoryTrait;

    private string $directory;

    private Filesystem $filesystem;

    #[Test]
    public function absoluteOutputPathsArePreserved(): void
    {
        $absolute = Path::join($this->directory, 'elsewhere');

        $path = $this->writeConfig(
            'site.json',
            $this->minimalDocument()
                + [
                    'output' => ['directory' => $absolute],
                ],
        );

        $config = new SiteConfigLoader()->load($path);

        static::assertSame($absolute, $config->output()->directory());
    }

    #[Test]
    public function bundledSchemaIsShippedWithThePackage(): void
    {
        $loader = new SiteConfigLoader();

        static::assertSame(
            Path::join(Path::normalise(dirname(__DIR__, levels: 3)), 'config', 'site.schema.json'),
            $loader->schemaPath(),
        );
        static::assertFileExists($loader->schemaPath());

        $schema = $loader->schema();

        static::assertSame('object', $schema['type']);
        static::assertTrue(isset($schema['$defs']));
    }

    #[Test]
    public function bundledSchemaMatchesTheContract(): void
    {
        $contract = json_decode(
            (string) file_get_contents(
                Path::join(
                    Path::normalise(dirname(__DIR__, levels: 3)),
                    'specs',
                    '001-multi-repo-docs-site',
                    'contracts',
                    'site.schema.json',
                ),
            ),
            associative: true,
            depth: 512,
            flags: JSON_THROW_ON_ERROR,
        );

        static::assertIsArray($contract);
        static::assertFalse(array_is_list($contract));
        static::assertSame($contract, new SiteConfigLoader()->schema());
    }

    #[Test]
    public function loadsMinimalDocumentAndAppliesDefaults(): void
    {
        $path = $this->writeConfig('site.json', [
            'schemaVersion' => 1,
            'site'          => ['name' => 'Docs', 'baseUrl' => 'https://example.test'],
            'organization'  => ['name' => 'webinertia', 'propertyName' => 'doc-target'],
        ]);

        $config = new SiteConfigLoader()->load($path);

        static::assertSame(1, $config->schemaVersion());
        static::assertSame('Docs', $config->site()->name());
        static::assertSame('webinertia', $config->organization()->name());
        static::assertSame('doc-target', $config->organization()->propertyName());
        static::assertSame('true', $config->organization()->onboardValue());
        static::assertSame('docs', $config->source()->directory());
        static::assertSame([2, 3], $config->render()->tocLevels());
        static::assertSame(4, $config->render()->concurrency());
        static::assertSame('webware', $config->theme()->name());
        static::assertSame([], $config->projects());
        static::assertSame(Path::normalise($path), $config->configPath());
        static::assertSame(Path::normalise($this->directory), $config->configDirectory());
    }

    #[Test]
    public function malformedJsonFailsNamingTheFile(): void
    {
        $path = Path::join($this->directory, 'site.json');
        $this->filesystem->writeAtomic($path, "{\n  \"schemaVersion\": 1,\n");

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage($path);

        new SiteConfigLoader()->load($path);
    }

    #[Test]
    public function missingFileFails(): void
    {
        $path = Path::join($this->directory, 'absent.json');

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage($path);

        new SiteConfigLoader()->load($path);
    }

    #[Test]
    public function missingRequiredSectionFails(): void
    {
        $path = $this->writeConfig('site.json', [
            'schemaVersion' => 1,
            'site'          => ['name' => 'Docs', 'baseUrl' => 'https://example.test'],
        ]);

        try {
            new SiteConfigLoader()->load($path);
            self::fail('Expected a ConfigException.');
        } catch (ConfigException $exception) {
            static::assertSame('', $exception->pointer());
            static::assertStringContainsString('organization', $exception->getMessage());
        }
    }

    #[Test]
    public function missingSchemaVersionFails(): void
    {
        $document = $this->minimalDocument();
        unset($document['schemaVersion']);

        $path = $this->writeConfig('site.json', $document);

        $this->expectException(ConfigException::class);

        new SiteConfigLoader()->load($path);
    }

    #[Test]
    public function nestedConfigResolvesAgainstItsOwnDirectory(): void
    {
        $nested = Path::join($this->directory, 'conf');
        $this->filesystem->ensureDirectory($nested);

        $path = $this->writeConfig('conf/site.json', $this->minimalDocument());

        $config = new SiteConfigLoader()->load($path);

        static::assertSame(Path::join($nested, 'build', 'site'), $config->output()->directory());
    }

    #[Test]
    public function outputPathsResolveAgainstTheConfigDirectory(): void
    {
        $path = $this->writeConfig(
            'site.json',
            $this->minimalDocument()
                + [
                    'output' => ['directory' => 'out', 'cacheDirectory' => 'cache', 'reportPath' => 'r.json'],
                ],
        );

        $config = new SiteConfigLoader()->load($path);

        static::assertSame(Path::join($this->directory, 'out'), $config->output()->directory());
        static::assertSame(Path::join($this->directory, 'cache'), $config->output()->cacheDirectory());
        static::assertSame(Path::join($this->directory, 'r.json'), $config->output()->reportPath());
    }

    #[Test]
    public function projectsAreParsedAndSorted(): void
    {
        $path = $this->writeConfig(
            'site.json',
            $this->minimalDocument()
                + [
                    'projects' => [
                        'messagebus-event' => ['title' => 'Message Bus Event'],
                        'message-bus'      => ['slug' => 'bus', 'order' => 1],
                    ],
                ],
        );

        $config = new SiteConfigLoader()->load($path);

        static::assertSame(['message-bus', 'messagebus-event'], array_keys($config->projects()));
        static::assertSame('bus', $config->project('message-bus')?->slug());
        static::assertSame(1, $config->project('message-bus')?->order());
        static::assertNull($config->project('missing'));
    }

    #[Test]
    public function relativePathResolvesAgainstTheWorkingDirectory(): void
    {
        $this->writeConfig('site.json', $this->minimalDocument());

        $relative = Path::relativeTo((string) getcwd(), Path::join($this->directory, 'site.json'));

        $config = new SiteConfigLoader()->load($relative);

        static::assertSame(Path::join($this->directory, 'site.json'), $config->configPath());
    }

    #[Test]
    public function unknownTopLevelKeyFailsNamingTheKeyAndPointer(): void
    {
        $path = $this->writeConfig('site.json', $this->minimalDocument() + ['typo' => true]);

        try {
            new SiteConfigLoader()->load($path);
            self::fail('Expected a ConfigException.');
        } catch (ConfigException $exception) {
            static::assertSame('/typo', $exception->pointer());
            static::assertStringContainsString('typo', $exception->getMessage());
            static::assertStringContainsString('/typo', $exception->getMessage());
        }
    }

    #[Test]
    public function unsupportedSchemaVersionFails(): void
    {
        $document                  = $this->minimalDocument();
        $document['schemaVersion'] = 2;

        $path = $this->writeConfig('site.json', $document);

        try {
            new SiteConfigLoader()->load($path);
            self::fail('Expected a ConfigException.');
        } catch (ConfigException $exception) {
            static::assertSame('/schemaVersion', $exception->pointer());
            static::assertStringContainsString('schemaVersion', $exception->getMessage());
        }
    }

    #[Test]
    public function wrongPropertyTypeFailsAtThePropertyPointer(): void
    {
        $document         = $this->minimalDocument();
        $document['site'] = ['name' => 42, 'baseUrl' => 'https://example.test'];

        $path = $this->writeConfig('site.json', $document);

        try {
            new SiteConfigLoader()->load($path);
            self::fail('Expected a ConfigException.');
        } catch (ConfigException $exception) {
            static::assertSame('/site/name', $exception->pointer());
        }
    }

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory  = $this->createTempDirectory();
    }

    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalDocument(): array
    {
        return [
            'schemaVersion' => 1,
            'site'          => ['name' => 'Docs', 'baseUrl' => 'https://example.test'],
            'organization'  => ['name' => 'webinertia', 'propertyName' => 'doc-target'],
        ];
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function writeConfig(string $name, array $extra): string
    {
        $path = Path::join($this->directory, $name);

        $this->filesystem->writeAtomic(
            $path,
            json_encode($extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
        );

        return $path;
    }
}
