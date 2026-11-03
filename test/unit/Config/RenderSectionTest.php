<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Config\RenderSection;

#[CoversClass(RenderSection::class)]
final class RenderSectionTest extends TestCase
{
    #[Test]
    public function fromArrayAppliesDefaults(): void
    {
        $section = RenderSection::fromArray([]);

        static::assertSame([2, 3], $section->tocLevels());
        static::assertSame('safe', $section->safeMode());
        static::assertSame(4, $section->concurrency());
        static::assertSame(2000, $section->searchExtractLimit());
        static::assertFalse($section->syntaxHighlighting());
    }

    #[Test]
    public function fromArrayReadsEveryField(): void
    {
        $section = RenderSection::fromArray([
            'tocLevels'          => [1, 2],
            'safeMode'           => 'server',
            'concurrency'        => 8,
            'searchExtractLimit' => 500,
            'syntaxHighlighting' => true,
        ]);

        static::assertSame([1, 2], $section->tocLevels());
        static::assertSame('server', $section->safeMode());
        static::assertSame(8, $section->concurrency());
        static::assertSame(500, $section->searchExtractLimit());
        static::assertTrue($section->syntaxHighlighting());
    }

    #[Test]
    public function withConcurrencyReturnsANewInstance(): void
    {
        $section = RenderSection::fromArray([]);
        $updated = $section->withConcurrency(16);

        static::assertSame(16, $updated->concurrency());
        static::assertSame(4, $section->concurrency());
        static::assertSame($section->tocLevels(), $updated->tocLevels());
        static::assertSame($section->safeMode(), $updated->safeMode());
        static::assertSame($section->searchExtractLimit(), $updated->searchExtractLimit());
        static::assertSame($section->syntaxHighlighting(), $updated->syntaxHighlighting());
    }
}
