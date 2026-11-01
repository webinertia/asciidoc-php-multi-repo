<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Discovery;

use Webware\AsciidocPhp\MultiRepo\Config\SiteConfig;

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
