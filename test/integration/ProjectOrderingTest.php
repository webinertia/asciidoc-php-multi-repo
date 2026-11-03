<?php

declare(strict_types=1);

namespace WebwareTestIntegration\AsciidocPhp\MultiRepo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Build\BuildRunner;
use Webware\AsciidocPhp\MultiRepo\Build\ContentWriter;
use Webware\AsciidocPhp\MultiRepo\Cli\Commands\BuildCommand;
use Webware\AsciidocPhp\MultiRepo\Cli\ExitCode;
use Webware\AsciidocPhp\MultiRepo\Cli\Options;
use Webware\AsciidocPhp\MultiRepo\Cli\Output;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfigLoader;
use Webware\AsciidocPhp\MultiRepo\Render\PageRenderer;
use Webware\AsciidocPhp\MultiRepo\Site\LandingPageBuilder;
use Webware\AsciidocPhp\MultiRepo\Source\RepositoryFetcher;
use Webware\AsciidocPhp\MultiRepo\Source\SourceCollector;
use Webware\AsciidocPhp\MultiRepo\Support\Json;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function array_column;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function is_file;
use function preg_match;
use function preg_match_all;
use function sprintf;
use function str_contains;
use function strlen;
use function substr;
use function substr_count;
use function trim;

/**
 * The order in which projects and versions are published, and the recording of what happened.
 *
 * The fixture organisation behind this test is built for observation rather than for content: ten
 * repositories whose labels sort differently as strings and as numbers, whose configured `order`
 * disagrees with their slug order, one project that is archived, one that has no documentation
 * directory, one whose entry page cannot be rendered, and one whose `docs/` holds a directory that
 * is not a version. Every line the build says about them is asserted here, because the report and
 * the terminal output are the only place a failure is visible.
 */
#[CoversClass(BuildCommand::class)]
#[CoversClass(BuildRunner::class)]
#[CoversClass(ContentWriter::class)]
#[CoversClass(LandingPageBuilder::class)]
#[CoversClass(PageRenderer::class)]
final class ProjectOrderingTest extends TestCase
{
    use TempDirectoryTrait;

    /** @var list<string> */
    private array $stdout = [];

    /** @var list<string> */
    private array $stderr = [];

    private ExitCode $exitCode;

    private string $output;

    private string $report;

    #[Test]
    public function aLabelThatStartsWithANumberIsStillOrderedByThatNumber(): void
    {
        static::assertSame(['v1', 'beta', 'v2beta'], $this->projectReport('narrow')['versions']);
    }

    #[Test]
    public function aProjectWhoseFetchFailsRecordsNoFetchAttempts(): void
    {
        $manifest = [
            'schemaVersion' => 1,
            'repositories'  => [
                [
                    'name'          => 'remote',
                    'slug'          => 'remote',
                    'defaultBranch' => 'main',
                    'archived'      => false,
                ],
            ],
        ];

        $path = Path::join($this->tempDirectory, 'remote-manifest.json');
        file_put_contents($path, new Json()->encode($manifest));

        $report = Path::join($this->tempDirectory, 'remote-report.json');

        $exitCode = new BuildCommand(
            runner: new BuildRunner(collector: new SourceCollector(
                fetcher: new RepositoryFetcher(
                    remoteUrl: static fn(
                        string $organization,
                        string $repository,
                    ): string => "file:///nonexistent/{$repository}.git",
                ),
            )),
        )->execute(
            new Options(
                command : 'build',
                manifest: $path,
                out     : Path::join($this->tempDirectory, 'remote-site'),
                cache   : Path::join($this->tempDirectory, 'remote-cache'),
                report  : $report,
            ),
            new SiteConfigLoader()->load($this->fixtureConfig()),
            new Output(stderr: static function (string $line): void {}),
        );

        static::assertSame(ExitCode::Degraded, $exitCode);

        $written  = new Json()->decodeObject((string) file_get_contents($report), $report);
        $projects = $written['projects'] ?? [];
        static::assertIsArray($projects);

        $byRepository = array_column($projects, null, 'repository');
        $result       = $byRepository['remote'] ?? null;

        static::assertIsArray($result);
        static::assertSame('failed', $result['status']);
        static::assertSame(0, $result['fetchAttempts']);
    }

