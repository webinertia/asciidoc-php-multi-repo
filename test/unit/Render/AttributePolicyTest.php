<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Render;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\Asciidoc;
use Webware\AsciidocPhp\Document;
use Webware\AsciidocPhp\MultiRepo\Render\AttributePolicy;
use Webware\AsciidocPhp\MultiRepo\Render\SiteConverter;

use function date_default_timezone_get;
use function date_default_timezone_set;

/**
 * T033: the attributes that keep a page independent of the machine that rendered it.
 *
 * The clock proof renders the same source twice with the process timezone moved by fourteen hours
 * between the runs. The source names every clock attribute, so a policy that let the converter seed
 * them from `date()` would produce different bytes on the second run.
 */
#[CoversClass(AttributePolicy::class)]
#[CoversMethod(AttributePolicy::class, 'attributes')]
#[CoversMethod(AttributePolicy::class, 'syntaxHighlighting')]
final class AttributePolicyTest extends TestCase
{
    private const string CLOCK_SOURCE = <<<'ADOC'
        = Clock Page

        A page that would carry a build date if the policy let it.
        ADOC;

    #[Test]
    public function highlightingLeavesTheHighlighterAttributeAlone(): void
    {
        $policy = new AttributePolicy(syntaxHighlighting: true);

        static::assertTrue($policy->syntaxHighlighting());
        static::assertArrayNotHasKey('source-highlighter', $policy->attributes());
    }

    #[Test]
    public function renderingTwiceAcrossAClockChangeProducesIdenticalBytes(): void
    {
        $original = date_default_timezone_get();

        try {
            date_default_timezone_set('UTC');
            $first = $this->renderClockPage();

            date_default_timezone_set('Pacific/Kiritimati');
            $second = $this->renderClockPage();
        } finally {
            date_default_timezone_set($original);
        }

        static::assertSame($first, $second);
    }

    #[Test]
    public function theClockAttributesCarryNoValueInTheDocument(): void
    {
        $document = $this->document(new AttributePolicy());

        static::assertNotNull($document);

        foreach (AttributePolicy::CLOCK_ATTRIBUTES as $name) {
            static::assertSame('', $document->getAttribute($name));
        }
    }

    #[Test]
    public function theConverterChromeIsSwitchedOff(): void
    {
        $attributes = new AttributePolicy()->attributes();

        static::assertFalse($attributes['stylesheet']);
        static::assertSame('', $attributes['noembed']);
        static::assertSame('', $attributes['linkcss']);
        static::assertFalse($attributes['toc']);
        static::assertFalse($attributes['docinfo']);
        static::assertFalse($attributes['source-highlighter']);
    }

    #[Test]
    public function theSixClockAttributesArePinnedToAnEmptyString(): void
    {
        $attributes = new AttributePolicy()->attributes();

        static::assertSame(
            ['docdate', 'doctime', 'docdatetime', 'localdate', 'localtime', 'localdatetime'],
            AttributePolicy::CLOCK_ATTRIBUTES,
        );

        foreach (AttributePolicy::CLOCK_ATTRIBUTES as $name) {
            static::assertArrayHasKey($name, $attributes);
            static::assertSame('', $attributes[$name]);
        }
    }

    #[Test]
    public function withoutThePolicyTheClockAttributesCarryTheBuildDate(): void
    {
        $document = $this->document(null);

        static::assertNotNull($document);
        static::assertNotSame('', $document->getAttribute('docdate'));
        static::assertNotSame('', $document->getAttribute('doctime'));
    }

    private function document(?AttributePolicy $policy): ?Document
    {
        $converter = new SiteConverter();

        Asciidoc::convert(self::CLOCK_SOURCE, [
            'attributes'    => $policy?->attributes() ?? [],
            'converter'     => $converter,
            'header_footer' => false,
        ]);

        return $converter->lastDocument();
    }

    private function renderClockPage(): string
    {
        return Asciidoc::convert(self::CLOCK_SOURCE, [
            'attributes'    => new AttributePolicy()->attributes(),
            'converter'     => new SiteConverter(),
            'header_footer' => false,
        ]);
    }
}
