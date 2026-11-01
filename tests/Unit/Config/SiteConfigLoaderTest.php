<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Test\Unit\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhpMultiRepo\Config\ConfigException;
use Webware\AsciidocPhpMultiRepo\Config\SiteConfigLoader;
use Webware\AsciidocPhpMultiRepo\Support\Filesystem;
use Webware\AsciidocPhpMultiRepo\Support\Path;
use Webware\AsciidocPhpMultiRepo\Test\Support\TempDirectoryTrait;

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

    public function testAbsoluteOutputPathsArePreserved(): void
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

        self::assertSame($absolute, $config->output()->directory());
    }

    public function testBundledSchemaIsShippedWithThePackage(): void
    {
        $loader = new SiteConfigLoader();

        self::assertSame(
            Path::join(Path::normalise(dirname(__DIR__, 3)), 'config', 'site.schema.json'),
            $loader->schemaPath(),
        );
        self::assertFileExists($loader->schemaPath());

        $schema = $loader->schema();

        self::assertSame('object', $schema['type']);
        self::assertTrue(isset($schema['$defs']));
    }

    public function testBundledSchemaMatchesTheContract(): void
    {
        $contract = json_decode(
            (string) file_get_contents(
                Path::join(
                    Path::normalise(dirname(__DIR__, 3)),
                    'specs',
                    '001-multi-repo-docs-site',
                    'contracts',
                    'site.schema.json',
                ),
            ),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($contract);
        self::assertFalse(array_is_list($contract));
        self::assertSame($contract, new SiteConfigLoader()->schema());
    }

    public function testLoadsMinimalDocumentAndAppliesDefaults(): void
    {
        $path = $this->writeConfig('site.json', [
            'schemaVersion' => 1,
            'site'          => ['name' => 'Docs', 'baseUrl' => 'https://example.test'],
            'organization'  => ['name' => 'webinertia', 'propertyName' => 'doc-target'],
        ]);

        $config = new SiteConfigLoader()->load($path);

        self::assertSame(1, $config->schemaVersion());
        self::assertSame('Docs', $config->site()->name());
        self::assertSame('webinertia', $config->organization()->name());
        self::assertSame('doc-target', $config->organization()->propertyName());
        self::assertSame('true', $config->organization()->onboardValue());
        self::assertSame('docs', $config->source()->directory());
        self::assertSame([2, 3], $config->render()->tocLevels());
        self::assertSame(4, $config->render()->concurrency());
        self::assertSame('webware', $config->theme()->name());
        self::assertSame([], $config->projects());
        self::assertSame(Path::normalise($path), $config->configPath());
        self::assertSame(Path::normalise($this->directory), $config->configDirectory());
    }

    public function testMalformedJsonFailsNamingTheFile(): void
    {
        $path = Path::join($this->directory, 'site.json');
        $this->filesystem->writeAtomic($path, "{\n  \"schemaVersion\": 1,\n");

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage($path);

        new SiteConfigLoader()->load($path);
    }

    public function testMissingFileFails(): void
    {
        $path = Path::join($this->directory, 'absent.json');

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage($path);

        new SiteConfigLoader()->load($path);
    }

    public function testMissingRequiredSectionFails(): void
    {
        $path = $this->writeConfig('site.json', [
            'schemaVersion' => 1,
            'site'          => ['name' => 'Docs', 'baseUrl' => 'https://example.test'],
        ]);

        try {
            new SiteConfigLoader()->load($path);
            self::fail('Expected a ConfigException.');
        } catch (ConfigException $exception) {
            self::assertSame('', $exception->pointer());
            self::assertStringContainsString('organization', $exception->getMessage());
        }
    }

    public function testMissingSchemaVersionFails(): void
    {
        $document = $this->minimalDocument();
        unset($document['schemaVersion']);

        $path = $this->writeConfig('site.json', $document);

        $this->expectException(ConfigException::class);

        new SiteConfigLoader()->load($path);
    }

    public function testNestedConfigResolvesAgainstItsOwnDirectory(): void
    {
        $nested = Path::join($this->directory, 'conf');
        $this->filesystem->ensureDirectory($nested);

        $path = $this->writeConfig('conf/site.json', $this->minimalDocument());

        $config = new SiteConfigLoader()->load($path);

        self::assertSame(Path::join($nested, 'build', 'site'), $config->output()->directory());
    }

    public function testOutputPathsResolveAgainstTheConfigDirectory(): void
    {
        $path = $this->writeConfig(
            'site.json',
            $this->minimalDocument()
                + [
                    'output' => ['directory' => 'out', 'cacheDirectory' => 'cache', 'reportPath' => 'r.json'],
                ],
        );

        $config = new SiteConfigLoader()->load($path);

        self::assertSame(Path::join($this->directory, 'out'), $config->output()->directory());
        self::assertSame(Path::join($this->directory, 'cache'), $config->output()->cacheDirectory());
        self::assertSame(Path::join($this->directory, 'r.json'), $config->output()->reportPath());
    }

    public function testProjectsAreParsedAndSorted(): void
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

        self::assertSame(['message-bus', 'messagebus-event'], array_keys($config->projects()));
        self::assertSame('bus', $config->project('message-bus')?->slug());
        self::assertSame(1, $config->project('message-bus')?->order());
        self::assertNull($config->project('missing'));
    }

    public function testRelativePathResolvesAgainstTheWorkingDirectory(): void
    {
        $this->writeConfig('site.json', $this->minimalDocument());

        $relative = Path::relativeTo((string) getcwd(), Path::join($this->directory, 'site.json'));

        $config = new SiteConfigLoader()->load($relative);

        self::assertSame(Path::join($this->directory, 'site.json'), $config->configPath());
    }

    public function testUnknownTopLevelKeyFailsNamingTheKeyAndPointer(): void
    {
        $path = $this->writeConfig('site.json', $this->minimalDocument() + ['typo' => true]);

        try {
            new SiteConfigLoader()->load($path);
            self::fail('Expected a ConfigException.');
        } catch (ConfigException $exception) {
            self::assertSame('/typo', $exception->pointer());
            self::assertStringContainsString('typo', $exception->getMessage());
            self::assertStringContainsString('/typo', $exception->getMessage());
        }
    }

    public function testUnsupportedSchemaVersionFails(): void
    {
        $document                  = $this->minimalDocument();
        $document['schemaVersion'] = 2;

        $path = $this->writeConfig('site.json', $document);

        try {
            new SiteConfigLoader()->load($path);
            self::fail('Expected a ConfigException.');
        } catch (ConfigException $exception) {
            self::assertSame('/schemaVersion', $exception->pointer());
            self::assertStringContainsString('schemaVersion', $exception->getMessage());
        }
    }

    public function testWrongPropertyTypeFailsAtThePropertyPointer(): void
    {
        $document         = $this->minimalDocument();
        $document['site'] = ['name' => 42, 'baseUrl' => 'https://example.test'];

        $path = $this->writeConfig('site.json', $document);

        try {
            new SiteConfigLoader()->load($path);
            self::fail('Expected a ConfigException.');
        } catch (ConfigException $exception) {
            self::assertSame('/site/name', $exception->pointer());
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
