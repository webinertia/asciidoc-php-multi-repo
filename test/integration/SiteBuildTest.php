<?php

declare(strict_types=1);

namespace WebwareTestIntegration\AsciidocPhp\MultiRepo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Build\ContentWriter;
use Webware\AsciidocPhp\MultiRepo\Cli\Commands\BuildCommand;
use Webware\AsciidocPhp\MultiRepo\Cli\ExitCode;
use Webware\AsciidocPhp\MultiRepo\Cli\Options;
use Webware\AsciidocPhp\MultiRepo\Cli\Output;
use Webware\AsciidocPhp\MultiRepo\Config\SchemaValidator;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfigLoader;
use Webware\AsciidocPhp\MultiRepo\Render\PageRenderer;
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
#[CoversClass(ContentWriter::class)]
#[CoversClass(PageRenderer::class)]
final class SiteBuildTest extends TestCase
{
    use TempDirectoryTrait;

    /** @var list<string> */
    private array $stdout = [];

    /** @var list<string> */
    private array $stderr = [];

    private ExitCode $exitCode;

    private string $output;

    public function testAPublishedPageCarriesTheShellAndTheRewrittenLink(): void
    {
        $html = (string) file_get_contents(Path::join($this->output, 'versioned', 'v1', 'index.html'));

        self::assertStringContainsString('<article class="doc">', $html);
        self::assertStringContainsString('Welcome to version 1.', $html);
        self::assertStringContainsString('href="/versioned/v1/reference/nested.html"', $html);
        self::assertStringContainsString('data-theme-mode="system"', $html);
        self::assertStringNotContainsString('stylesheet!', $html);
        self::assertStringNotContainsString('C:\\', $html);
    }

    public function testAVersionlessProjectUrlRedirectsToTheNewestVersion(): void
    {
        $versioned = (string) file_get_contents(Path::join($this->output, 'versioned', 'index.html'));

        self::assertStringContainsString('http-equiv="refresh"', $versioned);
        self::assertStringContainsString('/versioned/v2/', $versioned);
        self::assertStringContainsString('rel="canonical"', $versioned);
        self::assertStringContainsString('noindex', $versioned);
        self::assertStringContainsString('<a href="/versioned/v2/"', $versioned);

        $unversioned = (string) file_get_contents(Path::join($this->output, 'unversioned', 'index.html'));

        self::assertStringContainsString('http-equiv="refresh"', $unversioned);
        self::assertStringContainsString('/unversioned/latest/', $unversioned);
        self::assertStringContainsString('noindex', $unversioned);
    }

