<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Cli\Commands\FetchCommand;
use Webware\AsciidocPhp\MultiRepo\Cli\ExitCode;
use Webware\AsciidocPhp\MultiRepo\Cli\Options;
use Webware\AsciidocPhp\MultiRepo\Cli\Output;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfig;
use Webware\AsciidocPhp\MultiRepo\Discovery\DiscoveryFactory;
use Webware\AsciidocPhp\MultiRepo\Source\RepositoryFetcher;
use Webware\AsciidocPhp\MultiRepo\Source\SourceCollector;
use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Json;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use WebwareTest\AsciidocPhp\MultiRepo\Support\CapturedOutput;
use WebwareTest\AsciidocPhp\MultiRepo\Support\SourceFixture;
use WebwareTest\AsciidocPhp\MultiRepo\Support\StubHttpResponse;
use WebwareTest\AsciidocPhp\MultiRepo\Support\StubHttpTransport;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function array_column;
use function explode;
use function is_file;
use function json_decode;
use function json_encode;
use function putenv;
use function rtrim;

use const JSON_THROW_ON_ERROR;

/**
 * Fetching end to end through the CLI: the fixture repositories are read from their local paths, so
 * this needs no network and no git.
 */
#[CoversClass(FetchCommand::class)]
#[CoversMethod(FetchCommand::class, 'execute')]
#[CoversMethod(FetchCommand::class, 'name')]
#[CoversMethod(FetchCommand::class, 'summary')]
final class FetchCommandTest extends TestCase
{
    use TempDirectoryTrait;

    private CapturedOutput $captured;

    private Filesystem $filesystem;

    private bool $verbose = false;

    public function testADiscoveryFailureExitsThreeAndWritesNoManifest(): void
    {
        $exit = $this->executeCommand(
            manifest : null,
            config   : SourceFixture::config(),
            transport: new StubHttpTransport(['properties/values' => StubHttpResponse::create(401, '{}')]),
        );

        self::assertSame(ExitCode::DiscoveryError, $exit);
        self::assertFalse(is_file($this->manifestPath()));
        self::assertStringContainsString('error: ', $this->captured->stderr);
    }

    public function testAHealthyRepositoryIsRecordedWithoutAnError(): void
    {
        $this->executeCommand(SourceFixture::path('manifest.json'));

        self::assertNull($this->entry('unversioned')['error']);
        self::assertNull($this->entry('mixed')['error']);
    }

    public function testALocalPathThatDoesNotExistIsRecordedWithNoAsciidocSources(): void
    {
        $manifest = $this->writeManifest([
            ['name' => 'versioned', 'path' => Path::join($this->tempDirectory, 'absent')],
        ]);

        $this->verbose = true;

        $exit = $this->executeCommand($manifest);

        self::assertSame(ExitCode::Success, $exit);
        self::assertSame('no-asciidoc-sources', $this->entry('versioned')['error']['reasonCode']);
        self::assertStringContainsString('versioned: 0 version(s), 0 page(s), revision ', $this->captured->stderr);
    }

    public function testAMarkdownOnlyRepositoryIsRecordedWithNoAsciidocSources(): void
    {
        $this->executeCommand(SourceFixture::path('manifest.json'));

        $entry = $this->entry('markdown-only');

        self::assertSame('no-asciidoc-sources', $entry['error']['reasonCode']);
        self::assertSame('docs', $entry['documentationPath']);
    }

    public function testAnArchivedRepositoryIsRecordedWithTheArchivedReasonCode(): void
    {
        $manifest = $this->writeManifest([
            ['name' => 'archived', 'path' => SourceFixture::fixtureDirectory('archived'), 'archived' => true],
            ['name' => 'versioned', 'path' => SourceFixture::fixtureDirectory('versioned')],
        ]);

        $this->executeCommand($manifest, $this->configWithoutExclusions());

        self::assertSame('archived', $this->entry('archived')['error']['reasonCode']);
        self::assertNull($this->entry('archived')['revision']);
        self::assertSame(
            ['archived', 'versioned'],
            array_column($this->manifest()['repositories'], 'name'),
        );
        self::assertStringContainsString('archived: archived, skipped', $this->captured->stderr);
    }

    public function testARepositoryThatIsNotOnboardedIsAnnouncedAndDoesNotStopTheLoop(): void
    {
        $manifest = $this->writeManifest([
            ['name' => 'alpha', 'path' => SourceFixture::fixtureDirectory('versioned'), 'onboarded' => false],
            ['name' => 'zeta', 'path' => SourceFixture::fixtureDirectory('unversioned')],
        ]);

        $this->verbose = true;
        $this->executeCommand($manifest);

        self::assertSame(['zeta'], array_column($this->manifest()['repositories'], 'name'));
        self::assertStringContainsString('alpha: not onboarded, skipped', $this->captured->stderr);
    }

