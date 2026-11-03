<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Render;

/**
 * The site-level navigation fragments a page shell renders around its body.
 *
 * The fragments arrive as finished markup rather than as data because they are built once per build
 * and shared by every page; re-deriving a project switcher for each of several thousand pages would
 * dominate the render. A default instance carries no chrome at all, which is what a caller that
 * renders a page without assembling a site gets.
 *
 * @api
 */
final readonly class PageChrome
{
    public function __construct(
        private string $projectSwitcher = '',
        private string $versionSelector = '',
        private string $outdatedBanner = '',
        private string $siteIndexUrl = '',
        private string $projectIndexUrl = '',
        private string $landingUrl = '/',
        private string $projectSlug = '',
    ) {}

    public function hasOutdatedBanner(): bool
    {
        return '' !== $this->outdatedBanner;
    }

    public function hasProjectSwitcher(): bool
    {
        return '' !== $this->projectSwitcher;
    }

    public function hasSearch(): bool
    {
        return '' !== $this->siteIndexUrl && '' !== $this->projectIndexUrl;
    }

    public function hasVersionSelector(): bool
    {
        return '' !== $this->versionSelector;
    }

    public function landingUrl(): string
    {
        return $this->landingUrl;
    }

    public function outdatedBanner(): string
    {
        return $this->outdatedBanner;
    }

    public function projectIndexUrl(): string
    {
        return $this->projectIndexUrl;
    }

    public function projectSlug(): string
    {
        return $this->projectSlug;
    }

    public function projectSwitcher(): string
    {
        return $this->projectSwitcher;
    }

    public function siteIndexUrl(): string
    {
        return $this->siteIndexUrl;
    }

    public function versionSelector(): string
    {
        return $this->versionSelector;
    }
}
