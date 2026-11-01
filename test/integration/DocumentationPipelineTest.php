<?php

declare(strict_types=1);

namespace WebwareTestIntegration\AsciidocPhp\MultiRepo;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfig;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfigLoader;
use Webware\AsciidocPhp\MultiRepo\Discovery\DiscoveryFactory;
use Webware\AsciidocPhp\MultiRepo\Discovery\ManifestDiscovery;
use Webware\AsciidocPhp\MultiRepo\Report\ReasonCode;
use Webware\AsciidocPhp\MultiRepo\Source\PageEnumerator;
use Webware\AsciidocPhp\MultiRepo\Source\PreparedSource;
use Webware\AsciidocPhp\MultiRepo\Source\RepositoryFetcher;
use Webware\AsciidocPhp\MultiRepo\Source\RepositorySources;
use Webware\AsciidocPhp\MultiRepo\Source\SourceCollector;
use Webware\AsciidocPhp\MultiRepo\Source\SourceFormat;
use Webware\AsciidocPhp\MultiRepo\Source\VersionDetector;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function array_keys;
use function array_map;
use function count;
use function dirname;
use function file_get_contents;
use function hash;
use function is_string;
use function str_contains;
use function str_replace;

/**
 * The whole Group 2 pipeline against the checked-in fixture organisation.
 *
 * Discovery produces the project set, the fetcher resolves each repository from its local path,
 * version detection selects the versions, page enumeration lists the renderable pages, and source
 * preparation reads and classifies each one. Every stage is asserted on its own output as well as
 * on what the next stage consumed, so replacing any one of them with a stub fails this suite.
 *
 * Nothing here touches the network or git: the manifest carries local paths and the transport is a
 * trap that throws if discovery ever tries to open a connection.
 */
#[CoversClass(ManifestDiscovery::class)]
#[CoversClass(RepositoryFetcher::class)]
#[CoversClass(VersionDetector::class)]
#[CoversClass(PageEnumerator::class)]
#[CoversClass(SourceCollector::class)]
final class DocumentationPipelineTest extends TestCase
{
    use TempDirectoryTrait;

    private const EXPECTED_PROJECTS = [
        'broken-include',
        'markdown-only',
        'missing-entry',
        'mixed',
        'unversioned',
        'versioned',
    ];

    private SiteConfig $config;

    /** @var array<string, RepositorySources> */
    private array $collected = [];

    public function testABrokenIncludeStillReachesSourcePreparation(): void
    {
        $sources = $this->sources('broken-include');

        self::assertTrue($sources->isSuccessful());
        self::assertSame(['v1'], $this->labels('broken-include'));
        self::assertStringContainsString(
            'include::missing.adoc[]',
            $this->prepared('broken-include', 'docs/v1/index.adoc')->text(),
        );
    }

    public function testAMarkdownOnlyProjectIsRejectedAfterVersionDetection(): void
    {
        $sources = $this->sources('markdown-only');

        self::assertFalse($sources->isSuccessful());
        self::assertSame(ReasonCode::NoAsciidocSources, $sources->errors()[0]->reasonCode());
        self::assertSame('docs', $sources->documentationPath());
        self::assertSame(['v1'], $this->labels('markdown-only'));
        self::assertSame([], $sources->pages());
        self::assertSame([], $sources->sources());
    }

    public function testAPageInANestedDirectoryKeepsItsVersion(): void
    {
        $paths = array_map(
            static fn(PreparedSource $source): string => $source->relativePath(),
            $this->sources('versioned')->sources(),
        );

        self::assertContains('docs/v1/reference/nested.adoc', $paths);
        self::assertContains('docs/v2/guide.adoc', $paths);
    }

    public function testAPageOutsideEveryVersionDirectoryIsReportedAsAWarning(): void
    {
        $sources = $this->sources('mixed');

        self::assertSame(['v1', 'v2'], $this->labels('mixed'));
        self::assertCount(1, $sources->warnings());
        self::assertStringContainsString('docs/loose.adoc', $sources->warnings()[0]);
    }

    public function testAProjectWithoutAnEntryPageIsReportedAsMissingEntry(): void
    {
        $sources = $this->sources('missing-entry');

        self::assertSame(ReasonCode::MissingEntry, $sources->errors()[0]->reasonCode());
        self::assertSame('docs/v1', $sources->errors()[0]->path());
        self::assertStringContainsString('index.adoc, README.adoc', $sources->errors()[0]->message());
    }

    public function testDiscoverySelectsTheOnboardedFixtureProjects(): void
    {
        self::assertSame(self::EXPECTED_PROJECTS, array_keys($this->collected));
    }

    public function testEveryDiscoveredProjectResolvesToItsLocalFixture(): void
    {
        foreach (self::EXPECTED_PROJECTS as $name) {
            $fetched = $this->sources($name)->fetched();

            self::assertNotNull($fetched, "{$name} was never fetched.");
            self::assertSame($this->fixture($name), $fetched->rootPath());
            self::assertSame('docs', $fetched->documentationDirectory());
            self::assertSame(1, $fetched->attempts());
        }
    }

