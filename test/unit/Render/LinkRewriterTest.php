<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Render;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Render\LinkRewriter;

/**
 * T038: internal links become published links, external links stay put.
 *
 * The same fixture link is rewritten under `/` and under `/docs/`, and the base-path case asserts
 * that no generated link starts with a bare root slash.
 */
#[CoversClass(LinkRewriter::class)]
#[CoversMethod(LinkRewriter::class, 'isExternal')]
#[CoversMethod(LinkRewriter::class, 'rewrite')]
#[CoversMethod(LinkRewriter::class, 'rewriteUrl')]
final class LinkRewriterTest extends TestCase
{
    private const string PAGE = '/project/latest/guide.html';

    #[Test]
    public function aFragmentThatDoesNotEndInAdocIsLeftAlone(): void
    {
        $rewriter = new LinkRewriter();

        static::assertSame('#adoc', $rewriter->rewriteUrl('#adoc', self::PAGE, '/docs/'));
        static::assertSame('#page.adoc.txt', $rewriter->rewriteUrl('#page.adoc.txt', self::PAGE, '/docs/'));
    }

    #[Test]
    public function anAdocLinkBecomesAnHtmlLinkUnderASubdirectoryBasePath(): void
    {
        $url = new LinkRewriter()->rewriteUrl('../v2/usage-examples.adoc', self::PAGE, '/docs/');

        static::assertSame('/docs/project/v2/usage-examples.html', $url);
    }

    #[Test]
    public function anAdocLinkBecomesAnHtmlLinkUnderTheRootBasePath(): void
    {
        $url = new LinkRewriter()->rewriteUrl('../v2/usage-examples.adoc', self::PAGE, '/');

        static::assertSame('/project/v2/usage-examples.html', $url);
    }

    #[Test]
    public function anAnchorWithASingleQuotedHrefIsLeftAlone(): void
    {
        static::assertSame(
            "<a href='one.adoc'>One</a>",
            new LinkRewriter()->rewrite("<a href='one.adoc'>One</a>", self::PAGE, '/docs/'),
        );
    }

    #[Test]
    public function anAnchorWithoutAnHrefIsLeftAlone(): void
    {
        $rewriter = new LinkRewriter();

        static::assertSame(
            '<a name="top">Top</a>',
            $rewriter->rewrite('<a name="top">Top</a>', self::PAGE, '/docs/'),
        );
        static::assertSame(
            '<a id="end">End</a>',
            $rewriter->rewrite('<a id="end">End</a>', self::PAGE, '/docs/'),
        );
    }

    #[Test]
    public function anEmptyRelBecomesNoopenerRatherThanAnEmptyToken(): void
    {
        static::assertSame(
            '<a href="https://example.com" rel="noopener">External</a>',
            new LinkRewriter()->rewrite('<a href="https://example.com" rel="">External</a>', self::PAGE, '/'),
        );
    }

    #[Test]
    public function anExistingRelIsExtendedRatherThanDuplicated(): void
    {
        $rewriter = new LinkRewriter();

        static::assertSame(
            '<a href="https://example.com" rel="external noopener">External</a>',
            $rewriter->rewrite('<a href="https://example.com" rel="external">External</a>', self::PAGE, '/'),
        );
        static::assertSame(
            '<a href="https://example.com" rel="noopener">External</a>',
            $rewriter->rewrite('<a href="https://example.com" rel="noopener">External</a>', self::PAGE, '/'),
        );
    }

    #[Test]
    public function anExistingRelIsRecognisedInAnyCase(): void
    {
        static::assertSame(
            '<a href="https://example.com" rel="external noopener">External</a>',
            new LinkRewriter()->rewrite('<a href="https://example.com" REL="external">External</a>', self::PAGE, '/'),
        );
    }

    #[Test]
    public function anExternalUrlIsReturnedUnchangedByTheUrlRewriter(): void
    {
        $rewriter = new LinkRewriter();

        static::assertSame('https://example.com/page.adoc', $rewriter->rewriteUrl(
            'https://example.com/page.adoc',
            self::PAGE,
            '/docs/',
        ));
        static::assertSame('//cdn.example.com/x.adoc', $rewriter->rewriteUrl(
            '//cdn.example.com/x.adoc',
            self::PAGE,
            '/docs/',
        ));
    }

