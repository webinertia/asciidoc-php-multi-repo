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

        static::assertFalse($result->isValid());
        static::assertSame('/typo', $result->errors()[0]->pointer());
        static::assertStringContainsString('not an allowed property', $result->errors()[0]->message());
    }

    #[Test]
    public function additionalPropertiesSchemaIsApplied(): void
    {
        $schema = [
            'type'                 => 'object',
            'properties'           => [],
            'additionalProperties' => ['type' => 'string'],
        ];

        static::assertTrue($this->validator->validate(['a' => 'x'], $schema)->isValid());
        static::assertFalse($this->validator->validate(['a' => 1], $schema)->isValid());
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

        static::assertTrue($this->validator->validate(['a' => 'y'], $schema)->isValid());
    }

    #[Test]
    public function constKeywordFails(): void
    {
        static::assertTrue($this->validator->validate(1, ['const' => 1])->isValid());

        $result = $this->validator->validate(2, ['const' => 1]);

        static::assertFalse($result->isValid());
        static::assertStringContainsString('must equal', $result->errors()[0]->message());
    }

    #[Test]
    public function enumKeywordFails(): void
    {
        $result = $this->validator->validate('other', ['enum' => ['safe', 'secure']]);

        static::assertFalse($result->isValid());
        static::assertStringContainsString('allowed values', $result->errors()[0]->message());
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

        static::assertSame(2, $result->errorCount());
        static::assertSame('/a', $result->errors()[0]->pointer());
        static::assertSame('/b', $result->errors()[1]->pointer());
        static::assertStringContainsString('/a', $result->describe());
    }

    #[Test]
    public function itemsKeywordFails(): void
    {
        $schema = ['type' => 'array', 'items' => ['type' => 'integer']];

        static::assertTrue($this->validator->validate([1, 2], $schema)->isValid());

        $result = $this->validator->validate([1, 'two'], $schema);

        static::assertFalse($result->isValid());
        static::assertSame('/1', $result->errors()[0]->pointer());
    }

    #[Test]
    public function maximumKeywordFails(): void
    {
        static::assertTrue($this->validator->validate(6, ['type' => 'integer', 'maximum' => 6])->isValid());

        $result = $this->validator->validate(7, ['type' => 'integer', 'maximum' => 6]);

        static::assertFalse($result->isValid());
        static::assertStringContainsString('less than or equal to 6', $result->errors()[0]->message());
    }

    #[Test]
    public function minimumKeywordFails(): void
    {
        static::assertTrue($this->validator->validate(1, ['type' => 'integer', 'minimum' => 1])->isValid());

        $result = $this->validator->validate(0, ['type' => 'integer', 'minimum' => 1]);

        static::assertFalse($result->isValid());
        static::assertStringContainsString('greater than or equal to 1', $result->errors()[0]->message());
    }

    #[Test]
    public function minItemsKeywordFails(): void
    {
        static::assertTrue($this->validator->validate([1], ['type' => 'array', 'minItems' => 1])->isValid());

        $result = $this->validator->validate([], ['type' => 'array', 'minItems' => 1]);

        static::assertFalse($result->isValid());
        static::assertStringContainsString('at least 1 items', $result->errors()[0]->message());
    }

    #[Test]
    public function minLengthKeywordFails(): void
    {
        static::assertTrue($this->validator->validate('a', ['type' => 'string', 'minLength' => 1])->isValid());

        $result = $this->validator->validate('', ['type' => 'string', 'minLength' => 1]);

        static::assertFalse($result->isValid());
        static::assertStringContainsString('at least 1 characters', $result->errors()[0]->message());
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

        static::assertTrue($result->isValid());
        static::assertSame(0, $result->errorCount());
        static::assertSame([], $result->errors());
        static::assertSame('', $result->describe());
    }

    #[Test]
    public function patternKeywordFails(): void
    {
        $schema = ['type' => 'string', 'pattern' => '^[a-z]+$'];

        static::assertTrue($this->validator->validate('abc', $schema)->isValid());

        $result = $this->validator->validate('ABC', $schema);

        static::assertFalse($result->isValid());
        static::assertStringContainsString('pattern', $result->errors()[0]->message());
    }

    #[Test]
    public function patternWithSlashAndHashCharacters(): void
    {
        static::assertTrue($this->validator->validate('/docs/', ['pattern' => '^(/|/[^\\s]*/)$'])->isValid());
        static::assertFalse($this->validator->validate('docs', ['pattern' => '^(/|/[^\\s]*/)$'])->isValid());
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

        static::assertSame('/a~1b~0c', $result->errors()[0]->pointer());
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

        static::assertFalse($result->isValid());
        static::assertSame('/site/name', $result->errors()[0]->pointer());
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

        static::assertTrue($this->validator->validate(['project' => ['slug' => 'a']], $schema)->isValid());

        $result = $this->validator->validate(['project' => ['slug' => 5]], $schema);

        static::assertFalse($result->isValid());
        static::assertSame('/project/slug', $result->errors()[0]->pointer());
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

        static::assertFalse($result->isValid());
        static::assertStringContainsString('"name"', $result->errors()[0]->message());
    }

    #[Test]
    public function typeKeywordFails(): void
    {
        $result = $this->validator->validate('text', ['type' => 'integer']);

        static::assertFalse($result->isValid());
        static::assertSame(1, $result->errorCount());
        static::assertSame('', $result->errors()[0]->pointer());
        static::assertStringContainsString('integer', $result->errors()[0]->message());
    }

    #[Test]
    public function typeListAcceptsAnyListedType(): void
    {
        static::assertTrue($this->validator->validate('text', ['type' => ['string', 'null']])->isValid());
        static::assertTrue($this->validator->validate(null, ['type' => ['string', 'null']])->isValid());
        static::assertFalse($this->validator->validate(1, ['type' => ['string', 'null']])->isValid());
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
