<?php

declare(strict_types=1);

namespace WebwareTestIntegration\AsciidocPhp\MultiRepo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Build\BuildRunner;
use Webware\AsciidocPhp\MultiRepo\Build\ContentWriter;
use Webware\AsciidocPhp\MultiRepo\Build\DeterminismGuard;
use Webware\AsciidocPhp\MultiRepo\Build\IncrementalCache;
use Webware\AsciidocPhp\MultiRepo\Cli\Commands\BuildCommand;
use Webware\AsciidocPhp\MultiRepo\Cli\ExitCode;
use Webware\AsciidocPhp\MultiRepo\Cli\Options;
use Webware\AsciidocPhp\MultiRepo\Cli\Output;
use Webware\AsciidocPhp\MultiRepo\Config\SchemaValidator;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfigLoader;
use Webware\AsciidocPhp\MultiRepo\Render\PageRenderer;
use Webware\AsciidocPhp\MultiRepo\Site\SiteAssembler;
use Webware\AsciidocPhp\MultiRepo\Site\SiteAssembly;
use Webware\AsciidocPhp\MultiRepo\Site\StaticAssetWriter;
use Webware\AsciidocPhp\MultiRepo\Support\Json;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function array_filter;
use function array_values;
use function dirname;
use function explode;
use function file_get_contents;
use function glob;
use function implode;
use function is_array;
use function is_file;
use function str_contains;
use function strpos;
use function trim;

/**
 * T040: `build` renders every page of every version of the fixture organisation.
 *
 * The fixture set is built once. The exit code, the per-project page counts, the published tree, and
 * the contents of one rendered page are asserted on that single run, because the acceptance
 * condition for the fixture set is that the build reports the broken and Markdown-only projects and
 * exits 1 while still publishing everything that did render.
 */
#[CoversClass(BuildCommand::class)]
#[CoversClass(BuildRunner::class)]
#[CoversClass(ContentWriter::class)]
#[CoversClass(DeterminismGuard::class)]
#[CoversClass(IncrementalCache::class)]
#[CoversClass(PageRenderer::class)]
#[CoversClass(SiteAssembler::class)]
#[CoversClass(SiteAssembly::class)]
#[CoversClass(StaticAssetWriter::class)]
final class SiteBuildTest extends TestCase
{
    use TempDirectoryTrait;

    /** @var list<string> */
    private array $stdout = [];

    /** @var list<string> */
    private array $stderr = [];

    private ExitCode $exitCode;

    private string $output;

    #[Test]
    public function aPublishedPageCarriesTheShellAndTheRewrittenLink(): void
    {
        $html = (string) file_get_contents(Path::join($this->output, 'versioned', 'v1', 'index.html'));

        static::assertStringContainsString('<article class="doc">', $html);
        static::assertStringContainsString('Welcome to version 1.', $html);
        static::assertStringContainsString('href="/versioned/v1/reference/nested.html"', $html);
        static::assertStringContainsString('data-theme-mode="system"', $html);
        static::assertStringNotContainsString('stylesheet!', $html);
        static::assertStringNotContainsString('<style', $html, 'no page inlines a stylesheet');
        static::assertStringNotContainsString('C:\\', $html);

        static::assertLessThan(
            strpos($html, needle: '/assets/site.'),
            strpos($html, needle: '/assets/asciidoctor.'),
            'the base stylesheet is linked before the site overrides',
        );
    }

    #[Test]
    public function aVersionlessProjectUrlRedirectsToTheNewestVersion(): void
    {
        $versioned = (string) file_get_contents(Path::join($this->output, 'versioned', 'index.html'));

        static::assertStringContainsString('http-equiv="refresh"', $versioned);
        static::assertStringContainsString('/versioned/v2/', $versioned);
        static::assertStringContainsString('rel="canonical"', $versioned);
        static::assertStringContainsString('noindex', $versioned);
        static::assertStringContainsString('<a href="/versioned/v2/"', $versioned);

        $unversioned = (string) file_get_contents(Path::join($this->output, 'unversioned', 'index.html'));

        static::assertStringContainsString('http-equiv="refresh"', $unversioned);
        static::assertStringContainsString('/unversioned/latest/', $unversioned);
        static::assertStringContainsString('noindex', $unversioned);
    }

