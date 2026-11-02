<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Site;

use Webware\AsciidocPhp\MultiRepo\Render\PageChrome;
use Webware\AsciidocPhp\MultiRepo\Render\PageShell;

use function implode;
use function sprintf;
use function strlen;
use function strpos;
use function substr;

/**
 * Builds the site chrome every page carries: the project switcher, the version selector, the
 * outdated banner, and the URLs the client-side search needs.
 *
 * The chrome is resolved per page rather than per project because the banner and the selector link
 * to the same page in another version when that page exists, which is a per-page question.
 *
 * Only publishable projects reach the switcher, so navigation never leads a reader to a 404.
 *
 * @api
 */
final readonly class NavigationBuilder
{
    /**
     * @param list<ProjectSummary> $projects the publishable projects, in the documented order
     * @param array<string, list<VersionLink>> $versions slug to versions, newest first
     * @param array<string, array<string, array<string, true>>> $pages slug to version label to the
     *        set of page paths published in that version, relative to the version directory
     */
    public function __construct(
        private array $projects,
        private array $versions,
        private array $pages,
        private UrlBuilder $urls,
        private PageShell $shell = new PageShell(),
        private OutdatedBannerBuilder $banners = new OutdatedBannerBuilder(),
        private VersionSelectorBuilder $selectors = new VersionSelectorBuilder(),
    ) {}

    /**
     * @param string $pagePath the page's output-relative path, used to find the same page elsewhere
     */
    public function chrome(string $slug, string $label, string $pagePath): PageChrome
    {
        $latest = $this->latest($this->versions[$slug] ?? []);
        $inner  = $this->innerPath($slug, $label, $pagePath);

        return new PageChrome(
            projectSwitcher: $this->projectSwitcher($slug),
            versionSelector: $this->selector($slug, $label, $inner),
            outdatedBanner : $this->banners->build(
                $label,
                $latest?->label(),
                null === $latest ? null : $this->samePage($slug, $latest->label(), $inner),
                null === $latest ? null : $this->urls->entryPage($slug, $latest->label()),
            ),
            siteIndexUrl   : $this->urls->searchIndex(),
            projectIndexUrl: $this->urls->projectSearchIndex($slug),
            landingUrl     : $this->urls->basePath(),
            projectSlug    : $slug,
        );
    }

    public function projectSwitcher(string $slug): string
    {
        if ([] === $this->projects) {
            return '';
        }

        $items = [];
        foreach ($this->projects as $project) {
            $items[] = sprintf(
                '<li class="project-item%s"><a href="%s"%s>%s</a></li>',
                $project->slug() === $slug ? ' project-current' : '',
                $this->shell->escape($project->url()),
                $project->slug() === $slug ? ' aria-current="page"' : '',
                $this->shell->escape($project->title()),
            );
        }

        return sprintf(
            "<nav class=\"project-switcher\" aria-label=\"Projects\" data-project-switcher>\n"
                . "<p class=\"nav-label\">Projects</p>\n<ul>\n%s\n</ul>\n</nav>\n",
            implode("\n", $items),
        );
    }

    private function innerPath(string $slug, string $label, string $pagePath): string
    {
        $prefix = "{$slug}/{$label}/";

        if (0 !== strpos($pagePath, $prefix)) {
            return 'index.html';
        }

        return substr($pagePath, strlen($prefix));
    }

    /**
     * @param list<VersionLink> $versions
     */
    private function latest(array $versions): ?VersionLink
    {
        foreach ($versions as $version) {
            if ($version->isLatest()) {
                return $version;
            }
        }

        return null;
    }

    private function linkFor(string $slug, string $label, string $innerPath): string
    {
        if (! isset($this->pages[$slug][$label][$innerPath])) {
            return $this->urls->entryPage($slug, $label);
        }

        return $this->urls->page($slug, $label, $innerPath);
    }

    private function samePage(string $slug, string $label, string $innerPath): ?string
    {
        if (! isset($this->pages[$slug][$label][$innerPath])) {
            return null;
        }

        return $this->urls->page($slug, $label, $innerPath);
    }

    /**
     * Resolves each version to the same page inside that version, or to its entry page.
     */
    private function selector(string $slug, string $label, string $innerPath): string
    {
        $versions = [];
        foreach ($this->versions[$slug] ?? [] as $version) {
            $versions[] = new VersionLink(
                $version->label(),
                $this->linkFor($slug, $version->label(), $innerPath),
                $version->isLatest(),
            );
        }

        return $this->selectors->build($versions, $label);
    }
}