    #[Test]
    public function numericLabelsOutrankTheLabelsThatCarryNoNumber(): void
    {
        static::assertSame(['v3', 'v2', 'v1', 'v2beta', 'zzz'], $this->projectReport('sorting')['versions']);
    }

    #[Test]
    public function theBuildAnnouncesEveryPhaseOfEveryProject(): void
    {
        $stderr = $this->stderr();

        static::assertStringContainsString('alpha: collecting sources', $stderr);
        static::assertStringContainsString('alpha: rendered alpha/v1/index.html', $stderr);
        static::assertStringContainsString('alpha: 5 page(s) ok', $stderr);
        static::assertStringContainsString('alpha: wrote pages', $stderr);

        static::assertStringContainsString('noindex: 2 page(s) ok', $stderr);
        static::assertStringContainsString('zeta: 1 page(s) ok', $stderr);
        static::assertStringContainsString('renderloop: 1 page(s) partial', $stderr);
        static::assertStringContainsString('missingrepo: 0 page(s) failed', $stderr);
        static::assertStringContainsString('nodocs: 0 page(s) failed', $stderr);
    }

    #[Test]
    public function theBuildKeepsGoingWhenAPageCannotBeRendered(): void
    {
        static::assertStringContainsString(
            'warning: docs/v1/index.adoc: The include target "missing-partial.adoc" does not exist. (render-failed)',
            $this->stderr(),
        );

        static::assertFalse(is_file(Path::join($this->output, 'renderloop', 'v1', 'index.html')));
        static::assertTrue(is_file(Path::join($this->output, 'renderloop', 'v1', 'zzz.html')));
        static::assertTrue(is_file(Path::join($this->output, 'renderloop', 'index.html')));
    }

    #[Test]
    public function theBuildNamesTheMissingDocumentationDirectory(): void
    {
        $stderr = $this->stderr();

        static::assertStringContainsString(
            'warning: docs: missingrepo: no documentation directory at docs/. (no-asciidoc-sources)',
            $stderr,
        );
        static::assertStringContainsString(
            'warning: docs: nodocs: no documentation directory at docs/. (no-asciidoc-sources)',
            $stderr,
        );
    }

    #[Test]
    public function theBuildReportsNoTimingUnlessAskedForIt(): void
    {
        $quiet = $this->build(
            verbose: false,
            timings: false,
        );

        static::assertStringNotContainsString('build: ', implode("\n", $quiet));
    }

    #[Test]
    public function theBuildTimesItselfWhenAsked(): void
    {
        $timings = [];

        foreach ($this->stderr as $line) {
            if (! str_contains($line, 'build: ')) {
                continue;
            }

            $timings[] = $line;
        }

        static::assertCount(1, $timings);
        static::assertMatchesRegularExpression('/^build: \d+\.\d{3}s$/', $timings[0]);

        $seconds = (float) substr($timings[0], strlen('build: '), -1);

        static::assertGreaterThan(0.0, $seconds);
        static::assertLessThan(60.0, $seconds);
    }

    #[Test]
    public function theBuildWarnsAboutAnArchivedProjectAndAnIgnoredPath(): void
    {
        $stderr = $this->stderr();

        static::assertStringContainsString('warning: archived: archived, skipped', $stderr);
        static::assertStringContainsString(
            'warning: stray: 1 path(s) outside a version directory were ignored: docs/draft-v1/index.adoc',
            $stderr,
        );
    }

    #[Test]
    public function theLandingPageAgreesWithTheRedirectOnTheLatestVersion(): void
    {
        static::assertSame(
            ['Latest version: v1', 'Latest version: v10'],
            $this->cardVersions(['zeta', 'alpha']),
        );
    }

