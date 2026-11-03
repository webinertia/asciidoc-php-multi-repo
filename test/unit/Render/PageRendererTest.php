<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Render;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
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

    #[Test]
    public function aBlankProjectTitleOverrideFallsBackToTheProjectName(): void
    {
        $page = $this->renderFromTemp(
            "= Page\n\nText.\n",
            $this->configWithOverrides(['projects' => ['versioned' => ['title' => '   ']]]),
        );

        static::assertStringContainsString('<p class="project-name">Versioned</p>', $page->html());
    }

    #[Test]
    public function aBrokenIncludeFailsWithRenderFailedAndTheSourcePath(): void
    {
        try {
            $this->render('docs/v1/index.adoc', repository: 'broken-include');
            self::fail('A broken include should fail the page.');
        } catch (SourceException $exception) {
            $error = $exception->error();

            static::assertSame(ReasonCode::RenderFailed, $error->reasonCode());
            static::assertSame('docs/v1/index.adoc', $error->path());
            static::assertSame('v1', $error->version());
            static::assertStringContainsString('missing.adoc', $error->message());
            static::assertNotNull($error->correctiveAction());
        }
    }

    #[Test]
    public function aCrlfSourceRendersIdenticallyToItsLfTwin(): void
    {
        $lf = "= Twin Page\n\nIntro paragraph.\n\n== Section\n\nBody text.\n\n* one\n* two\n";

        $fromLf   = $this->renderFromTemp($lf);
        $fromCrlf = $this->renderFromTemp(str_replace(
            search : "\n",
            replace: "\r\n",
            subject: $lf,
        ));

        static::assertSame($fromLf->html(), $fromCrlf->html());
    }

    #[Test]
    public function aHumanisedNameFillsInWhenTheDocumentHasNoTitle(): void
    {
        $page = $this->renderFromTemp("Just a paragraph.\n", relativePath: 'docs/v1/UPPER-CASE_PAGE.adoc');

        static::assertSame('Upper Case Page', $page->title());
    }

    #[Test]
    public function anIncludeTargetIsTrimmedBeforeItIsResolved(): void
    {
        try {
            $this->renderFromTemp("= Page\n\ninclude::   missing.adoc   []\n");
            self::fail('A padded include of a missing file should fail the page.');
        } catch (SourceException $exception) {
            static::assertSame(
                'The include target "missing.adoc" does not exist.',
                $exception->error()->message(),
            );
        }
    }

    #[Test]
    public function anIncludeThatNamesADirectoryIsNotAMissingFile(): void
    {
        $directory = $this->createTempDirectory();
        new Filesystem()->ensureDirectory(Path::join($directory, 'partials'));

        $page = $this->renderFromTemp("= Page\n\ninclude::partials[]\n", directory: $directory);

        static::assertSame('Page', $page->title());
    }

    #[Test]
    public function aProjectTitleOverrideIsUsedWhenItIsNotBlank(): void
    {
        $page = $this->renderFromTemp(
            "= Page\n\nText.\n",
            $this->configWithOverrides(['projects' => ['versioned' => ['title' => 'Custom Widgets']]]),
        );

        static::assertStringContainsString('<p class="project-name">Custom Widgets</p>', $page->html());
    }

    #[Test]
    public function aRemoteIncludeDoesNotStopTheCheckForTheNextOne(): void
    {
        try {
            $this->renderFromTemp(
                "= Page\n\ninclude::https://example.com/guide.adoc[]\n\ninclude::missing.adoc[]\n",
            );
            self::fail('The second include is missing and should fail the page.');
        } catch (SourceException $exception) {
            static::assertSame(
                'The include target "missing.adoc" does not exist.',
                $exception->error()->message(),
            );
        }
    }

    #[Test]
    public function aRemoteIncludeIsNotTreatedAsAMissingFile(): void
    {
        $page = $this->renderFromTemp("= Page\n\ninclude::https://example.com/guide.adoc[]\n");

        static::assertSame('Page', $page->title());
    }

    #[Test]
    public function aSearchLimitOfZeroKeepsTheWholeText(): void
    {
        $page = $this->renderFromTemp(
            "= Long\n\n" . str_repeat('word ', times: 900) . "\n",
            $this->configWithOverrides(['render' => ['searchExtractLimit' => 0]]),
        );

        static::assertGreaterThan(2000, mb_strlen($page->searchText()));
    }

    #[Test]
    public function aSubdirectoryBasePathIsAppliedToEveryAssetAndLink(): void
    {
        $page = $this->render('docs/v1/index.adoc', basePath: '/docs/');
        $html = $page->html();

        static::assertStringContainsString('<link rel="stylesheet" href="/docs/assets/site.', $html);
        static::assertStringContainsString('<link rel="stylesheet" href="/docs/assets/accent.', $html);
        static::assertStringContainsString('<script src="/docs/assets/theme.', $html);
        static::assertStringContainsString(
            '<link rel="canonical" href="https://docs.example.test/docs/versioned/v1/index.html">',
            $html,
        );
        static::assertStringContainsString('href="/docs/versioned/v1/reference/nested.html"', $html);
        static::assertStringNotContainsString('href="/versioned', $html);
    }

    #[Test]
    public function everyDocumentedSafeModeIsAccepted(): void
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

            static::assertStringContainsString(
                'This is the version 1 guide.',
                $page->html(),
                "Safe mode \"{$mode}\" should still resolve the include.",
            );
            static::assertSame('Versioned Project', $page->title());
        }
    }

    #[Test]
    public function everyIncludeIsCheckedNotJustTheFirst(): void
    {
        $directory = $this->createTempDirectory();
        file_put_contents(Path::join($directory, 'guide.adoc'), data: "== Guide\n\nIncluded text.\n");

        try {
            $this->renderFromTemp(
                "= Page\n\ninclude::guide.adoc[]\n\ninclude::missing.adoc[]\n",
                directory: $directory,
            );
            self::fail('The second include is missing and should fail the page.');
        } catch (SourceException $exception) {
            static::assertSame(
                'The include target "missing.adoc" does not exist.',
                $exception->error()->message(),
            );
        }
    }

    #[Test]
    public function theCanonicalUrlKeepsASingleSlashWhenTheBaseUrlEndsWithOne(): void
    {
        $page = $this->renderFromTemp(
            "= Page\n\nText.\n",
            $this->configWithOverrides(['site' => ['baseUrl' => 'https://docs.example.test/']]),
        );

        static::assertStringContainsString(
            '<link rel="canonical" href="https://docs.example.test/versioned/v1/page.html">',
            $page->html(),
        );
    }

    #[Test]
    public function theConverterDoesNotEmitItsOwnHeaderAndFooter(): void
    {
        $html = $this->render('docs/v1/index.adoc')->html();

        static::assertSame(1, substr_count($html, needle: '<!DOCTYPE html>'));
        static::assertStringNotContainsString('<div id="header">', $html);
        static::assertStringNotContainsString('<div id="footer">', $html);
    }

    #[Test]
    public function theEntryPageRendersWithTheExpectedTitleAndPath(): void
    {
        $page = $this->render('docs/v1/index.adoc');

        static::assertSame('Versioned Project', $page->title());
        static::assertSame('versioned/v1/index.html', $page->outputPath());
        static::assertSame('/versioned/v1/index.html', $page->urlPath());
        static::assertStringContainsString(
            '<title>Versioned Project | Versioned | Fixture Site</title>',
            $page->html(),
        );
        static::assertStringContainsString('Welcome to version 1.', $page->html());
    }

    #[Test]
    public function theIncludedSectionIsRenderedIntoThePageUnderSafeMode(): void
    {
        $page = new PageRenderer()->render(
            $this->configWithSafeMode('safe'),
            $this->fixtureRepository('versioned'),
            $this->fixtureVersion(),
            $this->source('versioned', 'docs/v1/index.adoc'),
        );

        static::assertStringContainsString('<h2 id="_guide">Guide</h2>', $page->html());
        static::assertStringContainsString('This is the version 1 guide.', $page->html());
        static::assertSame(['Guide'], $page->headings());
    }

    #[Test]
    public function theOutputPathIsPartOfThePublicSurface(): void
    {
        $renderer = new PageRenderer();

        static::assertSame(
            'versioned/v1/index.html',
            $renderer->outputPath(
                $this->fixtureRepository('versioned'),
                $this->fixtureVersion(),
                $this->source('versioned', 'docs/v1/index.adoc'),
            ),
        );
    }

    #[Test]
    public function thePageCarriesNoBuildDateAndNoAbsolutePath(): void
    {
        $page = $this->render('docs/v1/index.adoc');
        $html = $page->html();

        static::assertFalse(str_contains($html, (string) date('Y')));
        static::assertStringNotContainsString($this->fixturePath('org/versioned'), $html);
        static::assertStringNotContainsString('C:\\', $html);
        static::assertStringNotContainsString('file://', $html);
        static::assertStringNotContainsString('docdate', $html);
        static::assertStringNotContainsString('doctime', $html);
        static::assertStringNotContainsString('localdate', $html);
    }

    #[Test]
    public function theSafeModeIsReadWithoutRegardToCase(): void
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

        static::assertStringContainsString('This is the version 1 guide.', $safe->html());
        static::assertStringNotContainsString('This is the version 1 guide.', $secure->html());
    }

    #[Test]
    public function theSearchTextHasNoSurroundingWhitespace(): void
    {
        $text = $this->render('docs/v1/index.adoc')->searchText();

        static::assertNotSame('', $text);
        static::assertSame(trim($text), $text);
    }

    #[Test]
    public function theSearchTextIsATruncatedPrefixOfTheWholeText(): void
    {
        $page = $this->renderFromTemp(
            "= Accents\n\nIntro text.\n\n" . str_repeat("\u{65e5}", times: 400) . "\n",
            $this->configWithOverrides(['render' => ['searchExtractLimit' => 200]]),
        );

        static::assertSame(200, mb_strlen($page->searchText()));
        static::assertStringStartsWith('Intro text.', $page->searchText());
    }

    #[Test]
    public function theSearchTextIsPlain(): void
    {
        $page = $this->render('docs/v1/index.adoc');

        static::assertStringNotContainsString('<', $page->searchText());
        static::assertStringContainsString('Welcome to version 1.', $page->searchText());
        static::assertSame(
            ['title' => 'Versioned Project', 'text' => $page->searchText(), 'headings' => $page->headings()],
            $page->searchDocument(),
        );
    }

    #[Test]
    public function theSearchTextIsTruncatedOnCharacterBoundaries(): void
    {
        $page = $this->renderFromTemp(
            "= Accents\n\n" . str_repeat("\u{e9}", times: 400) . "\n",
            $this->configWithOverrides(['render' => ['searchExtractLimit' => 200]]),
        );

        static::assertSame(200, mb_strlen($page->searchText()));
    }

    #[Test]
    public function theSearchTextStopsAtTheConfiguredLimit(): void
    {
        $directory = $this->createTempDirectory();
        $path      = Path::join($directory, 'long.adoc');

        file_put_contents($path, "= Long Page\n\n" . str_repeat('word ', times: 900) . "\n");

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

        static::assertSame(2000, $limit);
        static::assertSame($limit, mb_strlen($page->searchText()));
        static::assertStringEndsWith('word', trim($page->searchText()));
    }

    #[Test]
    public function theSourceLinkPointsAtTheRepository(): void
    {
        $page = $this->render('docs/v1/index.adoc');

        static::assertStringContainsString(
            'href="https://github.com/fixture-org/versioned/blob/main/docs/v1/index.adoc"',
            $page->html(),
        );
    }

    #[Test]
    public function theTableOfContentsComesFromTheDocumentSections(): void
    {
        $page = $this->render('docs/v1/guide.adoc');

        static::assertSame([2], array_map(static fn($entry): int => $entry->level(), $page->toc()));
        static::assertSame(['_guide'], array_map(static fn($entry): string => $entry->id(), $page->toc()));
        static::assertSame(['Guide'], $page->headings());
        static::assertStringContainsString('<li class="toc-level-2"><a href="#_guide">Guide</a></li>', $page->html());
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
            associative: true,
            depth: 512,
            flags: JSON_THROW_ON_ERROR,
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
