<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Config\SchemaException;
use Webware\AsciidocPhp\MultiRepo\Config\SchemaValidator;

#[CoversClass(SchemaValidator::class)]
final class SchemaValidatorTest extends TestCase
{
    private SchemaValidator $validator;

    #[Test]
    public function additionalPropertiesFalseFails(): void
    {
        $result = $this->validator->validate(
            ['known' => 1, 'typo' => 2],
            [
                'type'                 => 'object',
                'properties'           => ['known' => ['type' => 'integer']],
                'additionalProperties' => false,
            ],
        );

        self::assertFalse($result->isValid());
        self::assertSame('/typo', $result->errors()[0]->pointer());
        self::assertStringContainsString('not an allowed property', $result->errors()[0]->message());
    }

    #[Test]
    public function additionalPropertiesSchemaIsApplied(): void
    {
        $schema = [
            'type'                 => 'object',
            'properties'           => [],
            'additionalProperties' => ['type' => 'string'],
        ];

        self::assertTrue($this->validator->validate(['a' => 'x'], $schema)->isValid());
        self::assertFalse($this->validator->validate(['a' => 1], $schema)->isValid());
    }

    #[Test]
    public function annotationKeywordsAreIgnored(): void
    {
        $schema = [
            '$schema'     => 'https://json-schema.org/draft/2020-12/schema',
            '$id'         => 'https://example.test/schema.json',
            'title'       => 'Example',
            'description' => 'An example schema.',
            'type'        => 'object',
            'properties'  => ['a' => ['type' => 'string', 'default' => 'x']],
        ];

        self::assertTrue($this->validator->validate(['a' => 'y'], $schema)->isValid());
    }

    #[Test]
    public function constKeywordFails(): void
    {
        self::assertTrue($this->validator->validate(1, ['const' => 1])->isValid());

        $result = $this->validator->validate(2, ['const' => 1]);

        self::assertFalse($result->isValid());
        self::assertStringContainsString('must equal', $result->errors()[0]->message());
    }

    #[Test]
    public function enumKeywordFails(): void
    {
        $result = $this->validator->validate('other', ['enum' => ['safe', 'secure']]);

        self::assertFalse($result->isValid());
        self::assertStringContainsString('allowed values', $result->errors()[0]->message());
    }

    #[Test]
    public function errorsAccumulateAcrossProperties(): void
    {
        $schema = [
            'type'       => 'object',
            'properties' => [
                'a' => ['type' => 'string'],
                'b' => ['type' => 'string'],
            ],
        ];

        $result = $this->validator->validate(['a' => 1, 'b' => 2], $schema);

        self::assertSame(2, $result->errorCount());
        self::assertSame('/a', $result->errors()[0]->pointer());
        self::assertSame('/b', $result->errors()[1]->pointer());
        self::assertStringContainsString('/a', $result->describe());
    }

    #[Test]
    public function itemsKeywordFails(): void
    {
        $schema = ['type' => 'array', 'items' => ['type' => 'integer']];

        self::assertTrue($this->validator->validate([1, 2], $schema)->isValid());

        $result = $this->validator->validate([1, 'two'], $schema);

        self::assertFalse($result->isValid());
        self::assertSame('/1', $result->errors()[0]->pointer());
    }

    #[Test]
    public function maximumKeywordFails(): void
    {
        self::assertTrue($this->validator->validate(6, ['type' => 'integer', 'maximum' => 6])->isValid());

        $result = $this->validator->validate(7, ['type' => 'integer', 'maximum' => 6]);

        self::assertFalse($result->isValid());
        self::assertStringContainsString('less than or equal to 6', $result->errors()[0]->message());
    }

    #[Test]
    public function minimumKeywordFails(): void
    {
        self::assertTrue($this->validator->validate(1, ['type' => 'integer', 'minimum' => 1])->isValid());

        $result = $this->validator->validate(0, ['type' => 'integer', 'minimum' => 1]);

        self::assertFalse($result->isValid());
        self::assertStringContainsString('greater than or equal to 1', $result->errors()[0]->message());
    }

    #[Test]
    public function minItemsKeywordFails(): void
    {
        self::assertTrue($this->validator->validate([1], ['type' => 'array', 'minItems' => 1])->isValid());

        $result = $this->validator->validate([], ['type' => 'array', 'minItems' => 1]);

        self::assertFalse($result->isValid());
        self::assertStringContainsString('at least 1 items', $result->errors()[0]->message());
    }

    #[Test]
    public function minLengthKeywordFails(): void
    {
        self::assertTrue($this->validator->validate('a', ['type' => 'string', 'minLength' => 1])->isValid());

        $result = $this->validator->validate('', ['type' => 'string', 'minLength' => 1]);

        self::assertFalse($result->isValid());
        self::assertStringContainsString('at least 1 characters', $result->errors()[0]->message());
    }

