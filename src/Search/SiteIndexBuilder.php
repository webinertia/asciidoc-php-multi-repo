<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Search;

use Webware\AsciidocPhp\MultiRepo\Site\ProjectSummary;
use Webware\AsciidocPhp\MultiRepo\Site\UrlBuilder;
use Webware\AsciidocPhp\MultiRepo\Support\Json;

/**
 * Builds the site-level index of project indexes.
 *
 * The client reads this file first and then fetches only the project indexes it needs, which keeps
 * a first search to two requests on any size of site.
 *
 * @api
 */
final readonly class SiteIndexBuilder
{
    public const int SCHEMA_VERSION = 1;

    public function __construct(
        private Json $json = new Json(),
    ) {}

    /**
     * @param list<ProjectSummary> $projects in published order
     *
     * @return array<string, mixed>
     */
    public function build(array $projects, UrlBuilder $urls): array
    {
        $entries = [];

        foreach ($projects as $project) {
            $entries[] = [
                'slug'  => $project->slug(),
                'title' => $project->title(),
                'index' => $urls->projectSearchIndex($project->slug()),
            ];
        }

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'projects'      => $entries,
        ];
    }

    /**
     * @param list<ProjectSummary> $projects in published order
     */
    public function toJson(array $projects, UrlBuilder $urls): string
    {
        return $this->json->encode($this->build($projects, $urls));
    }
}
