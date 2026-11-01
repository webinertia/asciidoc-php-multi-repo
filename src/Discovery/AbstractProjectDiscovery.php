<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Discovery;

use Override;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfig;

use function array_filter;
use function array_values;
use function strcmp;
use function usort;

/**
 * The behaviour every discovery source shares: apply the configured slug override, drop excluded
 * repositories, order by slug, and reject collisions.
 *
 * Sorting happens before the collision check so the reported pair is stable.
 *
 * @internal
 */
abstract class AbstractProjectDiscovery implements ProjectDiscoveryInterface
{
    public function __construct(
        protected readonly SlugNormalizer $normalizer = new SlugNormalizer(),
        protected readonly SlugCollisionDetector $collisions = new SlugCollisionDetector(),
    ) {}

    /**
     * @return list<DiscoveredRepository>
     *
     * @throws DiscoveryException when the organization listing cannot be read
     * @throws SlugException      when a repository name cannot be turned into a slug
     */
    #[Override]
    final public function discover(SiteConfig $config): array
    {
        $organization = $config->organization();
        $repositories = [];

        foreach ($this->collect($config) as $repository) {
            if ($organization->excludes($repository->name())) {
                continue;
            }

            $repositories[] = $this->applyOverride($config, $repository);
        }

        usort(
            $repositories,
            static fn(DiscoveredRepository $left, DiscoveredRepository $right): int => strcmp(
                $left->slug(),
                $right->slug(),
            ),
        );

        $this->collisions->detect(
            array_values(
                array_filter(
                    $repositories,
                    static fn(DiscoveredRepository $repository): bool => $repository->onboarded(),
                ),
            ),
        );

        return $repositories;
    }

    /**
     * @return list<DiscoveredRepository>
     *
     * @throws DiscoveryException
     */
    abstract protected function collect(SiteConfig $config): array;

    /**
     * @throws SlugException when the configured override cannot be turned into a slug
     */
    private function applyOverride(SiteConfig $config, DiscoveredRepository $repository): DiscoveredRepository
    {
        $override = $config->project($repository->name());

        if (null === $override) {
            return $repository;
        }

        $slug = $override->slug();

        if (null === $slug) {
            return $repository;
        }

        return $repository->withSlug($this->normalizer->normalise($slug));
    }
}
