<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Render;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
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

    public function testAnAdocLinkBecomesAnHtmlLinkUnderASubdirectoryBasePath(): void
    {
        $url = new LinkRewriter()->rewriteUrl('../v2/usage-examples.adoc', self::PAGE, '/docs/');

        self::assertSame('/docs/project/v2/usage-examples.html', $url);
    }

    public function testAnAdocLinkBecomesAnHtmlLinkUnderTheRootBasePath(): void
    {
        $url = new LinkRewriter()->rewriteUrl('../v2/usage-examples.adoc', self::PAGE, '/');

        self::assertSame('/project/v2/usage-examples.html', $url);
    }

    public function testAnExistingRelIsExtendedRatherThanDuplicated(): void
    {
        $rewriter = new LinkRewriter();

        self::assertSame(
            '<a href="https://example.com" rel="external noopener">External</a>',
            $rewriter->rewrite('<a href="https://example.com" rel="external">External</a>', self::PAGE, '/'),
        );
        self::assertSame(
            '<a href="https://example.com" rel="noopener">External</a>',
            $rewriter->rewrite('<a href="https://example.com" rel="noopener">External</a>', self::PAGE, '/'),
        );
    }

    public function testAnInPageFragmentIsLeftAlone(): void
    {
        $rewriter = new LinkRewriter();

        self::assertSame('#install', $rewriter->rewriteUrl('#install', self::PAGE, '/docs/'));
    }

    public function testAnXrefToAnotherPageIsResolvedAgainstThePageDirectory(): void
    {
        $url = new LinkRewriter()->rewriteUrl('#reference/nested.adoc', '/versioned/v1/index.html', '/docs/');

        self::assertSame('/docs/versioned/v1/reference/nested.html', $url);
    }

    public function testEveryInternalLinkUnderASubdirectoryBasePathStartsWithThatBasePath(): void
    {
        $html = new LinkRewriter()->rewrite(
            '<a href="one.adoc">One</a> <a href="/two.adoc">Two</a> <a href="#three">Three</a>',
            self::PAGE,
            '/docs/',
        );

        self::assertSame(
            '<a href="/docs/project/latest/one.html">One</a> <a href="/docs/two.html">Two</a> <a href="#three">Three</a>',
            $html,
        );
        self::assertStringNotContainsString('href="/project', $html);
        self::assertStringNotContainsString('href="/two', $html);
    }

    public function testExternalLinksAreUntouchedButMarkedNoopener(): void
    {
        $html = new LinkRewriter()->rewrite(
            '<a href="https://example.com/page">External</a>',
            self::PAGE,
            '/docs/',
        );

        self::assertSame('<a href="https://example.com/page" rel="noopener">External</a>', $html);
        self::assertTrue(new LinkRewriter()->isExternal('//cdn.example.com/x.js'));
        self::assertTrue(new LinkRewriter()->isExternal('mailto:docs@example.test'));
        self::assertFalse(new LinkRewriter()->isExternal('../v2/usage.adoc'));
    }

    public function testTheAnchorKeepsItsFragmentAndQuery(): void
    {
        $rewriter = new LinkRewriter();

        self::assertSame(
            '/docs/project/v2/usage.html#install',
            $rewriter->rewriteUrl('../v2/usage.adoc#install', self::PAGE, '/docs/'),
        );
        self::assertSame(
            '/docs/project/v2/usage.html?mode=full#install',
            $rewriter->rewriteUrl('../v2/usage.adoc?mode=full#install', self::PAGE, '/docs/'),
        );
    }
}