    #[Test]
    public function everySearchIndexUrlResolvesToAPublishedPage(): void
    {
        $json = new Json();

        $site = $json->decodeObject(
            (string) file_get_contents(Path::join($this->output, 'search-index.json')),
            'search-index.json',
        );

        $slugs = [];

        foreach ($site['projects'] as $project) {
            static::assertIsArray($project);

            $index = $project['index'] ?? '';
            static::assertIsString($index);
            static::assertTrue(
                is_file(Path::join($this->output, ...explode('/', ltrim($index, characters: '/')))),
                "the site index points at a missing file: {$index}",
            );

            $slugs[] = $project['slug'] ?? '';
        }

        static::assertSame(['mixed', 'unversioned', 'versioned'], $slugs);

        foreach (['versioned', 'unversioned', 'mixed'] as $slug) {
            $index = $json->decodeObject(
                (string) file_get_contents(Path::join($this->output, $slug, 'search-index.json')),
                "{$slug}/search-index.json",
            );

            $result = new SchemaValidator()->validate(
                $index,
                $json->decodeObject(
                    (string) file_get_contents(Path::join($this->root(), 'contracts', 'search-index.schema.json')),
                    'search-index.schema.json',
                ),
            );

            static::assertTrue($result->isValid(), $result->describe());

            foreach ($index['versions'] as $version) {
                static::assertIsArray($version);

                foreach ($version['pages'] as $page) {
                    static::assertIsArray($page);

                    $url = $page['url'] ?? '';
                    static::assertIsString($url);
                    static::assertTrue(
                        is_file(Path::join($this->output, ...explode('/', ltrim($url, characters: '/')))),
                        "the {$slug} index points at a missing page: {$url}",
                    );
                }
            }
        }
    }

    #[Test]
    public function restrictingThePublishableSetRebuildsTheAggregatePages(): void
    {
        $output = Path::join($this->tempDirectory, 'narrow');

        $exit = new BuildCommand()->execute(
            new Options(
                command : 'build',
                manifest: $this->fixtureManifest(),
                out     : $output,
                cache   : Path::join($this->tempDirectory, 'narrow-cache'),
                report  : Path::join($this->tempDirectory, 'narrow-report.json'),
                projects: ['versioned'],
            ),
            new SiteConfigLoader()->load($this->fixtureConfig()),
            new Output(
                stdout: static function (string $line): void {},
                stderr: static function (string $line): void {},
            ),
        );

        static::assertSame(ExitCode::Success, $exit);

        $landing = (string) file_get_contents(Path::join($output, 'index.html'));

        static::assertSame(1, substr_count($landing, needle: 'class="project-card"'));
        static::assertStringContainsString('href="/versioned/"', $landing);
        static::assertStringNotContainsString('mixed', $landing);

        static::assertTrue(is_file(Path::join($output, 'versioned', 'index.html')));
        static::assertFalse(is_file(Path::join($output, 'mixed', 'index.html')));

        $page = (string) file_get_contents(Path::join($output, 'versioned', 'v1', 'index.html'));

        static::assertSame(1, substr_count($page, needle: 'class="project-item'));
    }

    #[Test]
    public function theBuildPrintsExactlyOneSummaryBlockOnStandardOutput(): void
    {
        static::assertCount(1, $this->stdout, 'standard output carried more than one block');

        $summary = $this->summary();

        static::assertStringContainsString('ok: 3, partial: 1, failed: 2, skipped: 0', $summary);
        static::assertStringContainsString("output directory: {$this->output}", $summary);
        static::assertStringContainsString('report: ', $summary);
    }

    #[Test]
    public function theBuildPrintsOnePageCountPerProjectOnStandardError(): void
    {
        $stderr = implode("\n", $this->stderr);

        static::assertStringContainsString('versioned: 5 page(s) ok', $stderr);
        static::assertStringContainsString('unversioned: 1 page(s) ok', $stderr);
        static::assertStringContainsString('mixed: 2 page(s) ok', $stderr);
        static::assertStringContainsString('broken-include: 0 page(s) partial', $stderr);
        static::assertStringContainsString('markdown-only: 0 page(s) failed', $stderr);
        static::assertStringContainsString('missing-entry: 0 page(s) failed', $stderr);
        static::assertStringNotContainsString('page(s)', implode("\n", $this->stdout));
    }

