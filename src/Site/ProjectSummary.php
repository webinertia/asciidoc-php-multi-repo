<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Site;

use function strcmp;

/**
 * One project as it appears in navigation, the landing page, and the search index.
 *
 * `order` is the configured `projects.<name>.order`, or `PHP_INT_MAX` when the project has no
 * override. Sorting on the tuple keeps unconfigured projects after configured ones and makes the
 * whole set independent of discovery order.
 *
 * @api
 */
final readonly class ProjectSummary
{
    public function __construct(
        private string $slug,
        private string $title,
        private string $versionLabel,
        private string $url,
        private int $order = PHP_INT_MAX,
        private string $description = '',
    ) {}

    /**
     * The total order the specification requires: configured order ascending, then slug ascending.
     */
    public static function compare(self $left, self $right): int
    {
        if ($left->order !== $right->order) {
            return $left->order < $right->order ? -1 : 1;
        }

        return strcmp($left->slug, $right->slug);
    }

    public function description(): string
    {
        return $this->description;
    }

    public function order(): int
    {
        return $this->order;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function title(): string
    {
        return $this->title;
    }

    /**
     * The URL of the version the project's own directory redirects to.
     */
    public function url(): string
    {
        return $this->url;
    }

    public function versionLabel(): string
    {
        return $this->versionLabel;
    }
}
