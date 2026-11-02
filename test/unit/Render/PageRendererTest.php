<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Render;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfig;
use Webware\AsciidocPhp\MultiRepo\Render\PageRenderer;
use Webware\AsciidocPhp\MultiRepo\Render\RenderedPage;
use Webware\AsciidocPhp\MultiRepo\Report\ReasonCode;
use Webware\AsciidocPhp\MultiRepo\Source\DocPage;
use Webware\AsciidocPhp\MultiRepo\Source\PreparedSource;
use Webware\AsciidocPhp\MultiRepo\Source\SourceException;
use Webware\AsciidocPhp\MultiRepo\Source\SourceFormat;
use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use WebwareTest\AsciidocPhp\MultiRepo\Support\SiteFixtureTrait;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function array_map;
use function array_replace_recursive;
use function date;
use function file_get_contents;
use function file_put_contents;
use function hash;
use function hash_file;
use function is_array;
use function json_decode;
use function mb_strlen;
use function str_contains;
use function str_repeat;
use function str_replace;
use function substr_count;
use function trim;

use const JSON_THROW_ON_ERROR;

/**
 * T039: the renderer composes the converter, the policy, the table of contents, the link rewriter,
 * the theme, and the shell.
 *
 * The fixture entry page is the acceptance subject. It is rendered from the checked-in source, so
 * the title, the table of contents, the published path, and the absence of a build date and of an
 * absolute filesystem path are all asserted against real output.
 */
#[CoversClass(PageRenderer::class)]
#[CoversClass(RenderedPage::class)]
#[CoversMethod(PageRenderer::class, 'render')]
#[CoversMethod(PageRenderer::class, 'outputPath')]
#[CoversMethod(RenderedPage::class, 'headings')]
#[CoversMethod(RenderedPage::class, 'html')]
#[CoversMethod(RenderedPage::class, 'outputPath')]
#[CoversMethod(RenderedPage::class, 'searchDocument')]
#[CoversMethod(RenderedPage::class, 'searchText')]
#[CoversMethod(RenderedPage::class, 'title')]
#[CoversMethod(RenderedPage::class, 'toc')]
#[CoversMethod(RenderedPage::class, 'urlPath')]
final class PageRendererTest extends TestCase
{
    use SiteFixtureTrait;
    use TempDirectoryTrait;

    public function testABlankProjectTitleOverrideFallsBackToTheProjectName(): void
    {
        $page = $this->renderFromTemp(
            "= Page\n\nText.\n",
            $this->configWithOverrides(['projects' => ['versioned' => ['title' => '   ']]]),
        );

        self::assertStringContainsString('<p class="project-name">Versioned</p>', $page->html());
    }

    public function testABrokenIncludeFailsWithRenderFailedAndTheSourcePath(): void
    {
        try {
            $this->render('docs/v1/index.adoc', repository: 'broken-include');
            self::fail('A broken include should fail the page.');
        } catch (SourceException $exception) {
            $error = $exception->error();

            self::assertSame(ReasonCode::RenderFailed, $error->reasonCode());
            self::assertSame('docs/v1/index.adoc', $error->path());
            self::assertSame('v1', $error->version());
            self::assertStringContainsString('missing.adoc', $error->message());
            self::assertNotNull($error->correctiveAction());
        }
    }

    public function testACrlfSourceRendersIdenticallyToItsLfTwin(): void
    {
        $lf = "= Twin Page\n\nIntro paragraph.\n\n== Section\n\nBody text.\n\n* one\n* two\n";

        $fromLf   = $this->renderFromTemp($lf);
        $fromCrlf = $this->renderFromTemp(str_replace(search: "\n", replace: "\r\n", subject: $lf));

        self::assertSame($fromLf->html(), $fromCrlf->html());
    }

    public function testAHumanisedNameFillsInWhenTheDocumentHasNoTitle(): void
    {
        $page = $this->renderFromTemp("Just a paragraph.\n", relativePath: 'docs/v1/UPPER-CASE_PAGE.adoc');

        self::assertSame('Upper Case Page', $page->title());
    }

    public function testAnIncludeTargetIsTrimmedBeforeItIsResolved(): void
    {
        try {
            $this->renderFromTemp("= Page\n\ninclude::   missing.adoc   []\n");
            self::fail('A padded include of a missing file should fail the page.');
        } catch (SourceException $exception) {
            self::assertSame(
                'The include target "missing.adoc" does not exist.',
                $exception->error()->message(),
            );
        }
    }

