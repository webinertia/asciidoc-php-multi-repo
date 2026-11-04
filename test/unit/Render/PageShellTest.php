<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Render;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Webware\AsciidocPhp\MultiRepo\Render\PageChrome;
use Webware\AsciidocPhp\MultiRepo\Render\PageContext;
use Webware\AsciidocPhp\MultiRepo\Render\PageShell;
use Webware\AsciidocPhp\MultiRepo\Render\TocEntry;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function file_put_contents;
use function ob_get_level;
use function preg_match_all;
use function str_replace;
use function substr_count;

/**
 * T035: the document shell and its escaping.
 *
 * The escaping proof uses a title containing `<` and `&`, which is the exact case the acceptance
 * criterion names, and asserts that the same characters survive untouched only in the article body.
 */
#[CoversClass(PageShell::class)]
#[CoversClass(PageContext::class)]
#[CoversMethod(PageShell::class, 'escape')]
#[CoversMethod(PageShell::class, 'render')]
#[CoversMethod(PageShell::class, 'renderToc')]
#[CoversMethod(PageShell::class, 'templateDirectory')]
#[CoversMethod(PageContext::class, 'title')]
#[CoversMethod(PageContext::class, 'toc')]
#[CoversMethod(PageContext::class, 'body')]
#[CoversMethod(PageContext::class, 'scripts')]
#[CoversMethod(PageContext::class, 'stylesheets')]
#[CoversMethod(PageContext::class, 'canonicalUrl')]
#[CoversMethod(PageContext::class, 'sourceUrl')]
#[CoversMethod(PageContext::class, 'colorMode')]
#[CoversMethod(PageContext::class, 'defaultColorMode')]
#[CoversMethod(PageContext::class, 'logoUrl')]
#[CoversMethod(PageContext::class, 'pageTitle')]
#[CoversMethod(PageContext::class, 'projectTitle')]
#[CoversMethod(PageContext::class, 'siteName')]
#[CoversMethod(PageContext::class, 'versionLabel')]
#[CoversMethod(PageContext::class, 'withChrome')]
final class PageShellTest extends TestCase
{
    use TempDirectoryTrait;

    #[Test]
    public function aBufferLeftOpenByTheTemplateDoesNotEscapeTheShell(): void
    {
        $directory = $this->createTempDirectory();
        file_put_contents("{$directory}/page.php", data: '<?php ob_start(); echo "leaked";');

        $level = ob_get_level();

        try {
            $html = new PageShell($directory)->render($this->context());

            static::assertSame($level, ob_get_level());
            static::assertSame('leaked', $html);
        } finally {
            $this->removeTempDirectory($directory);
        }
    }