    #[Test]
    public function anInPageFragmentIsLeftAlone(): void
    {
        $rewriter = new LinkRewriter();

        static::assertSame('#install', $rewriter->rewriteUrl('#install', self::PAGE, '/docs/'));
    }

    #[Test]
    public function anXrefAnchorIsAppendedAfterTheResolvedPath(): void
    {
        static::assertSame(
            '/docs/versioned/v1/reference/nested.html#install',
            new LinkRewriter()->rewriteUrl('#reference/nested.adoc#install', '/versioned/v1/index.html', '/docs/'),
        );
    }

    #[Test]
    public function anXrefIsRecognisedInAnyCase(): void
    {
        static::assertSame(
            '/docs/versioned/v1/Reference/Nested.html',
            new LinkRewriter()->rewriteUrl('#Reference/Nested.ADOC', '/versioned/v1/index.html', '/docs/'),
        );
    }

    #[Test]
    public function anXrefToAnotherPageIsResolvedAgainstThePageDirectory(): void
    {
        $url = new LinkRewriter()->rewriteUrl('#reference/nested.adoc', '/versioned/v1/index.html', '/docs/');

        static::assertSame('/docs/versioned/v1/reference/nested.html', $url);
    }

    #[Test]
    public function anXrefWithNoAnchorGainsNoEmptyFragment(): void
    {
        static::assertSame(
            '/docs/versioned/v1/reference/nested.html',
            new LinkRewriter()->rewriteUrl('#reference/nested.adoc', '/versioned/v1/index.html', '/docs/'),
        );
    }

    #[Test]
    public function everyInternalLinkUnderASubdirectoryBasePathStartsWithThatBasePath(): void
    {
        $html = new LinkRewriter()->rewrite(
            '<a href="one.adoc">One</a> <a href="/two.adoc">Two</a> <a href="#three">Three</a>',
            self::PAGE,
            '/docs/',
        );

        static::assertSame(
            '<a href="/docs/project/latest/one.html">One</a> <a href="/docs/two.html">Two</a> <a href="#three">Three</a>',
            $html,
        );
        static::assertStringNotContainsString('href="/project', $html);
        static::assertStringNotContainsString('href="/two', $html);
    }

    #[Test]
    public function externalLinksAreUntouchedButMarkedNoopener(): void
    {
        $html = new LinkRewriter()->rewrite(
            '<a href="https://example.com/page">External</a>',
            self::PAGE,
            '/docs/',
        );

        static::assertSame('<a href="https://example.com/page" rel="noopener">External</a>', $html);
        static::assertTrue(new LinkRewriter()->isExternal('//cdn.example.com/x.js'));
        static::assertTrue(new LinkRewriter()->isExternal('mailto:docs@example.test'));
        static::assertFalse(new LinkRewriter()->isExternal('../v2/usage.adoc'));
    }

    #[Test]
    public function onlyTheFirstHrefAttributeIsRewritten(): void
    {
        static::assertSame(
            '<a href="/project/latest/one.html" data-href="two.adoc">One</a>',
            new LinkRewriter()->rewrite('<a href="one.adoc" data-href="two.adoc">One</a>', self::PAGE, '/'),
        );
    }

    #[Test]
    public function theAnchorKeepsItsFragmentAndQuery(): void
    {
        $rewriter = new LinkRewriter();

        static::assertSame(
            '/docs/project/v2/usage.html#install',
            $rewriter->rewriteUrl('../v2/usage.adoc#install', self::PAGE, '/docs/'),
        );
        static::assertSame(
            '/docs/project/v2/usage.html?mode=full#install',
            $rewriter->rewriteUrl('../v2/usage.adoc?mode=full#install', self::PAGE, '/docs/'),
        );
    }

    #[Test]
    public function theNoopenerAttributeIsSeparatedFromATrailingSpace(): void
    {
        static::assertSame(
            '<a href="https://example.com" rel="noopener">External</a>',
            new LinkRewriter()->rewrite('<a href="https://example.com" >External</a>', self::PAGE, '/'),
        );
    }
}
