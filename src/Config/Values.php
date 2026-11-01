<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Config;

use InvalidArgumentException;

use function array_is_list;
use function array_key_exists;
use function get_debug_type;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function sprintf;

/**
 * Typed readers for configuration arrays.
 *
 * Configuration documents are validated against the JSON Schema before these readers run, so a
 * mismatch here means the schema and the value objects disagree. Every failure therefore raises
 * rather than silently substituting a default.
 */
final class Values
{
    private function __construct() {}

    /**
     * @param array<string, mixed> $data
     */
    public static function boolean(array $data, string $key, bool $default): bool
    {
        if (! array_key_exists($key, $data)) {
            return $default;
        }

        $value = $data[$key];

        if (! is_bool($value)) {
            throw new InvalidArgumentException(sprintf(
                'Configuration key "%s" must be a boolean, got %s.',
                $key,
                get_debug_type($value),
            ));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function integer(array $data, string $key, int $default): int
    {
        if (! array_key_exists($key, $data)) {
            return $default;
        }

        $value = $data[$key];

        if (! is_int($value)) {
            throw new InvalidArgumentException(sprintf(
                'Configuration key "%s" must be an integer, got %s.',
                $key,
                get_debug_type($value),
            ));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<int>            $default
     *
     * @return list<int>
     */
    public static function integerList(array $data, string $key, array $default): array
    {
        if (! array_key_exists($key, $data)) {
            return $default;
        }

        $value = $data[$key];

        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidArgumentException(sprintf('Configuration key "%s" must be a list.', $key));
        }

        $list = [];
        foreach ($value as $entry) {
            if (! is_int($entry)) {
                throw new InvalidArgumentException(sprintf(
                    'Configuration key "%s" must contain only integers.',
                    $key,
                ));
            }

            $list[] = $entry;
        }

        return $list;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function optionalInteger(array $data, string $key): ?int
    {
        if (! array_key_exists($key, $data) || null === $data[$key]) {
            return null;
        }

        $value = $data[$key];

        if (! is_int($value)) {
            throw new InvalidArgumentException(sprintf(
                'Configuration key "%s" must be an integer, got %s.',
                $key,
                get_debug_type($value),
            ));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function optionalString(array $data, string $key): ?string
    {
        if (! array_key_exists($key, $data) || null === $data[$key]) {
            return null;
        }

        return self::asString($data[$key], $key);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function requiredString(array $data, string $key): string
    {
        if (! array_key_exists($key, $data)) {
            throw new InvalidArgumentException(sprintf('Missing required configuration key "%s".', $key));
        }

        return self::asString($data[$key], $key);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function section(array $data, string $key): array
    {
        if (! array_key_exists($key, $data)) {
            return [];
        }

        $value = $data[$key];

        if (! is_array($value)) {
            throw new InvalidArgumentException(sprintf('Configuration key "%s" must be an object.', $key));
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, array<string, mixed>>
     */
    public static function sectionMap(array $data, string $key): array
    {
        $section = self::section($data, $key);

        $map = [];
        foreach ($section as $name => $value) {
            if (! is_array($value)) {
                throw new InvalidArgumentException(sprintf(
                    'Configuration key "%s.%s" must be an object.',
                    $key,
                    $name,
                ));
            }

            /** @var array<string, mixed> $value */
            $map[$name] = $value;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function string(array $data, string $key, string $default): string
    {
        if (! array_key_exists($key, $data)) {
            return $default;
        }

        return self::asString($data[$key], $key);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string>         $default
     *
     * @return list<string>
     */
    public static function stringList(array $data, string $key, array $default): array
    {
        if (! array_key_exists($key, $data)) {
            return $default;
        }

        $value = $data[$key];

        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidArgumentException(sprintf('Configuration key "%s" must be a list.', $key));
        }

        $list = [];
        foreach ($value as $entry) {
            if (! is_string($entry)) {
                throw new InvalidArgumentException(sprintf(
                    'Configuration key "%s" must contain only strings.',
                    $key,
                ));
            }

            $list[] = $entry;
        }

        return $list;
    }

    private static function asString(mixed $value, string $key): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException(sprintf(
                'Configuration key "%s" must be a string, got %s.',
                $key,
                get_debug_type($value),
            ));
        }

        return $value;
    }
}
