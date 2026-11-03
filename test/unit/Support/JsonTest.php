<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Support\Json;
use Webware\AsciidocPhp\MultiRepo\Support\JsonDecodeException;

use function str_contains;
use function str_ends_with;

#[CoversClass(Json::class)]
final class JsonTest extends TestCase
{
    private Json $json;

    #[Test]
    public function decodeObjectRequiresAnObject(): void
    {
        $this->expectException(JsonDecodeException::class);

        $this->json->decodeObject('[1, 2]', 'site.json');
    }

    #[Test]
    public function decodeObjectReturnsTheDocument(): void
    {
        static::assertSame(['a' => 1], $this->json->decodeObject('{"a": 1}', 'site.json'));
    }

    #[Test]
    public function decodeReturnsAssociativeArrays(): void
    {
        static::assertSame(
            ['site' => ['name' => 'Docs']],
            $this->json->decode('{"site": {"name": "Docs"}}'),
        );
    }

    #[Test]
    public function emptyDocumentReportsFirstByte(): void
    {
        $this->expectException(JsonDecodeException::class);

        $this->json->decode('', 'site.json');
    }

    #[Test]
    public function encodeCompactHasNoInsignificantWhitespace(): void
    {
        static::assertSame("{\"a\":1,\"b\":[1,2]}\n", $this->json->encodeCompact(['a' => 1, 'b' => [1, 2]]));
    }

    #[Test]
    public function encodeDoesNotEscapeSlashes(): void
    {
        $encoded = $this->json->encode(['url' => 'https://example.test/docs/v1']);

        static::assertStringContainsString('https://example.test/docs/v1', $encoded);
        static::assertFalse(str_contains($encoded, '\\/'));
    }

    #[Test]
    public function encodeEndsWithSingleNewline(): void
    {
        $encoded = $this->json->encode(['a' => 1]);

        static::assertTrue(str_ends_with($encoded, "}\n"));
        static::assertFalse(str_ends_with($encoded, "}\n\n"));
    }

    #[Test]
    public function encodeIsDeterministic(): void
    {
        $value = ['b' => 1, 'a' => ['z' => true, 'y' => null]];

        static::assertSame($this->json->encode($value), $this->json->encode($value));
    }

    #[Test]
    public function encodePreservesZeroFraction(): void
    {
        static::assertStringContainsString('1.0', $this->json->encodeCompact(['value' => 1.0]));
    }

    #[Test]
    public function encodeUsesFourSpaceIndentation(): void
    {
        $encoded = $this->json->encode(['a' => 1]);

        static::assertStringContainsString("\n    \"a\": 1", $encoded);
    }

    #[Test]
    public function invalidJsonInsideArrayReportsIndexPointer(): void
    {
        try {
            $this->json->decode('{"projects": [1, ]}', 'manifest.json');
            self::fail('Invalid JSON should raise a decode exception.');
        } catch (JsonDecodeException $exception) {
            static::assertSame('/projects/1', $exception->pointer());
            static::assertSame(17, $exception->offset());
        }
    }

    #[Test]
    public function invalidJsonRaisesTypedExceptionWithOffsetAndPointer(): void
    {
        try {
            $this->json->decode('{"site": }', 'site.json');
            self::fail('Invalid JSON should raise a decode exception.');
        } catch (JsonDecodeException $exception) {
            static::assertSame('site.json', $exception->source());
            static::assertSame('/site', $exception->pointer());
            static::assertSame(9, $exception->offset());
            static::assertStringContainsString('byte offset 9', $exception->getMessage());
            static::assertStringContainsString('/site', $exception->getMessage());
        }
    }

    #[Test]
    public function truncatedDocumentReportsEndOfInput(): void
    {
        try {
            $this->json->decode('{"site": ', 'site.json');
            self::fail('Invalid JSON should raise a decode exception.');
        } catch (JsonDecodeException $exception) {
            static::assertSame(9, $exception->offset());
            static::assertSame('/site', $exception->pointer());
        }
    }

    protected function setUp(): void
    {
        $this->json = new Json();
    }
}
