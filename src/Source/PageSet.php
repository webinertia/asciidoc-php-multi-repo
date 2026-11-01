<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Source;

/**
 * The renderable pages of one version, with the entry page identified.
 *
 * The entry page is one of `pages`; it is exposed separately because the renderer publishes it at
 * the version root rather than at its source path.
 */
final readonly class PageSet
{
    /**
     * @param list<DocPage> $pages sorted by relative path, entry included
     */
    public function __construct(
        private DocVersion $version,
        private DocPage $entry,
        private array $pages,
    ) {}

    public function entry(): DocPage
    {
        return $this->entry;
    }

    /**
     * @return list<DocPage>
     */
    public function pages(): array
    {
        return $this->pages;
    }

    public function version(): DocVersion
    {
        return $this->version;
    }
}