    public function testARepositoryThatIsNotOnboardedIsSkippedEntirely(): void
    {
        $manifest = $this->writeManifest([
            ['name' => 'versioned', 'path' => SourceFixture::fixtureDirectory('versioned'), 'onboarded' => false],
            ['name' => 'unversioned', 'path' => SourceFixture::fixtureDirectory('unversioned')],
        ]);

        $this->executeCommand($manifest);

        self::assertSame(['unversioned'], array_column($this->manifest()['repositories'], 'name'));
    }

    public function testARepositoryWhoseFetchFailsIsReportedWithAnUnknownRevision(): void
    {
        $manifest = $this->writeManifest([
            ['name' => 'versioned'],
        ]);

        $this->verbose = true;

        $exit = $this->executeCommand(
            manifest: $manifest,
            collector: new SourceCollector(
                fetcher: new RepositoryFetcher(
                    remoteUrl: fn(): string => Path::join($this->tempDirectory, 'absent-repository'),
                ),
            ),
        );

        self::assertSame(ExitCode::Success, $exit);
        self::assertSame('fetch-failed', $this->entry('versioned')['error']['reasonCode']);
        self::assertNull($this->entry('versioned')['revision']);
        self::assertStringContainsString(
            'versioned: 0 version(s), 0 page(s), revision unknown',
            $this->captured->stderr,
        );
    }

    public function testARepositoryWithoutAnEntryPageIsRecordedWithMissingEntry(): void
    {
        $this->executeCommand(SourceFixture::path('manifest.json'));

        self::assertSame('missing-entry', $this->entry('missing-entry')['error']['reasonCode']);
    }

    public function testAResolvedRepositoryRecordsItsRevisionAndDocumentationPath(): void
    {
        $this->executeCommand(SourceFixture::path('manifest.json'));

        $versioned = $this->entry('versioned');

        self::assertNull($versioned['error']);
        self::assertSame('docs', $versioned['documentationPath']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $versioned['revision']);
    }

    public function testASlugCollisionExitsTwoAndWritesNoManifest(): void
    {
        $exit = $this->executeCommand(
            manifest : null,
            config   : SourceFixture::config(),
            transport: $this->collidingTransport(),
        );

        self::assertSame(ExitCode::ConfigError, $exit);
        self::assertFalse(is_file($this->manifestPath()));
        self::assertStringContainsString('message-bus', $this->captured->stderr);
    }

    public function testASlugOverrideCollisionExitsTwoBeforeAnyFetch(): void
    {
        $exit = $this->executeCommand(
            manifest: SourceFixture::path('manifest.json'),
            config  : SourceFixture::config(['projects' => ['versioned' => ['slug' => 'mixed']]]),
        );

        self::assertSame(ExitCode::ConfigError, $exit);
        self::assertStringContainsString('"mixed"', $this->captured->stderr);
        self::assertStringNotContainsString(': fetching', $this->captured->stderr);
        self::assertFalse(is_file($this->manifestPath()));
    }

    public function testTheCacheManifestCarriesOneEntryPerOnboardedRepository(): void
    {
        $this->executeCommand(SourceFixture::path('manifest.json'));

        self::assertSame(
            ['broken-include', 'markdown-only', 'missing-entry', 'mixed', 'unversioned', 'versioned'],
            array_column($this->manifest()['repositories'], 'name'),
        );
    }

    public function testTheCacheManifestDeclaresItsSchemaVersion(): void
    {
        $this->executeCommand(SourceFixture::path('manifest.json'));

        self::assertSame(1, $this->manifest()['schemaVersion']);
    }

    public function testTheCacheManifestIsRewrittenOnASecondRun(): void
    {
        $this->executeCommand(SourceFixture::path('manifest.json'));
        $first = $this->filesystem->read($this->manifestPath());

        $this->executeCommand(SourceFixture::path('manifest.json'));

        self::assertSame($first, $this->filesystem->read($this->manifestPath()));
    }

    public function testTheCommandNameAndSummaryAreSet(): void
    {
        $command = new FetchCommand();

        self::assertSame('fetch', $command->name());
        self::assertNotSame('', $command->summary());
    }

    public function testTheFixtureManifestIsFetchedSuccessfully(): void
    {
        $this->verbose = true;

        $exit = $this->executeCommand(SourceFixture::path('manifest.json'));

        self::assertSame(ExitCode::Success, $exit);
        self::assertTrue(is_file($this->manifestPath()));
        self::assertStringContainsString('versioned: fetching', $this->captured->stderr);
        self::assertMatchesRegularExpression(
            '/versioned: \d+ version\(s\), \d+ page\(s\), revision [0-9a-f]{64}\b/',
            $this->captured->stderr,
        );
    }