    public function testAnIncludeThatNamesADirectoryIsNotAMissingFile(): void
    {
        $directory = $this->createTempDirectory();
        new Filesystem()->ensureDirectory(Path::join($directory, 'partials'));

        $page = $this->renderFromTemp("= Page\n\ninclude::partials[]\n", directory: $directory);

        self::assertSame('Page', $page->title());
    }

    public function testAProjectTitleOverrideIsUsedWhenItIsNotBlank(): void
    {
        $page = $this->renderFromTemp(
            "= Page\n\nText.\n",
            $this->configWithOverrides(['projects' => ['versioned' => ['title' => 'Custom Widgets']]]),
        );

        self::assertStringContainsString('<p class="project-name">Custom Widgets</p>', $page->html());
    }

    public function testARemoteIncludeDoesNotStopTheCheckForTheNextOne(): void
    {
        try {
            $this->renderFromTemp(
                "= Page\n\ninclude::https://example.com/guide.adoc[]\n\ninclude::missing.adoc[]\n",
            );
            self::fail('The second include is missing and should fail the page.');
        } catch (SourceException $exception) {
            self::assertSame(
                'The include target "missing.adoc" does not exist.',
                $exception->error()->message(),
            );
        }
    }

    public function testARemoteIncludeIsNotTreatedAsAMissingFile(): void
    {
        $page = $this->renderFromTemp("= Page\n\ninclude::https://example.com/guide.adoc[]\n");

        self::assertSame('Page', $page->title());
    }

    public function testASearchLimitOfZeroKeepsTheWholeText(): void
    {
        $page = $this->renderFromTemp(
            "= Long\n\n" . str_repeat('word ', 900) . "\n",
            $this->configWithOverrides(['render' => ['searchExtractLimit' => 0]]),
        );

        self::assertGreaterThan(2000, mb_strlen($page->searchText()));
    }

    public function testASubdirectoryBasePathIsAppliedToEveryAssetAndLink(): void
    {
        $page = $this->render('docs/v1/index.adoc', basePath: '/docs/');
        $html = $page->html();

        self::assertStringContainsString('<link rel="stylesheet" href="/docs/assets/site.', $html);
        self::assertStringContainsString('<link rel="stylesheet" href="/docs/assets/accent.', $html);
        self::assertStringContainsString('<script src="/docs/assets/theme.', $html);
        self::assertStringContainsString(
            '<link rel="canonical" href="https://docs.example.test/docs/versioned/v1/index.html">',
            $html,
        );
        self::assertStringContainsString('href="/docs/versioned/v1/reference/nested.html"', $html);
        self::assertStringNotContainsString('href="/versioned', $html);
    }

    public function testEveryDocumentedSafeModeIsAccepted(): void
    {
        $repository = $this->fixtureRepository('versioned');
        $version    = $this->fixtureVersion();
        $source     = $this->source('versioned', 'docs/v1/index.adoc');

        foreach (['unsafe', 'server'] as $mode) {
            $page = new PageRenderer()->render(
                $this->configWithSafeMode($mode),
                $repository,
                $version,
                $source,
            );

            self::assertStringContainsString(
                'This is the version 1 guide.',
                $page->html(),
                "Safe mode \"{$mode}\" should still resolve the include.",
            );
            self::assertSame('Versioned Project', $page->title());
        }
    }

    public function testEveryIncludeIsCheckedNotJustTheFirst(): void
    {
        $directory = $this->createTempDirectory();
        file_put_contents(Path::join($directory, 'guide.adoc'), "== Guide\n\nIncluded text.\n");

        try {
            $this->renderFromTemp(
                "= Page\n\ninclude::guide.adoc[]\n\ninclude::missing.adoc[]\n",
                directory: $directory,
            );
            self::fail('The second include is missing and should fail the page.');
        } catch (SourceException $exception) {
            self::assertSame(
                'The include target "missing.adoc" does not exist.',
                $exception->error()->message(),
            );
        }
    }

    public function testTheCanonicalUrlKeepsASingleSlashWhenTheBaseUrlEndsWithOne(): void
    {
        $page = $this->renderFromTemp(
            "= Page\n\nText.\n",
            $this->configWithOverrides(['site' => ['baseUrl' => 'https://docs.example.test/']]),
        );

        self::assertStringContainsString(
            '<link rel="canonical" href="https://docs.example.test/versioned/v1/page.html">',
            $page->html(),
        );
    }