    public function testEverySearchIndexUrlResolvesToAPublishedPage(): void
    {
        $json = new Json();

        $site = $json->decodeObject(
            (string) file_get_contents(Path::join($this->output, 'search-index.json')),
            'search-index.json',
        );

        $slugs = [];

        foreach ($site['projects'] as $project) {
            self::assertIsArray($project);

            $index = $project['index'] ?? '';
            self::assertIsString($index);
            self::assertTrue(
                is_file(Path::join($this->output, ...explode('/', ltrim($index, '/')))),
                "the site index points at a missing file: {$index}",
            );

            $slugs[] = $project['slug'] ?? '';
        }

        self::assertSame(['mixed', 'unversioned', 'versioned'], $slugs);

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

            self::assertTrue($result->isValid(), $result->describe());

            foreach ($index['versions'] as $version) {
                self::assertIsArray($version);

                foreach ($version['pages'] as $page) {
                    self::assertIsArray($page);

                    $url = $page['url'] ?? '';
                    self::assertIsString($url);
                    self::assertTrue(
                        is_file(Path::join($this->output, ...explode('/', ltrim($url, '/')))),
                        "the {$slug} index points at a missing page: {$url}",
                    );
                }
            }
        }
    }

    public function testRestrictingThePublishableSetRebuildsTheAggregatePages(): void
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

        self::assertSame(ExitCode::Success, $exit);

        $landing = (string) file_get_contents(Path::join($output, 'index.html'));

        self::assertSame(1, substr_count($landing, 'class="project-card"'));
        self::assertStringContainsString('href="/versioned/"', $landing);
        self::assertStringNotContainsString('mixed', $landing);

        self::assertTrue(is_file(Path::join($output, 'versioned', 'index.html')));
        self::assertFalse(is_file(Path::join($output, 'mixed', 'index.html')));

        $page = (string) file_get_contents(Path::join($output, 'versioned', 'v1', 'index.html'));

        self::assertSame(1, substr_count($page, 'class="project-item'));
    }

    public function testTheBuildPrintsExactlyOneSummaryBlockOnStandardOutput(): void
    {
        self::assertCount(1, $this->stdout, 'standard output carried more than one block');

        $summary = $this->summary();

        self::assertStringContainsString('ok: 3, partial: 1, failed: 2, skipped: 0', $summary);
        self::assertStringContainsString("output directory: {$this->output}", $summary);
        self::assertStringContainsString('report: ', $summary);
    }

    public function testTheBuildPrintsOnePageCountPerProjectOnStandardError(): void
    {
        $stderr = implode("\n", $this->stderr);

        self::assertStringContainsString('versioned: 5 page(s) ok', $stderr);
        self::assertStringContainsString('unversioned: 1 page(s) ok', $stderr);
        self::assertStringContainsString('mixed: 2 page(s) ok', $stderr);
        self::assertStringContainsString('broken-include: 0 page(s) partial', $stderr);
        self::assertStringContainsString('markdown-only: 0 page(s) failed', $stderr);
        self::assertStringContainsString('missing-entry: 0 page(s) failed', $stderr);
        self::assertStringNotContainsString('page(s)', implode("\n", $this->stdout));
    }

    public function testTheBuildPublishesEveryPageOfEveryVersion(): void
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
            self::assertTrue(
                is_file(Path::join($this->output, ...explode('/', $path))),
                "{$path} was not published.",
            );
        }
    }

    public function testTheBuildWritesTheSharedAssetsOnce(): void
    {
        self::assertCount(1, $this->assetsMatching('site.*.css'));
        self::assertCount(1, $this->assetsMatching('theme.*.js'));
        self::assertNotSame([], $this->assetsMatching('accent.*.css'));
    }

    public function testTheFixtureSetExitsDegradedBecauseTwoProjectsFail(): void
    {
        self::assertSame(ExitCode::Degraded, $this->exitCode);
        self::assertSame(1, $this->exitCode->value);

        $errors = implode("\n", $this->errors());

        self::assertStringContainsString('render-failed', $errors);
        self::assertStringContainsString('no-asciidoc-sources', $errors);
        self::assertStringContainsString('missing-entry', $errors);
    }

    public function testTheLandingPageCarriesOneCardPerPublishableProject(): void
    {
        $html = (string) file_get_contents(Path::join($this->output, 'index.html'));

        self::assertSame(3, substr_count($html, 'class="project-card"'));
        self::assertStringContainsString('href="/mixed/"', $html);
        self::assertStringContainsString('href="/unversioned/"', $html);
        self::assertStringContainsString('href="/versioned/"', $html);
        self::assertStringNotContainsString('markdown-only', $html);
        self::assertStringNotContainsString('broken-include', $html);
    }

    public function testTheNotFoundPageLinksHomeAndListsEveryProject(): void
    {
        $html = (string) file_get_contents(Path::join($this->output, '404.html'));

        self::assertStringContainsString('href="/"', $html);
        self::assertStringContainsString('href="/mixed/"', $html);
        self::assertStringContainsString('href="/unversioned/"', $html);
        self::assertStringContainsString('href="/versioned/"', $html);
    }

    public function testTheOutdatedBannerLinksToTheTwinOrToTheLatestEntryPage(): void
    {
        $twin = (string) file_get_contents(Path::join($this->output, 'versioned', 'v1', 'guide.html'));

        self::assertStringContainsString('class="outdated-banner"', $twin);
        self::assertStringContainsString('The current version is <a href="/versioned/v2/guide.html">v2</a>', $twin);

        $lonely = (string) file_get_contents(
            Path::join($this->output, 'versioned', 'v1', 'reference', 'nested.html'),
        );

        self::assertStringContainsString('class="outdated-banner"', $lonely);
        self::assertStringContainsString('The current version is <a href="/versioned/v2/index.html">v2</a>', $lonely);

        $latest = (string) file_get_contents(Path::join($this->output, 'versioned', 'v2', 'index.html'));

        self::assertStringNotContainsString('outdated-banner', $latest);
    }

    public function testTheOutputRootCarriesTheNoJekyllMarkerAndTheHostname(): void
    {
        self::assertTrue(is_file(Path::join($this->output, '.nojekyll')));
        self::assertSame('', (string) file_get_contents(Path::join($this->output, '.nojekyll')));
        self::assertTrue(is_file(Path::join($this->output, 'CNAME')));
        self::assertSame("docs.example.test\n", (string) file_get_contents(Path::join($this->output, 'CNAME')));
    }

    public function testTheReportIsWrittenAndValidatesAgainstTheContract(): void
    {
        $report = Path::join($this->tempDirectory, 'report.json');

        self::assertTrue(is_file($report), 'the build wrote no report');

        $json    = new Json();
        $decoded = $json->decodeObject((string) file_get_contents($report), $report);

        $result = new SchemaValidator()->validate(
            $decoded,
            $json->decodeObject(
                (string) file_get_contents(Path::join($this->root(), 'contracts', 'report.schema.json')),
                'report.schema.json',
            ),
        );

        self::assertTrue($result->isValid(), $result->describe());
        self::assertSame(1, $decoded['exitCode']);
        self::assertCount(6, $decoded['projects']);
        self::assertSame(
            ['ok' => 3, 'partial' => 1, 'failed' => 2, 'skipped' => 0, 'pages' => 9, 'renderedPages' => 8],
            $decoded['totals'],
        );

        foreach ($decoded['projects'] as $project) {
            self::assertIsArray($project);
            self::assertGreaterThanOrEqual(1, $project['fetchAttempts'] ?? 0);
        }
    }

    public function testTheSearchControlIsHiddenUntilScriptingRuns(): void
    {
        $html = (string) file_get_contents(Path::join($this->output, 'versioned', 'v1', 'index.html'));

        self::assertMatchesRegularExpression(
            '/<div class="site-search"([^>]*)>/',
            $html,
        );

        $matches = [];
        preg_match('/<div class="site-search"([^>]*)>/', $html, $matches);

        $attributes = $matches[1] ?? '';

        self::assertStringContainsString(' hidden', $attributes, 'the control is reachable before scripting runs');
        self::assertStringContainsString('data-project-slug="versioned"', $attributes);
        self::assertStringContainsString('data-project-index="/versioned/search-index.json"', $attributes);
        self::assertStringNotContainsString('disabled', $html);
    }

    public function testTheShellLinksTheIconFontAndTheStylesheetCarriesTheGlyphRules(): void
    {
        $html = (string) file_get_contents(Path::join($this->output, 'versioned', 'v1', 'index.html'));

        self::assertStringContainsString(
            '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">',
            $html,
        );

        $css = (string) file_get_contents((string) $this->assetsMatching('site.*.css')[0]);

        foreach ([
            'note'      => '\f05a',
            'tip'       => '\f0eb',
            'warning'   => '\f071',
            'caution'   => '\f06d',
            'important' => '\f06a',
        ] as $name => $codepoint) {
            self::assertStringContainsString(".icon-{$name}::before", $css);
            self::assertStringContainsString($codepoint, $css);
        }

        self::assertStringContainsString('.admonitionblock td.icon [class^="fa icon-"]', $css);
    }

    public function testTheSwitcherCarriesExactlyThePublishableProjects(): void
    {
        $html = (string) file_get_contents(Path::join($this->output, 'versioned', 'v1', 'index.html'));

        self::assertSame(3, substr_count($html, 'class="project-item'));

        $current = [];
        preg_match('~<a href="/versioned/" aria-current="page">([^<]+)</a>~', $html, $current);

        self::assertSame('versioned', $current[1] ?? '');

        foreach (['mixed', 'unversioned'] as $slug) {
            self::assertStringContainsString("<a href=\"/{$slug}/\">{$slug}</a>", $html);
        }

        self::assertStringNotContainsString('broken-include', $html);
        self::assertStringNotContainsString('markdown-only', $html);
        self::assertStringNotContainsString('archived', $html);
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
        return Path::join(dirname(__DIR__, 2), 'test', 'fixtures', 'site.json');
    }

    private function fixtureManifest(): string
    {
        return Path::join(dirname(__DIR__, 2), 'test', 'fixtures', 'manifest.json');
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function summary(): string
    {
        return implode("\n", $this->stdout);
    }
}