    public function testEveryEnumeratedPageIsPreparedAsAsciiDoc(): void
    {
        $sources = $this->sources('versioned')->sources();

        self::assertCount(5, $sources);

        foreach ($sources as $source) {
            self::assertSame(SourceFormat::AsciiDoc, $source->format());
            self::assertNotSame('', $source->text());
            self::assertSame(hash('sha256', $source->text()), $source->hash());
        }
    }

    public function testEveryFetchedProjectCarriesAContentDigestRevision(): void
    {
        foreach (self::EXPECTED_PROJECTS as $name) {
            self::assertMatchesRegularExpression(
                '/^[0-9a-f]{64}$/',
                (string) $this->sources($name)->revision(),
            );
        }
    }

    public function testTheEntryPageOfEveryVersionIsEnumerated(): void
    {
        $sets = $this->sources('versioned')->pages();

        self::assertCount(2, $sets);
        self::assertSame('docs/v1/index.adoc', $sets[0]->entry()->relativePath());
        self::assertSame('docs/v2/index.adoc', $sets[1]->entry()->relativePath());
    }

    public function testTheExcludedArchivedProjectNeverReachesThePipeline(): void
    {
        self::assertArrayNotHasKey('archived', $this->collected);
    }

    public function testThePreparedTextIsTheNormalisedFileOnDisk(): void
    {
        $source = $this->prepared('versioned', 'docs/v1/index.adoc');

        self::assertStringContainsString('= Versioned Project', $source->text());
        self::assertStringContainsString('include::guide.adoc[]', $source->text());
        self::assertFalse(str_contains($source->text(), "\r"));
        self::assertSame($this->digestOf('versioned', 'docs/v1/index.adoc'), $source->hash());
    }

    public function testTheUnversionedProjectFallsBackToTheDocumentationDirectory(): void
    {
        self::assertSame(['latest'], $this->labels('unversioned'));
        self::assertSame('docs', $this->sources('unversioned')->versions()[0]->directory());
    }

    public function testTheVersionedProjectSelectsItsVersionDirectories(): void
    {
        self::assertSame(['v1', 'v2'], $this->labels('versioned'));
    }

    public function testTheWholeFixtureSetIsAccountedFor(): void
    {
        $prepared = 0;

        foreach ($this->collected as $sources) {
            $prepared += count($sources->sources());
        }

        self::assertSame(9, $prepared);
    }

    protected function setUp(): void
    {
        $this->createTempDirectory();
        $this->config    = new SiteConfigLoader()->load($this->fixtureConfig());
        $this->collected = $this->collectEverything();
    }

    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }

    /**
     * @return array<string, RepositorySources>
     */
    private function collectEverything(): array
    {
        $factory   = new DiscoveryFactory(transport: $this->networkMustNotBeUsed());
        $collector = new SourceCollector();
        $collected = [];

        foreach ($factory->create($this->fixtureManifest())->discover($this->config) as $repository) {
            $collected[$repository->name()] = $collector->collect(
                $repository,
                $this->config,
                Path::join($this->tempDirectory, 'cache'),
            );
        }

        return $collected;
    }

    private function digestOf(string $project, string $relativePath): string
    {
        $contents = file_get_contents(Path::join($this->fixture($project), $relativePath));

        self::assertTrue(is_string($contents));

        return hash(
            'sha256',
            str_replace(
                search : ["\r\n", "\r"],
                replace: "\n",
                subject: $contents,
            ),
        );
    }

    private function fixture(string $name): string
    {
        return Path::join(
            dirname(
                path  : __DIR__,
                levels: 2,
            ),
            'test',
            'fixtures',
            'org',
            $name,
        );
    }

    private function fixtureConfig(): string
    {
        return Path::join(
            dirname(
                path  : __DIR__,
                levels: 2,
            ),
            'test',
            'fixtures',
            'site.json',
        );
    }

    private function fixtureManifest(): string
    {
        return Path::join(
            dirname(
                path  : __DIR__,
                levels: 2,
            ),
            'test',
            'fixtures',
            'manifest.json',
        );
    }

    /**
     * @return list<string>
     */
    private function labels(string $name): array
    {
        return array_map(
            static fn($version): string => $version->label(),
            $this->sources($name)->versions(),
        );
    }

    private function networkMustNotBeUsed(): ClientInterface
    {
        return new class implements ClientInterface {
            #[Override]
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw new RuntimeException(
                    'The fixture pipeline opened a connection to ' . (string) $request->getUri(),
                );
            }
        };
    }

    private function prepared(string $project, string $relativePath): PreparedSource
    {
        foreach ($this->sources($project)->sources() as $source) {
            if ($source->relativePath() === $relativePath) {
                return $source;
            }
        }

        self::fail("{$project} did not prepare {$relativePath}.");
    }

    private function sources(string $name): RepositorySources
    {
        self::assertArrayHasKey($name, $this->collected);

        return $this->collected[$name];
    }
}