    public function testTheConverterDoesNotEmitItsOwnHeaderAndFooter(): void
    {
        $html = $this->render('docs/v1/index.adoc')->html();

        self::assertSame(1, substr_count($html, '<!DOCTYPE html>'));
        self::assertStringNotContainsString('<div id="header">', $html);
        self::assertStringNotContainsString('<div id="footer">', $html);
    }

    public function testTheEntryPageRendersWithTheExpectedTitleAndPath(): void
    {
        $page = $this->render('docs/v1/index.adoc');

        self::assertSame('Versioned Project', $page->title());
        self::assertSame('versioned/v1/index.html', $page->outputPath());
        self::assertSame('/versioned/v1/index.html', $page->urlPath());
        self::assertStringContainsString(
            '<title>Versioned Project | Versioned | Fixture Site</title>',
            $page->html(),
        );
        self::assertStringContainsString('Welcome to version 1.', $page->html());
    }

    public function testTheIncludedSectionIsRenderedIntoThePageUnderSafeMode(): void
    {
        $page = new PageRenderer()->render(
            $this->configWithSafeMode('safe'),
            $this->fixtureRepository('versioned'),
            $this->fixtureVersion(),
            $this->source('versioned', 'docs/v1/index.adoc'),
        );

        self::assertStringContainsString('<h2 id="_guide">Guide</h2>', $page->html());
        self::assertStringContainsString('This is the version 1 guide.', $page->html());
        self::assertSame(['Guide'], $page->headings());
    }

    public function testTheOutputPathIsPartOfThePublicSurface(): void
    {
        $renderer = new PageRenderer();

        self::assertSame(
            'versioned/v1/index.html',
            $renderer->outputPath(
                $this->fixtureRepository('versioned'),
                $this->fixtureVersion(),
                $this->source('versioned', 'docs/v1/index.adoc'),
            ),
        );
    }

    public function testThePageCarriesNoBuildDateAndNoAbsolutePath(): void
    {
        $page = $this->render('docs/v1/index.adoc');
        $html = $page->html();

        self::assertFalse(str_contains($html, (string) date('Y')));
        self::assertStringNotContainsString($this->fixturePath('org/versioned'), $html);
        self::assertStringNotContainsString('C:\\', $html);
        self::assertStringNotContainsString('file://', $html);
        self::assertStringNotContainsString('docdate', $html);
        self::assertStringNotContainsString('doctime', $html);
        self::assertStringNotContainsString('localdate', $html);
    }

    public function testTheSafeModeIsReadWithoutRegardToCase(): void
    {
        $repository = $this->fixtureRepository('versioned');
        $version    = $this->fixtureVersion();
        $source     = $this->source('versioned', 'docs/v1/index.adoc');

        $safe = new PageRenderer()->render(
            $this->configWithOverrides(['render' => ['safeMode' => 'safe']]),
            $repository,
            $version,
            $source,
        );

        $secure = new PageRenderer()->render(
            $this->configWithOverrides(['render' => ['safeMode' => 'SECURE']]),
            $repository,
            $version,
            $source,
        );

        self::assertStringContainsString('This is the version 1 guide.', $safe->html());
        self::assertStringNotContainsString('This is the version 1 guide.', $secure->html());
    }

    public function testTheSearchTextHasNoSurroundingWhitespace(): void
    {
        $text = $this->render('docs/v1/index.adoc')->searchText();

        self::assertNotSame('', $text);
        self::assertSame(trim($text), $text);
    }

    public function testTheSearchTextIsATruncatedPrefixOfTheWholeText(): void
    {
        $page = $this->renderFromTemp(
            "= Accents\n\nIntro text.\n\n" . str_repeat("\u{65e5}", 400) . "\n",
            $this->configWithOverrides(['render' => ['searchExtractLimit' => 200]]),
        );

        self::assertSame(200, mb_strlen($page->searchText()));
        self::assertStringStartsWith('Intro text.', $page->searchText());
    }

    public function testTheSearchTextIsPlain(): void
    {
        $page = $this->render('docs/v1/index.adoc');

        self::assertStringNotContainsString('<', $page->searchText());
        self::assertStringContainsString('Welcome to version 1.', $page->searchText());
        self::assertSame(
            ['title' => 'Versioned Project', 'text' => $page->searchText(), 'headings' => $page->headings()],
            $page->searchDocument(),
        );
    }

