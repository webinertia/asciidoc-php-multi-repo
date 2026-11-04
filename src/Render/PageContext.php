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
        private string $iconFontCdn = '',
        private string $logoUrl = '',
        private ?PageChrome $chrome = null,
    ) {}

    public function body(): string
    {
        return $this->body;
    }

    public function canonicalUrl(): string
    {
        return $this->canonicalUrl;
    }

    /**
     * The site chrome, which is empty when the page was rendered without assembling a site.
     */
    public function chrome(): PageChrome
    {
        return $this->chrome ?? new PageChrome();
    }

    public function colorMode(): string
    {
        return $this->colorMode;
    }

    public function defaultColorMode(): string
    {
        return $this->defaultColorMode;
    }

    /**
     * The icon font stylesheet, empty when the shell should not link one.
     */
    public function iconFontCdn(): string
    {
        return $this->iconFontCdn;
    }

    /**
     * The logo URL the shell renders, empty when the shell should not render a mark.
     */
    public function logoUrl(): string
    {
        return $this->logoUrl;
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

    /**
     * The same page with the site chrome attached.
     *
     * The chrome cannot be built until every page of every project has rendered, so a page is
     * rendered once without it and its shell is rendered again from this copy. The script list is
     * recomputed by the caller because the chrome decides whether the navigation and search scripts
     * are needed at all.
     *
     * @param list<string> $scripts
     */
    public function withChrome(PageChrome $chrome, array $scripts): self
    {
        return new self(
            siteName        : $this->siteName,
            projectTitle    : $this->projectTitle,
            pageTitle       : $this->pageTitle,
            versionLabel    : $this->versionLabel,
            canonicalUrl    : $this->canonicalUrl,
            body            : $this->body,
            toc             : $this->toc,
            stylesheets     : $this->stylesheets,
            scripts         : $scripts,
            sourceUrl       : $this->sourceUrl,
            colorMode       : $this->colorMode,
            defaultColorMode: $this->defaultColorMode,
            iconFontCdn     : $this->iconFontCdn,
            logoUrl         : $this->logoUrl,
            chrome          : $chrome,
        );
    }
}