    #[Test]
    public function theLandingPageOrdersProjectsByConfiguredOrderThenBySlug(): void
    {
        static::assertSame(
            [
                '/zeta/',
                '/alpha/',
                '/narrow/',
                '/noindex/',
                '/prefix/',
                '/renderloop/',
                '/sorting/',
                '/stray/',
                '/twoindex/',
            ],
            $this->cardLinks(),
        );
    }

    #[Test]
    public function theLandingPagePrefersTheConfiguredDescriptionOverThePageBody(): void
    {
        static::assertSame(
            [
                'zeta'  => 'Zeta handles shipping.',
                'alpha' => 'Alpha handles billing.',
            ],
            $this->cardDescriptions(['zeta', 'alpha']),
        );

        $landing = (string) file_get_contents(Path::join($this->output, 'index.html'));

        static::assertSame(9, substr_count($landing, needle: 'class="card-description"'));
    }

    #[Test]
    public function theReportCountsEveryProjectAndEveryPage(): void
    {
        $report = $this->report();

        static::assertSame(
            [
                'ok'            => 8,
                'partial'       => 1,
                'failed'        => 2,
                'skipped'       => 1,
                'pages'         => 23,
                'renderedPages' => 22,
            ],
            $report['totals'],
        );

        static::assertCount(12, $report['projects']);
        static::assertSame(ExitCode::Degraded, $this->exitCode);
    }

    #[Test]
    public function theReportRecordsTheFetchAttemptsOfEachProject(): void
    {
        static::assertSame(0, $this->projectReport('archived')['fetchAttempts']);

        foreach (['alpha', 'zeta', 'noindex', 'twoindex', 'stray', 'sorting'] as $repository) {
            static::assertSame(1, $this->projectReport($repository)['fetchAttempts'], $repository);
        }
    }

    #[Test]
    public function theReportRecordsTheSkipReasonOfAnArchivedProject(): void
    {
        $archived = $this->projectReport('archived');

        static::assertSame('skipped', $archived['status']);
        static::assertCount(1, $archived['warnings']);

        $warning = $archived['warnings'][0];
        static::assertIsArray($warning);
        static::assertSame('archived', $warning['reasonCode']);
        static::assertSame('archived', $warning['message']);
    }

    #[Test]
    public function theVersionlessRedirectTargetsTheHighestNumberedVersion(): void
    {
        $redirect = (string) file_get_contents(Path::join($this->output, 'alpha', 'index.html'));

        static::assertStringContainsString('<title>Redirecting to v10</title>', $redirect);
        static::assertStringContainsString('content="0; url=/alpha/v10/"', $redirect);
        static::assertStringContainsString('<a href="/alpha/v10/">/alpha/v10/</a>', $redirect);
    }

    #[Test]
    public function theVersionSelectorListsTheVersionsInSortedOrder(): void
    {
        $matches = [];

        preg_match_all(
            '~<li class="version-item[^"]*"><a href="/alpha/([^"/]+)/index\.html"~',
            (string) file_get_contents(Path::join($this->output, 'alpha', 'v1', 'index.html')),
            $matches,
        );

        static::assertSame(['v10', 'v1', 'beta', 'releasev3', 'v2beta'], $matches[1] ?? []);
        static::assertStringContainsString(
            '<li class="version-item version-latest"><a href="/alpha/v10/index.html">',
            (string) file_get_contents(Path::join($this->output, 'alpha', 'v1', 'index.html')),
        );
    }

    #[Test]
    public function theVersionSelectorUsesTheSameOrderAsTheReport(): void
    {
        $matches = [];

        preg_match_all(
            '~<li class="version-item[^"]*"><a href="/sorting/([^"/]+)/index\.html"~',
            (string) file_get_contents(Path::join($this->output, 'sorting', 'v1', 'index.html')),
            $matches,
        );

        static::assertSame(['v3', 'v2', 'v1', 'v2beta', 'zzz'], $matches[1] ?? []);
    }

