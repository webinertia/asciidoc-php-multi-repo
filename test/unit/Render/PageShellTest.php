<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Render;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Render\PageContext;
use Webware\AsciidocPhp\MultiRepo\Render\PageShell;
use Webware\AsciidocPhp\MultiRepo\Render\TocEntry;

use function preg_match_all;
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
#[CoversMethod(PageContext::class, 'pageTitle')]
#[CoversMethod(PageContext::class, 'projectTitle')]
#[CoversMethod(PageContext::class, 'siteName')]
#[CoversMethod(PageContext::class, 'versionLabel')]
final class PageShellTest extends TestCase
{
    public function testInterpolatedValuesAreEscaped(): void
    {
        $html = new PageShell()->render($this->context(
            pageTitle   : 'Tips <script>alert("x")</script> & tricks',
            projectTitle: 'R&D <Widgets>',
        ));

        self::assertStringNotContainsString('<script>alert("x")</script>', $html);
        self::assertStringContainsString(
            '<title>Tips &lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; tricks | R&amp;D &lt;Widgets&gt; | Fixture Site</title>',
            $html,
        );
        self::assertStringContainsString('<p class="project-name">R&amp;D &lt;Widgets&gt;</p>', $html);
    }

    public function testTheArticleBodyIsNotEscaped(): void
    {
        $body = '<article class="doc"><p>Escaped &amp; converted.</p></article>';
        $html = new PageShell()->render($this->context(body: $body));

        self::assertStringContainsString($body, $html);
    }

    public function testTheShellCarriesEveryDocumentedElement(): void
    {
        $html = new PageShell()->render($this->context());

        self::assertStringStartsWith('<!DOCTYPE html>', $html);
        self::assertStringContainsString('<meta charset="utf-8">', $html);
        self::assertStringContainsString('<meta name="viewport" content="width=device-width, initial-scale=1">', $html);
        self::assertStringContainsString(
            '<title>Usage examples | Widgets | Fixture Site</title>',
            $html,
        );
        self::assertStringContainsString(
            '<link rel="canonical" href="https://docs.example.test/widgets/v1/usage.html">',
            $html,
        );
        self::assertSame(2, substr_count($html, '<link rel="stylesheet"'));
        self::assertStringContainsString('<a class="skip-link" href="#main">Skip to content</a>', $html);
        self::assertStringContainsString('<header class="site-header">', $html);
        self::assertStringContainsString('<nav class="sidebar" aria-label="On this page">', $html);
        self::assertStringContainsString('<main id="main">', $html);
        self::assertStringContainsString('<footer class="site-footer">', $html);
        self::assertStringContainsString('<a class="source-link"', $html);
        self::assertStringContainsString('rel="noopener"', $html);
        self::assertStringContainsString('data-theme-toggle', $html);
        self::assertStringContainsString('aria-pressed="false"', $html);
    }

    public function testTheSourceLinkIsOmittedWhenThereIsNoSourceUrl(): void
    {
        $html = new PageShell()->render($this->context(sourceUrl: null));

        self::assertStringNotContainsString('class="source-link"', $html);
    }

    public function testTheTableOfContentsIsRenderedAsAList(): void
    {
        $html = new PageShell()->render($this->context(toc: [
            new TocEntry(2, '_first', 'First'),
            new TocEntry(3, '_nested', 'Nested & <deep>'),
        ]));

        self::assertStringContainsString('<li class="toc-level-2"><a href="#_first">First</a></li>', $html);
        self::assertStringContainsString(
            '<li class="toc-level-3"><a href="#_nested">Nested &amp; &lt;deep&gt;</a></li>',
            $html,
        );
    }

    public function testTheThemeModeReachesTheDocumentElement(): void
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

        self::assertStringContainsString('<html lang="en" data-theme="light" data-theme-mode="system">', $html);
        preg_match_all('/<script>/', $html, $inline);
        self::assertCount(1, $inline[0]);
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
            stylesheets     : ['/assets/site.abc123.css', '/assets/accent.def456.css'],
            scripts         : ['/assets/theme.012abc.js'],
            sourceUrl       : $sourceUrl,
            colorMode       : 'system',
            defaultColorMode: 'dark',
        );
    }
}
