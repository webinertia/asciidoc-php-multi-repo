<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Site;

use Webware\AsciidocPhp\MultiRepo\Config\SiteSection;

use function ltrim;
use function trim;

/**
 * Builds every published URL from the site base path.
 *
 * One instance per build, so a `--base-path` override reaches every link, every canonical URL, and
 * every redirect without any caller re-deriving the prefix. The base path always carries a leading
 * and a trailing slash, which means joining never has to ask whether it is the root.
 *
 * @api
 */
final readonly class UrlBuilder
{
    private string $basePath;

    public function __construct(string $basePath = '/')
    {
        $this->basePath = SiteSection::normaliseBasePath($basePath);
    }

    /**
     * A published asset below `assets/`.
     */
    public function asset(string $name): string
    {
        return $this->url("assets/{$name}");
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * A directory URL, which always ends with a slash.
     */
    public function directory(string $relativePath): string
    {
        $relative = trim($relativePath, '/');

        if ('' === $relative) {
            return $this->basePath;
        }

        return "{$this->basePath}{$relative}/";
    }

    /**
     * The entry page of one version of one project.
     */
    public function entryPage(string $slug, string $label): string
    {
        return $this->url("{$slug}/{$label}/index.html");
    }

    /**
     * A page inside one version of one project. The inner path is relative to the version directory.
     */
    public function page(string $slug, string $label, string $innerPath): string
    {
        return $this->url("{$slug}/{$label}/" . ltrim($innerPath, '/'));
    }

    /**
     * The directory URL of one project, which is where its redirect document sits.
     */
    public function project(string $slug): string
    {
        return $this->directory($slug);
    }

    public function projectSearchIndex(string $slug): string
    {
        return $this->url("{$slug}/search-index.json");
    }

    public function searchIndex(): string
    {
        return $this->url('search-index.json');
    }

    /**
     * A URL for a path relative to the output directory.
     */
    public function url(string $relativePath = ''): string
    {
        $relative = ltrim($relativePath, '/');

        if ('' === $relative) {
            return $this->basePath;
        }

        return "{$this->basePath}{$relative}";
    }

    /**
     * The directory URL of one version of one project.
     */
    public function version(string $slug, string $label): string
    {
        return $this->directory("{$slug}/{$label}");
    }
}
