<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Test\Unit\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhpMultiRepo\Config\RenderSection;

#[CoversClass(RenderSection::class)]
final class RenderSectionTest extends TestCase
{
    public function testFromArrayAppliesDefaults(): void
    {
        $section = RenderSection::fromArray([]);

        self::assertSame([2, 3], $section->tocLevels());
        self::assertSame('safe', $section->safeMode());
        self::assertSame(4, $section->concurrency());
        self::assertSame(2000, $section->searchExtractLimit());
        self::assertFalse($section->syntaxHighlighting());
    }

    public function testFromArrayReadsEveryField(): void
    {
        $section = RenderSection::fromArray([
            'tocLevels'          => [1, 2],
            'safeMode'           => 'server',
            'concurrency'        => 8,
            'searchExtractLimit' => 500,
            'syntaxHighlighting' => true,
        ]);

        self::assertSame([1, 2], $section->tocLevels());
        self::assertSame('server', $section->safeMode());
        self::assertSame(8, $section->concurrency());
        self::assertSame(500, $section->searchExtractLimit());
        self::assertTrue($section->syntaxHighlighting());
    }

    public function testWithConcurrencyReturnsANewInstance(): void
    {
        $section = RenderSection::fromArray([]);
        $updated = $section->withConcurrency(16);

        self::assertSame(16, $updated->concurrency());
        self::assertSame(4, $section->concurrency());
        self::assertSame($section->tocLevels(), $updated->tocLevels());
        self::assertSame($section->safeMode(), $updated->safeMode());
        self::assertSame($section->searchExtractLimit(), $updated->searchExtractLimit());
        self::assertSame($section->syntaxHighlighting(), $updated->syntaxHighlighting());
    }
}
