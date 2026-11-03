<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Search\TextExtractor;

use function str_repeat;
use function strlen;

#[CoversClass(TextExtractor::class)]
#[CoversMethod(TextExtractor::class, 'firstParagraph')]
#[CoversMethod(TextExtractor::class, 'limit')]
#[CoversMethod(TextExtractor::class, 'plain')]
#[CoversMethod(TextExtractor::class, 'truncate')]
final class TextExtractorTest extends TestCase
{
    #[Test]
    public function aHtmlFragmentWithoutAParagraphYieldsAnEmptyDescription(): void
    {
        self::assertSame('', new TextExtractor()->firstParagraph('<h2>Only a heading</h2>'));
    }

    #[Test]
    public function aLimitOfZeroDisablesTruncation(): void
    {
        $text = str_repeat('word ', 40);

        self::assertSame($text, new TextExtractor(limit: 0)->truncate($text));
        self::assertSame(0, new TextExtractor(limit: 0)->limit());
    }

    #[Test]
    public function aTextUnderTheLimitIsUnchanged(): void
    {
        $text = 'A short description.';

        self::assertSame($text, new TextExtractor(limit: 100)->truncate($text));
        self::assertSame($text, new TextExtractor(limit: 20)->truncate($text));
    }

    #[Test]
    public function entitiesAreDecodedInTheExtractedText(): void
    {
        self::assertSame(
            'AT&T <tag>',
            new TextExtractor()->plain('<p>AT&amp;T &lt;tag&gt;</p>'),
        );
    }

    #[Test]
    public function markupIsStrippedAndWhitespaceCollapses(): void
    {
        $html =
            "<div class=\"paragraph\">\n<p>Alpha&nbsp;beta\tgamma\n"
            . " <strong>delta</strong> epsilon.</p>\n</div>";

        self::assertSame(
            'Alpha beta gamma delta epsilon.',
            new TextExtractor()->plain($html),
        );
    }

    #[Test]
    public function scriptAndStyleContentIsNotIndexed(): void
    {
        $html = '<style>.a{color:red}</style><p>Kept</p><script>var a = 1;</script>';

        self::assertSame('Kept', new TextExtractor()->plain($html));
    }

    #[Test]
    public function theFirstParagraphIsUsedForADescription(): void
    {
        $html =
            "<div class=\"sect1\">\n<h2 id=\"a\">Heading</h2>\n"
            . "<div class=\"sectionbody\">\n<div class=\"paragraph\">\n"
            . "<p>The opening paragraph.</p>\n</div>\n</div>\n</div>";

        self::assertSame('The opening paragraph.', new TextExtractor()->firstParagraph($html));
    }

    #[Test]
    public function truncationDoesNotSplitAWord(): void
    {
        $text = str_repeat('word ', 40);

        $truncated = new TextExtractor(limit: 20)->truncate("{$text}");

        self::assertSame('word word word word', $truncated);
        self::assertLessThanOrEqual(20, strlen($truncated));
        self::assertStringEndsNotWith('wor', $truncated);
    }
}
