<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Render;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
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

    public function testHighlightingLeavesTheHighlighterAttributeAlone(): void
    {
        $policy = new AttributePolicy(syntaxHighlighting: true);

        self::assertTrue($policy->syntaxHighlighting());
        self::assertArrayNotHasKey('source-highlighter', $policy->attributes());
    }

    public function testRenderingTwiceAcrossAClockChangeProducesIdenticalBytes(): void
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

        self::assertSame($first, $second);
    }

    public function testTheClockAttributesCarryNoValueInTheDocument(): void
    {
        $document = $this->document(new AttributePolicy());

        self::assertNotNull($document);

        foreach (AttributePolicy::CLOCK_ATTRIBUTES as $name) {
            self::assertSame('', $document->getAttribute($name));
        }
    }

    public function testTheConverterChromeIsSwitchedOff(): void
    {
        $attributes = new AttributePolicy()->attributes();

        self::assertFalse($attributes['stylesheet']);
        self::assertSame('', $attributes['noembed']);
        self::assertFalse($attributes['linkcss']);
        self::assertFalse($attributes['toc']);
        self::assertFalse($attributes['docinfo']);
        self::assertFalse($attributes['source-highlighter']);
    }

    public function testTheSixClockAttributesArePinnedToAnEmptyString(): void
    {
        $attributes = new AttributePolicy()->attributes();

        self::assertSame(
            ['docdate', 'doctime', 'docdatetime', 'localdate', 'localtime', 'localdatetime'],
            AttributePolicy::CLOCK_ATTRIBUTES,
        );

        foreach (AttributePolicy::CLOCK_ATTRIBUTES as $name) {
            self::assertArrayHasKey($name, $attributes);
            self::assertSame('', $attributes[$name]);
        }
    }

    public function testWithoutThePolicyTheClockAttributesCarryTheBuildDate(): void
    {
        $document = $this->document(null);

        self::assertNotNull($document);
        self::assertNotSame('', $document->getAttribute('docdate'));
        self::assertNotSame('', $document->getAttribute('doctime'));
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
