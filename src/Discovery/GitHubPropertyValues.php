<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Discovery;

use Webware\AsciidocPhpMultiRepo\Config\OrganizationSection;

use function is_array;
use function is_string;

/**
 * Maps the organisation property-values payload to a repository full name to onboarded flag map.
 *
 * The value may be a boolean or a string, and a repository with no property at all is not
 * onboarded.
 */
final readonly class GitHubPropertyValues
{
    /**
     * @param array<string, bool> $values
     */
    private function __construct(
        private array $values,
    ) {}

    /**
     * @param list<mixed> $pages
     */
    public static function fromPages(array $pages, OrganizationSection $organization): self
    {
        $values = [];

        foreach ($pages as $page) {
            if (! is_array($page)) {
                continue;
            }

            foreach ($page as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $fullName = $item['repository_full_name'] ?? null;

                if (! is_string($fullName)) {
                    continue;
                }

                $values[$fullName] = OnboardValue::matches(
                    self::valueFor($item, $organization->propertyName()),
                    $organization->onboardValue(),
                );
            }
        }

        return new self($values);
    }

    /**
     * @param array<array-key, mixed> $item
     */
    private static function valueFor(array $item, string $propertyName): mixed
    {
        $properties = $item['properties'] ?? null;

        if (! is_array($properties)) {
            return null;
        }

        foreach ($properties as $property) {
            if (! is_array($property)) {
                continue;
            }

            if (($property['property_name'] ?? null) === $propertyName) {
                return $property['value'] ?? null;
            }
        }

        return null;
    }

    /**
     * @return array<string, bool>
     */
    public function values(): array
    {
        return $this->values;
    }
}
