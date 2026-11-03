<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Source\SourceFormat;

#[CoversClass(SourceFormat::class)]
#[CoversMethod(SourceFormat::class, 'extension')]
#[CoversMethod(SourceFormat::class, 'fromPath')]
#[CoversMethod(SourceFormat::class, 'isAsciiDoc')]
final class SourceFormatTest extends TestCase
{
    /**
     * @return iterable<string, array{string, SourceFormat}>
     */
    public static function extensions(): iterable
    {
        yield 'adoc' => ['docs/v1/index.adoc', SourceFormat::AsciiDoc];
        yield 'asciidoc' => ['docs/v1/index.asciidoc', SourceFormat::AsciiDoc];
        yield 'upper adoc' => ['docs/v1/INDEX.ADOC', SourceFormat::AsciiDoc];
        yield 'markdown' => ['docs/v1/index.md', SourceFormat::Markdown];
        yield 'markdown long' => ['docs/v1/index.markdown', SourceFormat::Markdown];
        yield 'markdown mdown' => ['docs/v1/index.mdown', SourceFormat::Markdown];
        yield 'html' => ['docs/v1/index.html', SourceFormat::Unknown];
        yield 'no extension' => ['docs/v1/README', SourceFormat::Unknown];
        yield 'dot in the path' => ['docs/v1.2/index', SourceFormat::Unknown];
    }

    #[Test]
    public function onlyAsciiDocIsAccepted(): void
    {
        self::assertTrue(SourceFormat::AsciiDoc->isAsciiDoc());
        self::assertFalse(SourceFormat::Markdown->isAsciiDoc());
        self::assertFalse(SourceFormat::Unknown->isAsciiDoc());
    }

    #[Test]
    public function theEnumValueIsTheLowercaseFormatName(): void
    {
        self::assertSame('asciidoc', SourceFormat::AsciiDoc->value);
        self::assertSame('markdown', SourceFormat::Markdown->value);
        self::assertSame('unknown', SourceFormat::Unknown->value);
    }

    #[Test]
    public function theExtensionComesFromTheLastDot(): void
    {
        self::assertSame('.adoc', SourceFormat::extension('docs/v1.2/index.adoc'));
    }

    #[Test]
    public function theExtensionIsLowercasedAndKeepsTheDot(): void
    {
        self::assertSame('.adoc', SourceFormat::extension('docs/INDEX.ADOC'));
        self::assertSame('', SourceFormat::extension('docs/README'));
    }

    #[Test]
    #[DataProvider('extensions')]
    public function theFormatComesFromTheExtension(string $path, SourceFormat $expected): void
    {
        self::assertSame($expected, SourceFormat::fromPath($path));
    }
}
