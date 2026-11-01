<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Discovery;

use function is_bool;
use function is_string;
use function strtolower;
use function trim;

/**
 * Compares a GitHub custom property value with the configured onboard value.
 *
 * The API returns a property value as a string or a boolean depending on the property type, so both
 * are accepted and the comparison is case-insensitive and trimmed.
 */
final class OnboardValue
{
    public static function matches(mixed $value, string $configured): bool
    {
        $expected = strtolower(trim($configured));

        if (is_bool($value)) {
            return ($value ? 'true' : 'false') === $expected;
        }

        if (! is_string($value)) {
            return false;
        }

        return strtolower(trim($value)) === $expected;
    }
}
