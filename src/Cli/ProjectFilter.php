<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Cli;

use Webware\AsciidocPhpMultiRepo\Discovery\DiscoveredRepository;

use function array_filter;
use function array_values;
use function in_array;

/**
 * Applies `--project` to a discovered set. The contract accepts either the slug or the repository
 * name, so both are matched.
 */
final class ProjectFilter
{
    /**
     * @param list<DiscoveredRepository> $repositories
     *
     * @return list<DiscoveredRepository>
     */
    public static function apply(array $repositories, Options $options): array
    {
        if (! $options->hasProjectFilter()) {
            return $repositories;
        }

        return array_values(array_filter(
            $repositories,
            static fn(DiscoveredRepository $repository): bool => (
                in_array($repository->slug(), $options->projects, strict: true)
                || in_array($repository->name(), $options->projects, strict: true)
            ),
        ));
    }
}