    #[Test]
    public function versionsAreSortedByNumberThenByLabel(): void
    {
        static::assertSame(['v10', 'v1', 'beta', 'releasev3', 'v2beta'], $this->projectReport('alpha')['versions']);
    }

    protected function setUp(): void
    {
        $this->createTempDirectory();
        $this->output = Path::join($this->tempDirectory, 'site');
        $this->report = Path::join($this->tempDirectory, 'report.json');

        $this->exitCode = new BuildCommand()->execute(
            new Options(
                command : 'build',
                manifest: $this->fixtureManifest(),
                out     : $this->output,
                cache   : Path::join($this->tempDirectory, 'cache'),
                report  : $this->report,
            ),
            new SiteConfigLoader()->load($this->fixtureConfig()),
            new Output(
                verbose: true,
                timings: true,
                stdout : function (string $line): void {
                    $this->stdout[] = trim($line);
                },
                stderr : function (string $line): void {
                    $this->stderr[] = trim($line);
                },
            ),
        );
    }

    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }

    /**
     * @return list<string>
     */
    private function build(bool $verbose, bool $timings): array
    {
        $lines = [];

        new BuildCommand()->execute(
            new Options(
                command : 'build',
                manifest: $this->fixtureManifest(),
                out     : Path::join($this->tempDirectory, 'second'),
                cache   : Path::join($this->tempDirectory, 'second-cache'),
                report  : Path::join($this->tempDirectory, 'second-report.json'),
            ),
            new SiteConfigLoader()->load($this->fixtureConfig()),
            new Output(
                verbose: $verbose,
                timings: $timings,
                stdout : static function (string $line): void {},
                stderr : static function (string $line) use (&$lines): void {
                    $lines[] = trim($line);
                },
            ),
        );

        return $lines;
    }

    /**
     * @param  list<string> $slugs
     * @return array<string, string>
     */
    private function cardDescriptions(array $slugs): array
    {
        $descriptions = [];

        foreach ($slugs as $slug) {
            $matches = [];
            preg_match(
                sprintf('~href="/%s/".*?<p class="card-description">([^<]*)</p>~s', $slug),
                (string) file_get_contents(Path::join($this->output, 'index.html')),
                $matches,
            );

            $descriptions[$slug] = trim($matches[1] ?? '');
        }

        return $descriptions;
    }

    /**
     * @return list<string>
     */
    private function cardLinks(): array
    {
        $matches = [];

        preg_match_all(
            '~<h2 class="card-title"><a href="([^"]+)"~',
            (string) file_get_contents(Path::join($this->output, 'index.html')),
            $matches,
        );

        return $matches[1] ?? [];
    }

    /**
     * @param  list<string> $slugs
     * @return list<string>
     */
    private function cardVersions(array $slugs): array
    {
        $versions = [];

        foreach ($slugs as $slug) {
            $matches = [];
            preg_match(
                sprintf('~href="/%s/".*?<p class="card-version">([^<]*)</p>~s', $slug),
                (string) file_get_contents(Path::join($this->output, 'index.html')),
                $matches,
            );

            $versions[] = trim($matches[1] ?? '');
        }

        return $versions;
    }

    private function fixtureConfig(): string
    {
        return Path::join(dirname(__DIR__, levels: 2), 'test', 'fixtures', 'ordering', 'site.json');
    }

    private function fixtureManifest(): string
    {
        return Path::join(dirname(__DIR__, levels: 2), 'test', 'fixtures', 'ordering', 'manifest.json');
    }

    /**
     * @return array<string, mixed>
     */
    private function projectReport(string $repository): array
    {
        foreach ($this->report()['projects'] as $project) {
            static::assertIsArray($project);

            if (($project['repository'] ?? '') === $repository) {
                return $project;
            }
        }

        static::fail("The report carries no entry for {$repository}.");
    }

    /**
     * @return array<string, mixed>
     */
    private function report(): array
    {
        return new Json()->decodeObject((string) file_get_contents($this->report), $this->report);
    }

    private function stderr(): string
    {
        return implode("\n", $this->stderr);
    }
}
