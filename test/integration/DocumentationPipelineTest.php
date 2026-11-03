<?php

declare(strict_types=1);

namespace WebwareTestIntegration\AsciidocPhp\MultiRepo;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
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

    #[Test]
    public function aBrokenIncludeStillReachesSourcePreparation(): void
    {
        $sources = $this->sources('broken-include');

        static::assertTrue($sources->isSuccessful());
        static::assertSame(['v1'], $this->labels('broken-include'));
        static::assertStringContainsString(
            'include::missing.adoc[]',
            $this->prepared('broken-include', 'docs/v1/index.adoc')->text(),
        );
    }

    #[Test]
    public function aMarkdownOnlyProjectIsRejectedAfterVersionDetection(): void
    {
        $sources = $this->sources('markdown-only');

        static::assertFalse($sources->isSuccessful());
        static::assertSame(ReasonCode::NoAsciidocSources, $sources->errors()[0]->reasonCode());
        static::assertSame('docs', $sources->documentationPath());
        static::assertSame(['v1'], $this->labels('markdown-only'));
        static::assertSame([], $sources->pages());
        static::assertSame([], $sources->sources());
    }

    #[Test]
    public function aPageInANestedDirectoryKeepsItsVersion(): void
    {
        $paths = array_map(
            static fn(PreparedSource $source): string => $source->relativePath(),
            $this->sources('versioned')->sources(),
        );

        static::assertContains('docs/v1/reference/nested.adoc', $paths);
        static::assertContains('docs/v2/guide.adoc', $paths);
    }

    #[Test]
    public function aPageOutsideEveryVersionDirectoryIsReportedAsAWarning(): void
    {
        $sources = $this->sources('mixed');

        static::assertSame(['v1', 'v2'], $this->labels('mixed'));
        static::assertCount(1, $sources->warnings());
        static::assertStringContainsString('docs/loose.adoc', $sources->warnings()[0]);
    }

    #[Test]
    public function aProjectWithoutAnEntryPageIsReportedAsMissingEntry(): void
    {
        $sources = $this->sources('missing-entry');

        static::assertSame(ReasonCode::MissingEntry, $sources->errors()[0]->reasonCode());
        static::assertSame('docs/v1', $sources->errors()[0]->path());
        static::assertStringContainsString('index.adoc, README.adoc', $sources->errors()[0]->message());
    }

    #[Test]
    public function discoverySelectsTheOnboardedFixtureProjects(): void
    {
        static::assertSame(self::EXPECTED_PROJECTS, array_keys($this->collected));
    }

    #[Test]
    public function everyDiscoveredProjectResolvesToItsLocalFixture(): void
    {
        foreach (self::EXPECTED_PROJECTS as $name) {
            $fetched = $this->sources($name)->fetched();

            static::assertNotNull($fetched, "{$name} was never fetched.");
            static::assertSame($this->fixture($name), $fetched->rootPath());
            static::assertSame('docs', $fetched->documentationDirectory());
            static::assertSame(1, $fetched->attempts());
        }
    }

    #[Test]
    public function everyEnumeratedPageIsPreparedAsAsciiDoc(): void
    {
        $sources = $this->sources('versioned')->sources();

        static::assertCount(5, $sources);

        foreach ($sources as $source) {
            static::assertSame(SourceFormat::AsciiDoc, $source->format());
            static::assertNotSame('', $source->text());
            static::assertSame(hash('sha256', $source->text()), $source->hash());
        }
    }

    #[Test]
    public function everyFetchedProjectCarriesAContentDigestRevision(): void
    {
        foreach (self::EXPECTED_PROJECTS as $name) {
            static::assertMatchesRegularExpression(
                '/^[0-9a-f]{64}$/',
                (string) $this->sources($name)->revision(),
            );
        }
    }

    #[Test]
    public function theEntryPageOfEveryVersionIsEnumerated(): void
    {
        $sets = $this->sources('versioned')->pages();

        static::assertCount(2, $sets);
        static::assertSame('docs/v1/index.adoc', $sets[0]->entry()->relativePath());
        static::assertSame('docs/v2/index.adoc', $sets[1]->entry()->relativePath());
    }

    #[Test]
    public function theExcludedArchivedProjectNeverReachesThePipeline(): void
    {
        static::assertArrayNotHasKey('archived', $this->collected);
    }

    #[Test]
    public function thePreparedTextIsTheNormalisedFileOnDisk(): void
    {
        $source = $this->prepared('versioned', 'docs/v1/index.adoc');

        static::assertStringContainsString('= Versioned Project', $source->text());
        static::assertStringContainsString('include::guide.adoc[]', $source->text());
        static::assertFalse(str_contains($source->text(), "\r"));
        static::assertSame($this->digestOf('versioned', 'docs/v1/index.adoc'), $source->hash());
    }

    #[Test]
    public function theUnversionedProjectFallsBackToTheDocumentationDirectory(): void
    {
        static::assertSame(['latest'], $this->labels('unversioned'));
        static::assertSame('docs', $this->sources('unversioned')->versions()[0]->directory());
    }

    #[Test]
    public function theVersionedProjectSelectsItsVersionDirectories(): void
    {
        static::assertSame(['v1', 'v2'], $this->labels('versioned'));
    }

    #[Test]
    public function theWholeFixtureSetIsAccountedFor(): void
    {
        $prepared = 0;

        foreach ($this->collected as $sources) {
            $prepared += count($sources->sources());
        }

        static::assertSame(9, $prepared);
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

        static::assertTrue(is_string($contents));

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
        static::assertArrayHasKey($name, $this->collected);

        return $this->collected[$name];
    }
}