    public function testTheSearchTextIsTruncatedOnCharacterBoundaries(): void
    {
        $page = $this->renderFromTemp(
            "= Accents\n\n" . str_repeat("\u{e9}", 400) . "\n",
            $this->configWithOverrides(['render' => ['searchExtractLimit' => 200]]),
        );

        self::assertSame(200, mb_strlen($page->searchText()));
    }

    public function testTheSearchTextStopsAtTheConfiguredLimit(): void
    {
        $directory = $this->createTempDirectory();
        $path      = Path::join($directory, 'long.adoc');

        file_put_contents($path, "= Long Page\n\n" . str_repeat('word ', 900) . "\n");

        $page = new PageRenderer()->render(
            $this->fixtureConfig(),
            $this->fixtureRepository('versioned'),
            $this->fixtureVersion(),
            new PreparedSource(
                new DocPage('v1', 'docs/v1/long.adoc', $path),
                SourceFormat::AsciiDoc,
                (string) file_get_contents($path),
                (string) hash_file('sha256', $path),
            ),
        );

        $limit = $this->fixtureConfig()->render()->searchExtractLimit();

        self::assertSame(2000, $limit);
        self::assertSame($limit, mb_strlen($page->searchText()));
        self::assertStringEndsWith('word', trim($page->searchText()));
    }

    public function testTheSourceLinkPointsAtTheRepository(): void
    {
        $page = $this->render('docs/v1/index.adoc');

        self::assertStringContainsString(
            'href="https://github.com/fixture-org/versioned/blob/main/docs/v1/index.adoc"',
            $page->html(),
        );
    }

    public function testTheTableOfContentsComesFromTheDocumentSections(): void
    {
        $page = $this->render('docs/v1/guide.adoc');

        self::assertSame([2], array_map(static fn($entry): int => $entry->level(), $page->toc()));
        self::assertSame(['_guide'], array_map(static fn($entry): string => $entry->id(), $page->toc()));
        self::assertSame(['Guide'], $page->headings());
        self::assertStringContainsString('<li class="toc-level-2"><a href="#_guide">Guide</a></li>', $page->html());
    }

    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }

    /**
     * Loads the fixture config without schema validation, so a test can reach values the schema
     * forbids but the renderer must still handle.
     *
     * @param array<string, mixed> $overrides
     */
    private function configWithOverrides(array $overrides): SiteConfig
    {
        $decoded = json_decode(
            (string) file_get_contents($this->fixturePath('site.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $document = is_array($decoded) ? $decoded : [];

        foreach ($overrides as $key => $value) {
            $current        = $document[$key] ?? null;
            $document[$key] = is_array($value) && is_array($current)
                ? array_replace_recursive($current, $value)
                : $value;
        }

        return SiteConfig::fromArray($document, $this->fixturePath('site.json'));
    }

    /**
     * The checked-in fixture runs in secure mode, which drops include directives, so the include
     * path is exercised by building a config with a different safe mode.
     */
    private function configWithSafeMode(string $mode): SiteConfig
    {
        return $this->configWithOverrides(['render' => ['safeMode' => $mode]]);
    }

    private function render(
        string $relativePath,
        string $repository = 'versioned',
        string $basePath = '/',
    ): RenderedPage {
        return new PageRenderer()->render(
            $this->fixtureConfig($basePath),
            $this->fixtureRepository($repository),
            $this->fixtureVersion(),
            $this->source($repository, $relativePath),
        );
    }

    private function renderFromTemp(
        string $text,
        ?SiteConfig $config = null,
        string $relativePath = 'docs/v1/page.adoc',
        ?string $directory = null,
    ): RenderedPage {
        $directory ??= $this->createTempDirectory();
        $path      = Path::join($directory, 'page.adoc');

        file_put_contents($path, $text);

        return new PageRenderer()->render(
            $config ?? $this->fixtureConfig(),
            $this->fixtureRepository('versioned'),
            $this->fixtureVersion(),
            new PreparedSource(
                new DocPage('v1', $relativePath, $path),
                SourceFormat::AsciiDoc,
                $text,
                hash('sha256', $text),
            ),
        );
    }

    private function source(string $repository, string $relativePath): PreparedSource
    {
        $path = $this->fixturePath("org/{$repository}/{$relativePath}");
        $body = (string) file_get_contents($path);

        return new PreparedSource(
            new DocPage('v1', $relativePath, $path),
            SourceFormat::AsciiDoc,
            $body,
            hash('sha256', $body),
        );
    }
}
