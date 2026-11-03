<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Site;

use Webware\AsciidocPhp\MultiRepo\Config\SiteConfig;

/**
 * Everything the aggregate pages of one build are derived from.
 *
 * The assembly is a value object rather than a bag of method arguments because every aggregate page
 * is a projection of the same three inputs: the publishable set, what each project published, and
 * the shared assets. Rebuilding the whole assembly is therefore the only way to change any of them,
 * which is what makes a stale landing page or a stale switcher impossible.
 *
 * @api
 */
final readonly class SiteAssembly
{
    /**
     * @param list<ProjectSummary> $projects the publishable projects, in the documented order
     * @param array<string, string> $entryPages slug to the latest entry page's rendered HTML
     * @param array<string, string> $redirects slug to the version label the project directory targets
     * @param array<string, string> $searchIndexes slug to the per-project search index document
     * @param array<string, string> $assets published asset file name to its bytes
     */
    public function __construct(
        private string $outputDirectory,
        private SiteConfig $config,
        private UrlBuilder $urls,
        private array $projects,
        private array $entryPages,
        private array $redirects,
        private array $searchIndexes,
        private string $siteSearchIndex,
        private array $assets,
        private string $baseStylesheet,
        private string $stylesheet,
    ) {}

    /**
     * @return array<string, string>
     */
    public function assets(): array
    {
        return $this->assets;
    }

    /**
     * The published base stylesheet URL, linked before the site overrides.
     */
    public function baseStylesheet(): string
    {
        return $this->baseStylesheet;
    }

    public function baseUrl(): string
    {
        return $this->config->site()->baseUrl();
    }

    public function config(): SiteConfig
    {
        return $this->config;
    }

    /**
     * @return array<string, string>
     */
    public function entryPages(): array
    {
        return $this->entryPages;
    }

    public function hostname(): ?string
    {
        return $this->config->site()->hostname();
    }

    public function outputDirectory(): string
    {
        return $this->outputDirectory;
    }

    /**
     * @return list<ProjectSummary>
     */
    public function projects(): array
    {
        return $this->projects;
    }

    /**
     * @return array<string, string>
     */
    public function redirects(): array
    {
        return $this->redirects;
    }

    /**
     * @return array<string, string>
     */
    public function searchIndexes(): array
    {
        return $this->searchIndexes;
    }

    public function siteName(): string
    {
        return $this->config->site()->name();
    }

    public function siteSearchIndex(): string
    {
        return $this->siteSearchIndex;
    }

    public function stylesheet(): string
    {
        return $this->stylesheet;
    }

    public function urls(): UrlBuilder
    {
        return $this->urls;
    }
}
