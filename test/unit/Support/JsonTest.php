<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhpMultiRepo\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhpMultiRepo\Support\Json;
use Webware\AsciidocPhpMultiRepo\Support\JsonDecodeException;

use function str_contains;
use function str_ends_with;

#[CoversClass(Json::class)]
final class JsonTest extends TestCase
{
    private Json $json;

    public function testDecodeObjectRequiresAnObject(): void
    {
        $this->expectException(JsonDecodeException::class);

        $this->json->decodeObject('[1, 2]', 'site.json');
    }

    public function testDecodeObjectReturnsTheDocument(): void
    {
        self::assertSame(['a' => 1], $this->json->decodeObject('{"a": 1}', 'site.json'));
    }

    public function testDecodeReturnsAssociativeArrays(): void
    {
        self::assertSame(
            ['site' => ['name' => 'Docs']],
            $this->json->decode('{"site": {"name": "Docs"}}'),
        );
    }

    public function testEmptyDocumentReportsFirstByte(): void
    {
        $this->expectException(JsonDecodeException::class);

        $this->json->decode('', 'site.json');
    }

    public function testEncodeCompactHasNoInsignificantWhitespace(): void
    {
        self::assertSame("{\"a\":1,\"b\":[1,2]}\n", $this->json->encodeCompact(['a' => 1, 'b' => [1, 2]]));
    }

    public function testEncodeDoesNotEscapeSlashes(): void
    {
        $encoded = $this->json->encode(['url' => 'https://example.test/docs/v1']);

        self::assertStringContainsString('https://example.test/docs/v1', $encoded);
        self::assertFalse(str_contains($encoded, '\\/'));
    }

    public function testEncodeEndsWithSingleNewline(): void
    {
        $encoded = $this->json->encode(['a' => 1]);

        self::assertTrue(str_ends_with($encoded, "}\n"));
        self::assertFalse(str_ends_with($encoded, "}\n\n"));
    }

    public function testEncodeIsDeterministic(): void
    {
        $value = ['b' => 1, 'a' => ['z' => true, 'y' => null]];

        self::assertSame($this->json->encode($value), $this->json->encode($value));
    }

    public function testEncodePreservesZeroFraction(): void
    {
        self::assertStringContainsString('1.0', $this->json->encodeCompact(['value' => 1.0]));
    }

    public function testEncodeUsesFourSpaceIndentation(): void
    {
        $encoded = $this->json->encode(['a' => 1]);

        self::assertStringContainsString("\n    \"a\": 1", $encoded);
    }

    public function testInvalidJsonInsideArrayReportsIndexPointer(): void
    {
        try {
            $this->json->decode('{"projects": [1, ]}', 'manifest.json');
            self::fail('Invalid JSON should raise a decode exception.');
        } catch (JsonDecodeException $exception) {
            self::assertSame('/projects/1', $exception->pointer());
            self::assertSame(17, $exception->offset());
        }
    }

    public function testInvalidJsonRaisesTypedExceptionWithOffsetAndPointer(): void
    {
        try {
            $this->json->decode('{"site": }', 'site.json');
            self::fail('Invalid JSON should raise a decode exception.');
        } catch (JsonDecodeException $exception) {
            self::assertSame('site.json', $exception->source());
            self::assertSame('/site', $exception->pointer());
            self::assertSame(9, $exception->offset());
            self::assertStringContainsString('byte offset 9', $exception->getMessage());
            self::assertStringContainsString('/site', $exception->getMessage());
        }
    }

    public function testTruncatedDocumentReportsEndOfInput(): void
    {
        try {
            $this->json->decode('{"site": ', 'site.json');
            self::fail('Invalid JSON should raise a decode exception.');
        } catch (JsonDecodeException $exception) {
            self::assertSame(9, $exception->offset());
            self::assertSame('/site', $exception->pointer());
        }
    }

    protected function setUp(): void
    {
        $this->json = new Json();
    }
}
