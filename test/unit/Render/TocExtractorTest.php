<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Render;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\Asciidoc;
use Webware\AsciidocPhp\Document;
use Webware\AsciidocPhp\MultiRepo\Render\SiteConverter;
use Webware\AsciidocPhp\MultiRepo\Render\TocEntry;
use Webware\AsciidocPhp\MultiRepo\Render\TocExtractor;
use Webware\AsciidocPhp\MultiRepo\Report\ReasonCode;
use Webware\AsciidocPhp\MultiRepo\Source\SourceException;
use Webware\AsciidocPhp\Node\Section;

use function array_map;

/**
 * T034: the sidebar table of contents.
 *
 * The levels are published heading levels, so `==` is 2 and `===` is 3, which is what a page with
 * an h2 and an h3 yields. A page whose only heading is the document title has no entries at all.
 */
#[CoversClass(TocExtractor::class)]
#[CoversClass(TocEntry::class)]
#[CoversMethod(TocExtractor::class, 'extract')]
#[CoversMethod(TocEntry::class, 'id')]
#[CoversMethod(TocEntry::class, 'level')]
#[CoversMethod(TocEntry::class, 'title')]
#[CoversMethod(TocEntry::class, 'toArray')]
final class TocExtractorTest extends TestCase
{
    private const string ORDERED_SOURCE = <<<'ADOC'
        = Ordered Page

        == Second level heading

        === Third level heading

        == Another second level heading
        ADOC;

    public function testAnH1OnlyPageYieldsAnEmptyTableOfContents(): void
    {
        $entries = new TocExtractor()->extract(
            $this->document("= Title only\n\nA paragraph with no section below it."),
            [2, 3],
        );

        self::assertSame([], $entries);
    }

    public function testASectionWithoutAnIdFailsWithTocTargetMissing(): void
    {
        $document = new Document();
        $section  = new Section($document, $document, 1);
        $section->setTitle('Orphan heading');
        $section->setAttribute('id', '');
        $document->append($section);

        try {
            new TocExtractor()->extract($document, [2], 'docs/v1/index.adoc');
            self::fail('A section without an id should fail the page.');
        } catch (SourceException $exception) {
            $error = $exception->error();

            self::assertSame(ReasonCode::TocTargetMissing, $error->reasonCode());
            self::assertSame('docs/v1/index.adoc', $error->path());
            self::assertStringContainsString('Orphan heading', $error->message());
            self::assertNotNull($error->correctiveAction());
        }
    }

    public function testHeadingsAreReturnedInDocumentOrder(): void
    {
        $entries = new TocExtractor()->extract($this->document(self::ORDERED_SOURCE), [2, 3]);

        self::assertSame(
            [
                ['level' => 2, 'id' => '_second_level_heading', 'title' => 'Second level heading'],
                ['level' => 3, 'id' => '_third_level_heading', 'title' => 'Third level heading'],
                ['level' => 2, 'id' => '_another_second_level_heading', 'title' => 'Another second level heading'],
            ],
            array_map(static fn(TocEntry $entry): array => $entry->toArray(), $entries),
        );
    }

    public function testOnlyTheConfiguredLevelsAreReturned(): void
    {
        $entries = new TocExtractor()->extract($this->document(self::ORDERED_SOURCE), [2]);

        self::assertCount(2, $entries);
        self::assertSame([2, 2], array_map(static fn(TocEntry $entry): int => $entry->level(), $entries));
    }

    private function document(string $source): Document
    {
        $converter = new SiteConverter();

        Asciidoc::convert($source, ['converter' => $converter, 'header_footer' => false]);

        $document = $converter->lastDocument();
        self::assertInstanceOf(Document::class, $document);

        return $document;
    }
}
