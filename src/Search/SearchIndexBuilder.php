<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Search;

use Webware\AsciidocPhp\MultiRepo\Render\RenderedPage;
use Webware\AsciidocPhp\MultiRepo\Render\TocEntry;
use Webware\AsciidocPhp\MultiRepo\Site\UrlBuilder;
use Webware\AsciidocPhp\MultiRepo\Support\Json;

use function array_keys;
use function array_map;
use function preg_match;
use function strcmp;
use function usort;

/**
 * Builds one project's search index.
 *
 * The index carries every published version so the client can search across versions without a
 * second request, and every page's url is written in published form so each entry resolves to a
 * file in the output tree.
 *
 * @api
 */
final readonly class SearchIndexBuilder
{
    public const int SCHEMA_VERSION = 1;

    public function __construct(
        private Json $json = new Json(),
        private UrlBuilder $urls = new UrlBuilder(),
    ) {}

    /**
     * Highest numeric label first, then any non-numeric label such as `latest`.
     */
    private static function compareLabels(string $left, string $right): int
    {
        $leftNumber  = self::number($left);
        $rightNumber = self::number($right);

        if ($leftNumber === $rightNumber) {
            return strcmp($left, $right);
        }

        return $rightNumber <=> $leftNumber;
    }

    private static function number(string $label): int
    {
        if (1 === preg_match('/^v([0-9]+)$/', $label, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * @param array<string, list<RenderedPage>> $pages pages keyed by version label
     * @param string                            $latestLabel the label the project root redirects to
     *
     * @return array<string, mixed>
     */
    public function build(string $slug, string $title, array $pages, string $latestLabel): array
    {
        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'project'       => [
                'slug'  => $slug,
                'title' => $title,
            ],
            'versions'      => $this->versions($slug, $pages, $latestLabel),
        ];
    }

    /**
     * @param array<string, list<RenderedPage>> $pages pages keyed by version label
     */
    public function toJson(string $slug, string $title, array $pages, string $latestLabel): string
    {
        return $this->json->encode($this->build($slug, $title, $pages, $latestLabel));
    }

    /**
     * @param list<RenderedPage> $pages
     *
     * @return list<array<string, mixed>>
     */
    private function documents(array $pages): array
    {
        $documents = [];

        foreach ($pages as $page) {
            $documents[] = [
                'url'      => $this->urls->url($page->outputPath()),
                'title'    => $page->title(),
                'headings' => array_map(
                    static fn(TocEntry $entry): array => [
                        'id'    => $entry->id(),
                        'title' => $entry->title(),
                        'level' => $entry->level(),
                    ],
                    $page->toc(),
                ),
                'text'     => $page->searchText(),
            ];
        }

        usort(
            $documents,
            static fn(array $left, array $right): int => strcmp((string) $left['url'], (string) $right['url']),
        );

        return $documents;
    }

    /**
     * @param array<string, list<RenderedPage>> $pages
     *
     * @return list<array<string, mixed>>
     */
    private function versions(string $slug, array $pages, string $latestLabel): array
    {
        $labels = array_keys($pages);

        usort($labels, self::compareLabels(...));

        $versions = [];

        foreach ($labels as $label) {
            $versions[] = [
                'label'  => $label,
                'latest' => $label === $latestLabel,
                'url'    => $this->urls->version($slug, $label),
                'pages'  => $this->documents($pages[$label] ?? []),
            ];
        }

        return $versions;
    }
}
