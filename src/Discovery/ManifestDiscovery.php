<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Discovery;

use Override;
use Webware\AsciidocPhp\MultiRepo\Config\Manifest;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfig;

/**
 * Discovery backed by the offline manifest.
 *
 * This source never opens a socket: every entry already carries the branch, the flags, and usually
 * a local path. It exists so the whole pipeline can be exercised without network access.
 */
final class ManifestDiscovery extends AbstractProjectDiscovery
{
    public function __construct(
        private readonly Manifest $manifest,
        SlugNormalizer $normalizer = new SlugNormalizer(),
        SlugCollisionDetector $collisions = new SlugCollisionDetector(),
    ) {
        parent::__construct($normalizer, $collisions);
    }

    /**
     * @return list<DiscoveredRepository>
     *
     * @throws SlugException when a manifest slug cannot be normalised
     */
    #[Override]
    protected function collect(SiteConfig $config): array
    {
        $repositories = [];

        foreach ($this->manifest->repositories() as $entry) {
            $repositories[] = new DiscoveredRepository(
                $entry->name(),
                $this->normalizer->normalise($entry->slug() ?? $entry->name()),
                $entry->defaultBranch(),
                $entry->archived(),
                $entry->onboarded(),
                $entry->path(),
            );
        }

        return $repositories;
    }
}