    #[Test]
    public function theBuildPublishesEveryPageOfEveryVersion(): void
    {
        foreach ([
            'versioned/v1/index.html',
            'versioned/v1/guide.html',
            'versioned/v1/reference/nested.html',
            'versioned/v2/index.html',
            'versioned/v2/guide.html',
            'unversioned/latest/index.html',
            'mixed/v1/index.html',
            'mixed/v2/index.html',
        ] as $path) {
            static::assertTrue(
                is_file(Path::join($this->output, ...explode('/', $path))),
                "{$path} was not published.",
            );
        }
    }

    #[Test]
    public function theBuildWritesTheSharedAssetsOnce(): void
    {
        static::assertCount(1, $this->assetsMatching('asciidoctor.*.css'));
        static::assertCount(1, $this->assetsMatching('site.*.css'));
        static::assertCount(1, $this->assetsMatching('theme.*.js'));
        static::assertNotSame([], $this->assetsMatching('accent.*.css'));
    }

    #[Test]
    public function theFixtureSetExitsDegradedBecauseTwoProjectsFail(): void
    {
        static::assertSame(ExitCode::Degraded, $this->exitCode);
        static::assertSame(1, $this->exitCode->value);

        $errors = implode("\n", $this->errors());

        static::assertStringContainsString('render-failed', $errors);
        static::assertStringContainsString('no-asciidoc-sources', $errors);
        static::assertStringContainsString('missing-entry', $errors);
    }

    #[Test]
    public function theLandingPageCarriesOneCardPerPublishableProject(): void
    {
        $html = (string) file_get_contents(Path::join($this->output, 'index.html'));

        static::assertSame(3, substr_count($html, needle: 'class="project-card"'));
        static::assertStringContainsString('href="/mixed/"', $html);
        static::assertStringContainsString('href="/unversioned/"', $html);
        static::assertStringContainsString('href="/versioned/"', $html);
        static::assertStringNotContainsString('markdown-only', $html);
        static::assertStringNotContainsString('broken-include', $html);
    }

    #[Test]
    public function theNotFoundPageLinksHomeAndListsEveryProject(): void
    {
        $html = (string) file_get_contents(Path::join($this->output, '404.html'));

        static::assertStringContainsString('href="/"', $html);
        static::assertStringContainsString('href="/mixed/"', $html);
        static::assertStringContainsString('href="/unversioned/"', $html);
        static::assertStringContainsString('href="/versioned/"', $html);
    }

    #[Test]
    public function theOutdatedBannerLinksToTheTwinOrToTheLatestEntryPage(): void
    {
        $twin = (string) file_get_contents(Path::join($this->output, 'versioned', 'v1', 'guide.html'));

        static::assertStringContainsString('class="outdated-banner"', $twin);
        static::assertStringContainsString('The current version is <a href="/versioned/v2/guide.html">v2</a>', $twin);

        $lonely = (string) file_get_contents(
            Path::join($this->output, 'versioned', 'v1', 'reference', 'nested.html'),
        );

        static::assertStringContainsString('class="outdated-banner"', $lonely);
        static::assertStringContainsString('The current version is <a href="/versioned/v2/index.html">v2</a>', $lonely);

        $latest = (string) file_get_contents(Path::join($this->output, 'versioned', 'v2', 'index.html'));

        static::assertStringNotContainsString('outdated-banner', $latest);
    }

    #[Test]
    public function theOutputRootCarriesTheNoJekyllMarkerAndTheHostname(): void
    {
        static::assertTrue(is_file(Path::join($this->output, '.nojekyll')));
        static::assertSame('', (string) file_get_contents(Path::join($this->output, '.nojekyll')));
        static::assertTrue(is_file(Path::join($this->output, 'CNAME')));
        static::assertSame("docs.example.test\n", (string) file_get_contents(Path::join($this->output, 'CNAME')));
    }

    #[Test]
    public function theReportIsWrittenAndValidatesAgainstTheContract(): void
    {
        $report = Path::join($this->tempDirectory, 'report.json');

        static::assertTrue(is_file($report), 'the build wrote no report');

        $json    = new Json();
        $decoded = $json->decodeObject((string) file_get_contents($report), $report);

        $result = new SchemaValidator()->validate(
            $decoded,
            $json->decodeObject(
                (string) file_get_contents(Path::join($this->root(), 'contracts', 'report.schema.json')),
                'report.schema.json',
            ),
        );

        static::assertTrue($result->isValid(), $result->describe());
        static::assertSame(1, $decoded['exitCode']);
        static::assertCount(6, $decoded['projects']);
        static::assertSame(
            ['ok' => 3, 'partial' => 1, 'failed' => 2, 'skipped' => 0, 'pages' => 9, 'renderedPages' => 8],
            $decoded['totals'],
        );

        foreach ($decoded['projects'] as $project) {
            static::assertIsArray($project);
            static::assertGreaterThanOrEqual(1, $project['fetchAttempts'] ?? 0);
        }
    }

