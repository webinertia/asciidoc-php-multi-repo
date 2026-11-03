<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Render;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\Asciidoc;
use Webware\AsciidocPhp\Converter\Html5Converter;
use Webware\AsciidocPhp\Document;
use Webware\AsciidocPhp\MultiRepo\Render\SiteConverter;

use function preg_match_all;

/**
 * T032: the site converter returns article markup only.
 *
 * The golden comparison converts the same source with the parent `Html5Converter` and with
 * `SiteConverter`, then compares the section markup and the heading ids. The wrappers differ by
 * design (the parent emits a `<div id="content">` shell and a stylesheet, the site emits an
 * `<article>`), so the comparison is on the section fragment, which is what the shell embeds.
 */
#[CoversClass(SiteConverter::class)]
#[CoversMethod(SiteConverter::class, 'convert')]
#[CoversMethod(SiteConverter::class, 'convertDocument')]
#[CoversMethod(SiteConverter::class, 'handles')]
#[CoversMethod(SiteConverter::class, 'lastDocument')]
#[CoversMethod(SiteConverter::class, 'renderStylesheet')]
final class SiteConverterTest extends TestCase
{
    private const string SOURCE = <<<'ADOC'
        = Golden Page

        Introductory paragraph.

        == First section

        A paragraph in the first section.

        === Nested section

        * one
        * two

        == Second section

        A paragraph in the second section.
        ADOC;

    #[Test]
    public function aNonDocumentNodeIsDelegatedToTheParentConverter(): void
    {
        $converter = new SiteConverter();

        Asciidoc::convert(self::SOURCE, ['converter' => $converter, 'header_footer' => false]);

        $document = $converter->lastDocument();
        self::assertInstanceOf(Document::class, $document);

        $block = $document->getBlocks()[0] ?? null;
        self::assertNotNull($block);

        self::assertStringNotContainsString('<article class="doc">', $converter->convert($block));
        self::assertStringContainsString('<article class="doc">', $converter->convert($document));
    }

    #[Test]
    public function headingIdsMatchTheParentConverter(): void
    {
        self::assertSame($this->parentIds(), $this->siteIds());
        self::assertNotSame([], $this->siteIds());
    }

    #[Test]
    public function sectionMarkupMatchesTheParentConverter(): void
    {
        self::assertSame($this->parentSections(), $this->siteSections());
        self::assertNotSame([], $this->siteSections());
    }

    #[Test]
    public function theArticleFragmentIsAvailableWithoutTheConvertEntryPoint(): void
    {
        $converter = new SiteConverter();

        Asciidoc::convert(self::SOURCE, ['converter' => $converter, 'header_footer' => false]);

        $document = $converter->lastDocument();
        self::assertInstanceOf(Document::class, $document);

        $html = $converter->convertDocument($document);

        self::assertStringStartsWith('<article class="doc">', $html);
        self::assertStringContainsString('<h2 id="_first_section">First section</h2>', $html);
    }

    #[Test]
    public function theConverterDelegatesEveryOtherTransformToTheParent(): void
    {
        $converter = new SiteConverter();

        self::assertTrue($converter->handles('paragraph'));
        self::assertTrue($converter->handles('section'));
        self::assertFalse($converter->handles('not-a-transform'));
    }

    #[Test]
    public function theConverterHandlesTheDocumentTransform(): void
    {
        self::assertTrue(new SiteConverter()->handles('document'));
    }

    #[Test]
    public function theDocumentIsCapturedForLaterInspection(): void
    {
        $converter = new SiteConverter();

        Asciidoc::convert(self::SOURCE, ['converter' => $converter, 'header_footer' => false]);

        $document = $converter->lastDocument();

        self::assertInstanceOf(Document::class, $document);
        self::assertSame('Golden Page', $document->getDocTitle());
    }

    #[Test]
    public function theDocumentIsWrappedInAnArticleAndNothingElse(): void
    {
        $html = $this->convertWithSiteConverter();

        self::assertStringStartsWith('<article class="doc">', $html);
        self::assertStringEndsWith("</article>\n", $html);
        self::assertStringNotContainsString('<html', $html);
        self::assertStringNotContainsString('<head', $html);
        self::assertStringNotContainsString('<body', $html);
        self::assertStringNotContainsString('id="content"', $html);
    }

    #[Test]
    public function theStylesheetIsEmpty(): void
    {
        $converter = new SiteConverter();

        self::assertSame('', $converter->renderStylesheet());
    }

    private function convertWithSiteConverter(): string
    {
        return Asciidoc::convert(self::SOURCE, [
            'converter'     => new SiteConverter(),
            'header_footer' => false,
        ]);
    }

    /**
     * @return list<string>
     */
    private function ids(string $html): array
    {
        preg_match_all('/<h[1-6] id="([^"]+)"/', $html, $matches);

        return $matches[1];
    }

    /**
     * @return list<string>
     */
    private function parentIds(): array
    {
        return $this->ids(Asciidoc::convert(self::SOURCE, [
            'converter'     => new Html5Converter(),
            'header_footer' => false,
        ]));
    }

    /**
     * @return list<string>
     */
    private function parentSections(): array
    {
        return $this->sections(Asciidoc::convert(self::SOURCE, [
            'converter'     => new Html5Converter(),
            'header_footer' => false,
        ]));
    }

    /**
     * @return list<string>
     */
    private function sections(string $html): array
    {
        preg_match_all('/<div class="sect\d">.*?<\/div>\s*<\/div>/s', $html, $matches);

        return $matches[0];
    }

    /**
     * @return list<string>
     */
    private function siteIds(): array
    {
        return $this->ids($this->convertWithSiteConverter());
    }

    /**
     * @return list<string>
     */
    private function siteSections(): array
    {
        return $this->sections($this->convertWithSiteConverter());
    }
}
