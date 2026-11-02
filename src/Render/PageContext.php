<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Render;

use function implode;

/**
 * Everything the page shell needs to render one page.
 *
 * The body is already-converted article HTML and is the only value the shell does not escape. Every
 * other value, including the titles, the URLs and the table of contents, is escaped by the template.
 *
 * @api
 */
final readonly class PageContext
{
    /**
     * @param list<TocEntry> $toc
     * @param list<string>   $stylesheets absolute URLs, already carrying the base path
     * @param list<string>   $scripts     absolute URLs, already carrying the base path
     */
    public function __construct(
        private string $siteName,
        private string $projectTitle,
        private string $pageTitle,
        private string $versionLabel,
        private string $canonicalUrl,
        private string $body,
        private array $toc = [],
        private array $stylesheets = [],
        private array $scripts = [],
        private ?string $sourceUrl = null,
        private string $colorMode = 'system',
        private string $defaultColorMode = 'dark',
    ) {}

    public function body(): string
    {
        return $this->body;
    }

    public function canonicalUrl(): string
    {
        return $this->canonicalUrl;
    }

    public function colorMode(): string
    {
        return $this->colorMode;
    }

    public function defaultColorMode(): string
    {
        return $this->defaultColorMode;
    }

    public function pageTitle(): string
    {
        return $this->pageTitle;
    }

    public function projectTitle(): string
    {
        return $this->projectTitle;
    }

    /**
     * @return list<string>
     */
    public function scripts(): array
    {
        return $this->scripts;
    }

    public function siteName(): string
    {
        return $this->siteName;
    }

    public function sourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    /**
     * @return list<string>
     */
    public function stylesheets(): array
    {
        return $this->stylesheets;
    }

    /**
     * Page, then project, then site, which is the order the specification asks for.
     */
    public function title(): string
    {
        return implode(' | ', [$this->pageTitle, $this->projectTitle, $this->siteName]);
    }

    /**
     * @return list<TocEntry>
     */
    public function toc(): array
    {
        return $this->toc;
    }

    public function versionLabel(): string
    {
        return $this->versionLabel;
    }
}