    #[Test]
    public function theSearchControlIsHiddenUntilScriptingRuns(): void
    {
        $html = (string) file_get_contents(Path::join($this->output, 'versioned', 'v1', 'index.html'));

        static::assertMatchesRegularExpression(
            '/<div class="site-search"([^>]*)>/',
            $html,
        );

        $matches = [];
        preg_match('/<div class="site-search"([^>]*)>/', $html, $matches);

        $attributes = $matches[1] ?? '';

        static::assertStringContainsString(' hidden', $attributes, 'the control is reachable before scripting runs');
        static::assertStringContainsString('data-project-slug="versioned"', $attributes);
        static::assertStringContainsString('data-project-index="/versioned/search-index.json"', $attributes);
        static::assertStringNotContainsString('disabled', $html);
    }

    #[Test]
    public function theShellLinksTheIconFontAndTheBaseStylesheetCarriesTheGlyphRules(): void
    {
        $html = (string) file_get_contents(Path::join($this->output, 'versioned', 'v1', 'index.html'));

        static::assertStringContainsString(
            '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">',
            $html,
        );

        $css = (string) file_get_contents((string) $this->assetsMatching('asciidoctor.*.css')[0]);

        foreach ([
            'note'      => '\f05a',
            'tip'       => '\f0eb',
            'warning'   => '\f071',
            'caution'   => '\f06d',
            'important' => '\f06a',
        ] as $name => $codepoint) {
            static::assertStringContainsString(".icon-{$name}::before", $css);
            static::assertStringContainsString($codepoint, $css);
        }

        static::assertStringContainsString('.admonitionblock td.icon [class^="fa icon-"]', $css);
    }

    #[Test]
    public function theSwitcherCarriesExactlyThePublishableProjects(): void
    {
        $html = (string) file_get_contents(Path::join($this->output, 'versioned', 'v1', 'index.html'));

        static::assertSame(3, substr_count($html, needle: 'class="project-item'));

        $current = [];
        preg_match('~<a href="/versioned/" aria-current="page">([^<]+)</a>~', $html, $current);

        static::assertSame('versioned', $current[1] ?? '');

        foreach (['mixed', 'unversioned'] as $slug) {
            static::assertStringContainsString("<a href=\"/{$slug}/\">{$slug}</a>", $html);
        }

        static::assertStringNotContainsString('broken-include', $html);
        static::assertStringNotContainsString('markdown-only', $html);
        static::assertStringNotContainsString('archived', $html);
    }

    protected function setUp(): void
    {
        $this->createTempDirectory();
        $this->output = Path::join($this->tempDirectory, 'site');

        $this->exitCode = new BuildCommand()->execute(
            new Options(
                command : 'build',
                manifest: $this->fixtureManifest(),
                out     : $this->output,
                cache   : Path::join($this->tempDirectory, 'cache'),
                report  : Path::join($this->tempDirectory, 'report.json'),
            ),
            new SiteConfigLoader()->load($this->fixtureConfig()),
            new Output(
                stdout: function (string $line): void {
                    $this->stdout[] = trim($line);
                },
                stderr: function (string $line): void {
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
    private function assetsMatching(string $pattern): array
    {
        $matches = glob(Path::join($this->output, 'assets', $pattern));

        return is_array($matches) ? $matches : [];
    }

    /**
     * @return list<string>
     */
    private function errors(): array
    {
        return array_values(array_filter(
            $this->stderr,
            static fn(string $line): bool => str_contains($line, '('),
        ));
    }

    private function fixtureConfig(): string
    {
        return Path::join(dirname(__DIR__, levels: 2), 'test', 'fixtures', 'site.json');
    }

    private function fixtureManifest(): string
    {
        return Path::join(dirname(__DIR__, levels: 2), 'test', 'fixtures', 'manifest.json');
    }

    private function root(): string
    {
        return dirname(__DIR__, levels: 2);
    }

    private function summary(): string
    {
        return implode("\n", $this->stdout);
    }
}