    #[Test]
    public function aMissingTemplateIsReported(): void
    {
        $directory = $this->createTempDirectory();

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage("Template \"page.php\" is missing from \"{$directory}\".");

            new PageShell($directory)->render($this->context());
        } finally {
            $this->removeTempDirectory($directory);
        }
    }

    #[Test]
    public function anOmittedTemplateDirectoryFallsBackToTheShippedTemplates(): void
    {
        $directory = new PageShell(null)->templateDirectory();

        static::assertDirectoryExists($directory);
        static::assertStringEndsWith('resources/templates', str_replace('\\', replace: '/', subject: $directory));
        static::assertSame($directory, new PageShell()->templateDirectory());
    }

    #[Test]
    public function interpolatedValuesAreEscaped(): void
    {
        $html = new PageShell()->render($this->context(
            pageTitle   : 'Tips <script>alert("x")</script> & tricks',
            projectTitle: 'R&D <Widgets>',
        ));

        static::assertStringNotContainsString('<script>alert("x")</script>', $html);
        static::assertStringContainsString(
            '<title>Tips &lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; tricks | R&amp;D &lt;Widgets&gt; | Fixture Site</title>',
            $html,
        );
        static::assertStringContainsString('<p class="project-name">R&amp;D &lt;Widgets&gt;</p>', $html);
    }

    #[Test]
    public function theArticleBodyIsNotEscaped(): void
    {
        $body = '<article class="doc"><p>Escaped &amp; converted.</p></article>';
        $html = new PageShell()->render($this->context(body: $body));

        static::assertStringContainsString($body, $html);
    }

    #[Test]
    public function theChromeCopyKeepsTheLogoAndTakesTheNewScripts(): void
    {
        $page = new PageContext(
            siteName    : 'Fixture Site',
            projectTitle: 'Widgets',
            pageTitle   : 'Usage examples',
            versionLabel: 'v1',
            canonicalUrl: 'https://docs.example.test/widgets/v1/usage.html',
            body        : '<article class="doc"></article>',
            logoUrl     : '/assets/logo.abc123.svg',
        );

        $withChrome = $page->withChrome(new PageChrome(landingUrl: '/docs/'), ['/assets/nav.def456.js']);

        static::assertSame('/assets/logo.abc123.svg', $withChrome->logoUrl());
        static::assertSame(['/assets/nav.def456.js'], $withChrome->scripts());
        static::assertSame('/docs/', $withChrome->chrome()->landingUrl());
        static::assertSame($page->title(), $withChrome->title());
        static::assertSame($page->canonicalUrl(), $withChrome->canonicalUrl());
    }

    #[Test]
    public function theContextExposesItsPageTitle(): void
    {
        static::assertSame('Usage examples', $this->context()->pageTitle());
        static::assertSame('Rendered & escaped', $this->context(pageTitle: 'Rendered & escaped')->pageTitle());
    }

    #[Test]
    public function theShellCarriesEveryDocumentedElement(): void
    {
        $html = new PageShell()->render($this->context());

        static::assertStringStartsWith('<!DOCTYPE html>', $html);
        static::assertStringContainsString('<meta charset="utf-8">', $html);
        static::assertStringContainsString(
            '<meta name="viewport" content="width=device-width, initial-scale=1">',
            $html,
        );
        static::assertStringContainsString(
            '<title>Usage examples | Widgets | Fixture Site</title>',
            $html,
        );
        static::assertStringContainsString(
            '<link rel="canonical" href="https://docs.example.test/widgets/v1/usage.html">',
            $html,
        );
        static::assertSame(3, substr_count($html, needle: '<link rel="stylesheet"'));
        static::assertStringContainsString('<a class="skip-link" href="#main">Skip to content</a>', $html);
        static::assertStringContainsString('<header class="site-header">', $html);
        static::assertStringContainsString('<nav class="sidebar" aria-label="On this page">', $html);
        static::assertStringContainsString('<main id="main">', $html);
        static::assertStringContainsString('<footer class="site-footer">', $html);
        static::assertStringContainsString('<a class="source-link"', $html);
        static::assertStringContainsString('rel="noopener"', $html);
        static::assertStringContainsString('data-theme-toggle', $html);
        static::assertStringContainsString('aria-pressed="false"', $html);
    }

    #[Test]
    public function theShellHelpersAreCallableFromOutsideTheShell(): void
    {
        $shell = new PageShell();

        static::assertSame('&lt;b&gt; &amp; more', $shell->escape('<b> & more'));
        static::assertStringContainsString('href="#_guide"', $shell->renderToc([new TocEntry(2, '_guide', 'Guide')]));
    }

    #[Test]
    public function theSourceLinkIsOmittedWhenThereIsNoSourceUrl(): void
    {
        $html = new PageShell()->render($this->context(sourceUrl: null));

        static::assertStringNotContainsString('class="source-link"', $html);
    }

    #[Test]
    public function theTableOfContentsIsRenderedAsAList(): void
    {
        $html = new PageShell()->render($this->context(toc: [
            new TocEntry(2, '_first', 'First'),
            new TocEntry(3, '_nested', 'Nested & <deep>'),
        ]));

        static::assertStringContainsString('<li class="toc-level-2"><a href="#_first">First</a></li>', $html);
        static::assertStringContainsString(
            '<li class="toc-level-3"><a href="#_nested">Nested &amp; &lt;deep&gt;</a></li>',
            $html,
        );
    }

    #[Test]
    public function theThemeModeReachesTheDocumentElement(): void
    {
        $html = new PageShell()->render(new PageContext(
            siteName        : 'Fixture Site',
            projectTitle    : 'Widgets',
            pageTitle       : 'Usage examples',
            versionLabel    : 'v1',
            canonicalUrl    : 'https://docs.example.test/widgets/v1/usage.html',
            body            : '<article class="doc"></article>',
            toc             : [],
            stylesheets     : ['/assets/site.abc123.css'],
            scripts         : ['/assets/theme.012abc.js'],
            sourceUrl       : null,
            colorMode       : 'system',
            defaultColorMode: 'light',
        ));

        static::assertStringContainsString('<html lang="en" data-theme="light" data-theme-mode="system">', $html);
        preg_match_all('/<script>/', $html, $inline);
        static::assertCount(1, $inline[0]);
    }

    private function context(
        string $pageTitle = 'Usage examples',
        string $projectTitle = 'Widgets',
        string $body = '<article class="doc"></article>',
        array $toc = [],
        ?string $sourceUrl = 'https://github.com/fixture-org/widgets/blob/main/docs/v1/usage.adoc',
    ): PageContext {
        return new PageContext(
            siteName        : 'Fixture Site',
            projectTitle    : $projectTitle,
            pageTitle       : $pageTitle,
            versionLabel    : 'v1',
            canonicalUrl    : 'https://docs.example.test/widgets/v1/usage.html',
            body            : $body,
            toc             : $toc,
            stylesheets     : [
                '/assets/asciidoctor.012def.css',
                '/assets/site.abc123.css',
                '/assets/accent.def456.css',
            ],
            scripts         : ['/assets/theme.012abc.js'],
            sourceUrl       : $sourceUrl,
            colorMode       : 'system',
            defaultColorMode: 'dark',
        );
    }
}
