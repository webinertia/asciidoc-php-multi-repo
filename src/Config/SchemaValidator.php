<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Config;

use function array_diff;
use function array_is_list;
use function array_key_exists;
use function array_keys;
use function count;
use function explode;
use function get_debug_type;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function mb_strlen;
use function preg_match;
use function sprintf;
use function str_starts_with;
use function strtr;
use function substr;

/**
 * A dependency-free validator for the subset of JSON Schema this package publishes.
 *
 * Supported assertion keywords: type, required, properties, additionalProperties, enum, const,
 * pattern, minimum, maximum, items, minLength, minItems, and $ref to a local $defs entry. Annotation
 * keywords ($schema, $id, title, description, default, $defs, examples) are accepted and ignored.
 * Any other keyword raises SchemaException so that a typo in a schema cannot silently disable a
 * check.
 */
final class SchemaValidator
{
    private const int MAX_DEPTH = 64;

    /**
     * Keywords that carry no assertion.
     */
    private const array ANNOTATION_KEYWORDS = [
        '$schema',
        '$id',
        '$comment',
        '$defs',
        'title',
        'description',
        'default',
        'examples',
        'deprecated',
        'readOnly',
        'writeOnly',
    ];

    /**
     * @param array<string, mixed> $schema
     */
    public function validate(mixed $document, array $schema): ValidationResult
    {
        return new ValidationResult($this->validateNode($document, $schema, '', $schema, 0));
    }