    public function testTheProblemsAreReportedOnTheErrorStreamWithTheirReasonCode(): void
    {
        $this->executeCommand(SourceFixture::path('manifest.json'));

        self::assertStringContainsString('no-asciidoc-sources', $this->captured->stderr);
        self::assertStringContainsString('missing-entry', $this->captured->stderr);
        self::assertStringContainsString('outside a version directory were ignored', $this->captured->stderr);
    }

    public function testTheProjectFilterLimitsTheCacheManifest(): void
    {
        $this->executeCommand(SourceFixture::path('manifest.json'), projects: ['versioned']);

        self::assertSame(['versioned'], array_column($this->manifest()['repositories'], 'name'));
    }

    public function testTheSummaryGoesToStandardOutput(): void
    {
        $this->executeCommand(SourceFixture::path('manifest.json'));

        $lines = explode(
            separator: "\n",
            string: rtrim(
                string    : $this->captured->stdout,
                characters: "\n",
            ),
        );

        self::assertSame('6 repositories, 4 with sources, 2 with problems', $lines[0]);
        self::assertSame("cache manifest: {$this->manifestPath()}", $lines[1]);
    }

    protected function setUp(): void
    {
        $this->captured   = new CapturedOutput();
        $this->filesystem = new Filesystem();
        $this->createTempDirectory();

        putenv('FIXTURE_DISCOVERY_TOKEN=ghp_fixture_token_value');
    }

    protected function tearDown(): void
    {
        putenv('FIXTURE_DISCOVERY_TOKEN');
        $this->removeTempDirectory();
    }

    private function cacheDirectory(): string
    {
        return Path::join($this->tempDirectory, 'cache');
    }

    private function collidingTransport(): StubHttpTransport
    {
        return new StubHttpTransport([
            'properties/values'   => StubHttpResponse::create(
                200,
                (string) json_encode([
                    [
                        'repository_full_name' => 'fixture-org/message-bus',
                        'properties'           => [
                            ['property_name' => 'docs', 'value' => 'true'],
                        ],
                    ],
                    [
                        'repository_full_name' => 'fixture-org/message.bus',
                        'properties'           => [
                            ['property_name' => 'docs', 'value' => 'true'],
                        ],
                    ],
                ]),
            ),
            'search/repositories' => StubHttpResponse::create(200, (string) json_encode(['items' => []])),
        ]);
    }

    private function configWithoutExclusions(): SiteConfig
    {
        return SourceFixture::config([
            'organization' => [
                'name'                => 'fixture-org',
                'propertyName'        => 'docs',
                'onboardValue'        => 'true',
                'tokenEnvVar'         => 'FIXTURE_DISCOVERY_TOKEN',
                'excludeRepositories' => [],
            ],
        ]);
    }

    /**
     * @return array{name: string, slug: string, revision: ?string, documentationPath: ?string, error: ?array<string, mixed>}
     */
    private function entry(string $name): array
    {
        foreach ($this->manifest()['repositories'] as $entry) {
            if ($entry['name'] === $name) {
                return $entry;
            }
        }

        self::fail("The cache manifest has no entry for \"{$name}\".");
    }

    /**
     * @param list<string> $projects
     */
    private function executeCommand(
        ?string $manifest = null,
        ?SiteConfig $config = null,
        ?StubHttpTransport $transport = null,
        array $projects = [],
        ?SourceCollector $collector = null,
    ): ExitCode {
        $command = new FetchCommand(
            discovery: new DiscoveryFactory(transport: $transport ?? new StubHttpTransport([])),
            collector: $collector ?? new SourceCollector(),
        );

        return $command->execute(
            new Options(
                command : 'fetch',
                cache   : $this->cacheDirectory(),
                projects: $projects,
                manifest: $manifest,
            ),
            $config ?? SourceFixture::config(),
            new Output(
                stdout : $this->captured->stdoutWriter(),
                stderr : $this->captured->stderrWriter(),
                verbose: $this->verbose,
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(): array
    {
        $decoded = json_decode(
            json       : $this->filesystem->read($this->manifestPath()),
            associative: true,
            flags      : JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($decoded);

        return $decoded;
    }

    private function manifestPath(): string
    {
        return Path::join($this->cacheDirectory(), FetchCommand::CACHE_MANIFEST_FILE);
    }

    /**
     * @param list<array<string, mixed>> $repositories
     */
    private function writeManifest(array $repositories): string
    {
        $path = Path::join($this->tempDirectory, 'manifest.json');

        $this->filesystem->writeAtomic(
            $path,
            new Json()->encode(['schemaVersion' => 1, 'repositories' => $repositories]),
        );

        return $path;
    }
}
