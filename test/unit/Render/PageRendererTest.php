<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Render;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfig;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfigLoader;
use Webware\AsciidocPhp\MultiRepo\Render\PageRenderer;
use Webware\AsciidocPhp\MultiRepo\Render\RenderedPage;
use Webware\AsciidocPhp\MultiRepo\Report\ReasonCode;
use Webware\AsciidocPhp\MultiRepo\Source\DocPage;
use Webware\AsciidocPhp\MultiRepo\Source\PreparedSource;
use Webware\AsciidocPhp\MultiRepo\Source\SourceException;
use Webware\AsciidocPhp\MultiRepo\Source\SourceFormat;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use WebwareTest\AsciidocPhp\MultiRepo\Support\SiteFixtureTrait;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function array_map;
use function date;
use function file_get_contents;
use function file_put_contents;
use function hash;
use function hash_file;
use function json_decode;
use function json_encode;
use function mb_strlen;
use function str_contains;
use function str_repeat;
use function trim;

use const JSON_PRETTY_PRINT;
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
     * The checked-in fixture runs in secure mode, which drops include directives. Copying the config
     * into a scratch directory with safe mode is how the include path gets exercised.
     */
    private function configWithSafeMode(string $mode): SiteConfig
    {
        $document = json_decode(
            (string) file_get_contents($this->fixturePath('site.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $document['render']['safeMode'] = $mode;

        $path = Path::join($this->createTempDirectory(), 'site.json');
        file_put_contents($path, json_encode($document, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return new SiteConfigLoader()->load($path);
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