    /**
     * @param array<array-key, mixed> $value
     * @param array<string, mixed>    $schema
     * @param array<string, mixed>    $root
     *
     * @return list<ValidationError>
     */
    private function arrayErrors(
        array $value,
        array $schema,
        string $pointer,
        array $root,
        int $depth,
    ): array {
        if (! array_is_list($value)) {
            return [];
        }

        $errors = [];

        if (array_key_exists('minItems', $schema)) {
            $minimum = $schema['minItems'];
            if (! is_int($minimum)) {
                throw new SchemaException('The minItems keyword must be an integer.', $pointer);
            }

            if (count($value) < $minimum) {
                $errors[] = new ValidationError($pointer, sprintf(
                    'must contain at least %d items',
                    $minimum,
                ));
            }
        }

        if (array_key_exists('items', $schema)) {
            $items = $this->subSchema($schema['items'], 'items', $pointer);

            foreach ($value as $index => $entry) {
                foreach ($this->validateNode($entry, $items, "{$pointer}/{$index}", $root, $depth + 1) as $error) {
                    $errors[] = $error;
                }
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function assertKnownKeywords(array $schema, string $pointer): void
    {
        foreach (array_keys($schema) as $keyword) {
            $keyword = (string) $keyword;

            if (in_array($keyword, self::ANNOTATION_KEYWORDS, true)) {
                continue;
            }

            if (in_array(
                $keyword,
                [
                    'type',
                    'required',
                    'properties',
                    'additionalProperties',
                    'enum',
                    'const',
                    'pattern',
                    'minimum',
                    'maximum',
                    'items',
                    'minLength',
                    'minItems',
                    '$ref',
                ],
                true,
            )) {
                continue;
            }

            throw new SchemaException(sprintf(
                'Unsupported JSON Schema keyword "%s" at "%s".',
                $keyword,
                '' === $pointer ? '/' : $pointer,
            ), $pointer);
        }
    }

    private function childPointer(string $pointer, string $key): string
    {
        return $pointer . '/' . strtr($key, ['~' => '~0', '/' => '~1']);
    }

    private function describeValue(mixed $value): string
    {
        return is_string($value) ? '"' . $value . '"' : $this->typeName($value);
    }

    /**
     * @return list<ValidationError>
     */
    private function enumErrors(mixed $value, mixed $enum, string $pointer): array
    {
        if (! is_array($enum) || ! array_is_list($enum)) {
            throw new SchemaException('The enum keyword must be a list.', $pointer);
        }

        foreach ($enum as $candidate) {
            if ($candidate === $value) {
                return [];
            }
        }

        return [new ValidationError($pointer, 'must be one of the allowed values')];
    }

    private function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'object'  => is_array($value) && ([] === $value || ! array_is_list($value)),
            'array'   => is_array($value) && ([] === $value || array_is_list($value)),
            'string'  => is_string($value),
            'integer' => is_int($value),
            'number'  => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'null'    => null === $value,
            default   => throw new SchemaException(sprintf('Unsupported JSON Schema type "%s".', $type)),
        };
    }

    /**
     * @param list<ValidationError> $errors
     * @param list<ValidationError> $additional
     *
     * @return list<ValidationError>
     */
    private function merge(array $errors, array $additional): array
    {
        foreach ($additional as $error) {
            $errors[] = $error;
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return list<ValidationError>
     */
    private function numberErrors(int|float $value, array $schema, string $pointer): array
    {
        $errors = [];

        if (array_key_exists('minimum', $schema)) {
            $minimum = $schema['minimum'];
            if (! is_int($minimum) && ! is_float($minimum)) {
                throw new SchemaException('The minimum keyword must be a number.', $pointer);
            }

            if ($value < $minimum) {
                $errors[] = new ValidationError($pointer, sprintf(
                    'must be greater than or equal to %s',
                    (string) $minimum,
                ));
            }
        }

        if (array_key_exists('maximum', $schema)) {
            $maximum = $schema['maximum'];
            if (! is_int($maximum) && ! is_float($maximum)) {
                throw new SchemaException('The maximum keyword must be a number.', $pointer);
            }

            if ($value > $maximum) {
                $errors[] = new ValidationError($pointer, sprintf(
                    'must be less than or equal to %s',
                    (string) $maximum,
                ));
            }
        }

        return $errors;
    }

    /**
     * @param array<array-key, mixed> $value
     * @param array<string, mixed>    $schema
     * @param array<string, mixed>    $root
     *
     * @return list<ValidationError>
     */
    private function objectErrors(
        array $value,
        array $schema,
        string $pointer,
        array $root,
        int $depth,
    ): array {
        if (array_is_list($value) && [] !== $value) {
            return [];
        }

        $errors = [];

        if (array_key_exists('required', $schema)) {
            $required = $schema['required'];
            if (! is_array($required) || ! array_is_list($required)) {
                throw new SchemaException('The required keyword must be a list of property names.', $pointer);
            }

            foreach ($required as $name) {
                if (! is_string($name)) {
                    throw new SchemaException('The required keyword must contain only strings.', $pointer);
                }

                if (! array_key_exists($name, $value)) {
                    $errors[] = new ValidationError($pointer, sprintf('must contain the property "%s"', $name));
                }
            }
        }

        $properties = [];
        if (array_key_exists('properties', $schema)) {
            $declared = $schema['properties'];
            if (! is_array($declared)) {
                throw new SchemaException('The properties keyword must be an object of schemas.', $pointer);
            }

            foreach ($declared as $name => $propertySchema) {
                if (! is_array($propertySchema)) {
                    throw new SchemaException(
                        sprintf('The schema for property "%s" must be an object.', $name),
                        $pointer,
                    );
                }

                $properties[(string) $name] = $this->stringKeys($propertySchema);
            }
        }

        foreach ($properties as $name => $propertySchema) {
            if (! array_key_exists($name, $value)) {
                continue;
            }

            foreach ($this->validateNode(
                $value[$name],
                $propertySchema,
                $this->childPointer($pointer, $name),
                $root,
                $depth + 1,
            ) as $error) {
                $errors[] = $error;
            }
        }

        if (! array_key_exists('additionalProperties', $schema)) {
            return $errors;
        }

        $additional = $schema['additionalProperties'];
        $extras     = array_diff(array_keys($value), array_keys($properties));

        if (false === $additional) {
            foreach ($extras as $name) {
                $errors[] = new ValidationError(
                    $this->childPointer($pointer, (string) $name),
                    'is not an allowed property',
                );
            }

            return $errors;
        }

        if (! is_array($additional)) {
            throw new SchemaException('The additionalProperties keyword must be false or a schema object.', $pointer);
        }

        $additionalSchema = $this->stringKeys($additional);

        foreach ($extras as $name) {
            foreach ($this->validateNode(
                $value[$name],
                $additionalSchema,
                $this->childPointer($pointer, (string) $name),
                $root,
                $depth + 1,
            ) as $error) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $root
     *
     * @return array<string, mixed>
     */
    private function resolveReference(string $reference, array $root): array
    {
        if (! str_starts_with($reference, '#/')) {
            throw new SchemaException(sprintf('Only local $ref values are supported, got "%s".', $reference));
        }

        $segments = explode('/', substr($reference, 2));
        $current  = $root;

        foreach ($segments as $segment) {
            $segment = strtr($segment, ['~1' => '/', '~0' => '~']);

            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                throw new SchemaException(sprintf('The $ref "%s" cannot be resolved.', $reference));
            }

            $current = $current[$segment];
        }

        if (! is_array($current)) {
            throw new SchemaException(sprintf('The $ref "%s" does not point at a schema object.', $reference));
        }

        /** @var array<string, mixed> $current */
        return $current;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return list<ValidationError>
     */
    private function stringErrors(string $value, array $schema, string $pointer): array
    {
        $errors = [];

        if (array_key_exists('minLength', $schema)) {
            $minimum = $schema['minLength'];
            if (! is_int($minimum)) {
                throw new SchemaException('The minLength keyword must be an integer.', $pointer);
            }

            if (mb_strlen($value) < $minimum) {
                $errors[] = new ValidationError($pointer, sprintf(
                    'must be at least %d characters long',
                    $minimum,
                ));
            }
        }

        if (array_key_exists('pattern', $schema)) {
            $pattern = $schema['pattern'];
            if (! is_string($pattern)) {
                throw new SchemaException('The pattern keyword must be a string.', $pointer);
            }

            $result = @preg_match('#' . strtr($pattern, ['#' => '\\#']) . '#u', $value);
            if (false === $result) {
                throw new SchemaException(
                    sprintf('The pattern "%s" is not a valid regular expression.', $pattern),
                    $pointer,
                );
            }

            if (0 === $result) {
                $errors[] = new ValidationError($pointer, sprintf(
                    'must match the pattern %s',
                    $pattern,
                ));
            }
        }

        return $errors;
    }

    /**
     * @param array<array-key, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function stringKeys(array $schema): array
    {
        $normalised = [];

        foreach ($schema as $key => $value) {
            $normalised[(string) $key] = $value;
        }

        return $normalised;
    }

    /**
     * Narrows a keyword value that must be a schema object. Keys of a decoded JSON object are
     * always strings, but the decoded array type does not say so.
     *
     * @return array<string, mixed>
     */
    private function subSchema(mixed $candidate, string $keyword, string $pointer): array
    {
        if (! is_array($candidate)) {
            throw new SchemaException(sprintf('The %s keyword must be a schema object.', $keyword), $pointer);
        }

        return $this->stringKeys($candidate);
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return list<ValidationError>
     */
    private function typeErrors(mixed $value, array $schema, string $pointer): array
    {
        if (! array_key_exists('type', $schema)) {
            return [];
        }

        $type  = $schema['type'];
        $types = is_string($type) ? [$type] : $type;

        if (! is_array($types)) {
            throw new SchemaException('The type keyword must be a string or a list of strings.', $pointer);
        }

        foreach ($types as $candidate) {
            if (! is_string($candidate)) {
                throw new SchemaException('The type keyword must contain only strings.', $pointer);
            }

            if ($this->matchesType($value, $candidate)) {
                return [];
            }
        }

        $names = [];
        foreach ($types as $candidate) {
            $names[] = $candidate;
        }

        return [new ValidationError($pointer, sprintf(
            'must be of type %s, got %s',
            implode(' or ', $names),
            $this->typeName($value),
        ))];
    }

    private function typeName(mixed $value): string
    {
        if (is_array($value)) {
            return [] === $value || ! array_is_list($value) ? 'object' : 'array';
        }

        return get_debug_type($value);
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $root
     *
     * @return list<ValidationError>
     */
    private function validateNode(
        mixed $value,
        array $schema,
        string $pointer,
        array $root,
        int $depth,
    ): array {
        if ($depth > self::MAX_DEPTH) {
            throw new SchemaException('Schema nesting is too deep to validate.', $pointer);
        }

        $this->assertKnownKeywords($schema, $pointer);

        if (array_key_exists('$ref', $schema)) {
            $reference = $schema['$ref'];
            if (! is_string($reference)) {
                throw new SchemaException('The $ref keyword must be a string.', $pointer);
            }

            return $this->validateNode(
                $value,
                $this->resolveReference($reference, $root),
                $pointer,
                $root,
                $depth + 1,
            );
        }

        $errors = [];

        foreach ($this->typeErrors($value, $schema, $pointer) as $error) {
            $errors[] = $error;
        }

        if (array_key_exists('const', $schema) && $value !== $schema['const']) {
            $errors[] = new ValidationError($pointer, sprintf(
                'must equal %s',
                $this->describeValue($schema['const']),
            ));
        }

        if (array_key_exists('enum', $schema)) {
            $errors = $this->merge($errors, $this->enumErrors($value, $schema['enum'], $pointer));
        }

        if (is_string($value)) {
            $errors = $this->merge($errors, $this->stringErrors($value, $schema, $pointer));
        }

        if (is_int($value) || is_float($value)) {
            $errors = $this->merge($errors, $this->numberErrors($value, $schema, $pointer));
        }

        if (is_array($value)) {
            $errors = $this->merge($errors, $this->arrayErrors($value, $schema, $pointer, $root, $depth));
            $errors = $this->merge($errors, $this->objectErrors($value, $schema, $pointer, $root, $depth));
        }

        return $errors;
    }
}
