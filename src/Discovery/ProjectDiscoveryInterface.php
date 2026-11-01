<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Discovery;

use Webware\AsciidocPhpMultiRepo\Config\SiteConfig;

/**
 * The seam between "where the project list comes from" and everything downstream.
 *
 * @internal
 */
interface ProjectDiscoveryInterface
{
    /**
     * @return list<DiscoveredRepository> ordered by slug ascending
     *
     * @throws SlugException
     * @throws DiscoveryException
     */
    public function discover(SiteConfig $config): array;
}