    #[Test]
    public function passingDocumentProducesNoErrors(): void
    {
        $schema = [
            'type'       => 'object',
            'required'   => ['name'],
            'properties' => [
                'name'  => ['type' => 'string', 'minLength' => 1],
                'count' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
                'tags'  => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'string']],
                'mode'  => ['enum' => ['a', 'b']],
            ],
        ];

        $result = $this->validator->validate(
            ['name' => 'Docs', 'count' => 5, 'tags' => ['x'], 'mode' => 'a'],
            $schema,
        );

        self::assertTrue($result->isValid());
        self::assertSame(0, $result->errorCount());
        self::assertSame([], $result->errors());
        self::assertSame('', $result->describe());
    }

    #[Test]
    public function patternKeywordFails(): void
    {
        $schema = ['type' => 'string', 'pattern' => '^[a-z]+$'];

        self::assertTrue($this->validator->validate('abc', $schema)->isValid());

        $result = $this->validator->validate('ABC', $schema);

        self::assertFalse($result->isValid());
        self::assertStringContainsString('pattern', $result->errors()[0]->message());
    }

    #[Test]
    public function patternWithSlashAndHashCharacters(): void
    {
        self::assertTrue($this->validator->validate('/docs/', ['pattern' => '^(/|/[^\\s]*/)$'])->isValid());
        self::assertFalse($this->validator->validate('docs', ['pattern' => '^(/|/[^\\s]*/)$'])->isValid());
    }

    #[Test]
    public function pointerEscaping(): void
    {
        $schema = [
            'type'                 => 'object',
            'properties'           => [],
            'additionalProperties' => ['type' => 'string'],
        ];

        $result = $this->validator->validate(['a/b~c' => 1], $schema);

        self::assertSame('/a~1b~0c', $result->errors()[0]->pointer());
    }

    #[Test]
    public function propertiesKeywordFailsAtTheChildPointer(): void
    {
        $result = $this->validator->validate(
            ['site' => ['name' => 5]],
            [
                'type'       => 'object',
                'properties' => [
                    'site' => [
                        'type'       => 'object',
                        'properties' => ['name' => ['type' => 'string']],
                    ],
                ],
            ],
        );

        self::assertFalse($result->isValid());
        self::assertSame('/site/name', $result->errors()[0]->pointer());
    }

    #[Test]
    public function refToLocalDefinitionIsResolved(): void
    {
        $schema = [
            'type'       => 'object',
            'properties' => ['project' => ['$ref' => '#/$defs/project']],
            '$defs'      => [
                'project' => [
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => ['slug' => ['type' => 'string']],
                ],
            ],
        ];

        self::assertTrue($this->validator->validate(['project' => ['slug' => 'a']], $schema)->isValid());

        $result = $this->validator->validate(['project' => ['slug' => 5]], $schema);

        self::assertFalse($result->isValid());
        self::assertSame('/project/slug', $result->errors()[0]->pointer());
    }

    #[Test]
    public function remoteRefRaises(): void
    {
        $this->expectException(SchemaException::class);

        $this->validator->validate('x', ['$ref' => 'https://example.test/schema.json']);
    }

    #[Test]
    public function requiredKeywordFails(): void
    {
        $result = $this->validator->validate([], ['type' => 'object', 'required' => ['name']]);

        self::assertFalse($result->isValid());
        self::assertStringContainsString('"name"', $result->errors()[0]->message());
    }

    #[Test]
    public function typeKeywordFails(): void
    {
        $result = $this->validator->validate('text', ['type' => 'integer']);

        self::assertFalse($result->isValid());
        self::assertSame(1, $result->errorCount());
        self::assertSame('', $result->errors()[0]->pointer());
        self::assertStringContainsString('integer', $result->errors()[0]->message());
    }

    #[Test]
    public function typeListAcceptsAnyListedType(): void
    {
        self::assertTrue($this->validator->validate('text', ['type' => ['string', 'null']])->isValid());
        self::assertTrue($this->validator->validate(null, ['type' => ['string', 'null']])->isValid());
        self::assertFalse($this->validator->validate(1, ['type' => ['string', 'null']])->isValid());
    }

    #[Test]
    public function unknownKeywordInNestedSchemaRaises(): void
    {
        $this->expectException(SchemaException::class);

        $this->validator->validate(
            ['a' => 1],
            ['type' => 'object', 'properties' => ['a' => ['exclusiveMinimum' => 0]]],
        );
    }

    #[Test]
    public function unknownKeywordRaises(): void
    {
        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('Unsupported JSON Schema keyword "oneOf"');

        $this->validator->validate('x', ['oneOf' => []]);
    }

    #[Test]
    public function unknownTypeRaises(): void
    {
        $this->expectException(SchemaException::class);

        $this->validator->validate('x', ['type' => 'widget']);
    }

    #[Test]
    public function unresolvableRefRaises(): void
    {
        $this->expectException(SchemaException::class);

        $this->validator->validate('x', ['$ref' => '#/$defs/missing']);
    }

    protected function setUp(): void
    {
        $this->validator = new SchemaValidator();
    }
}
