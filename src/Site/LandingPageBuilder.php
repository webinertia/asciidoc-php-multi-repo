<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Site;

use RuntimeException;
use Webware\AsciidocPhp\MultiRepo\Render\PageShell;
use Webware\AsciidocPhp\MultiRepo\Search\TextExtractor;

use function array_map;

/**
 * Emits the site landing page: one card per publishable project, in the documented order.
 *
 * A configured description always wins. Without one the card falls back to the first paragraph of
 * the project's latest entry page, truncated on a word boundary so a card never ends mid-word.
 *
 * @api
 */
final readonly class LandingPageBuilder
{
    public function __construct(
        private PageShell $shell = new PageShell(),
        private TextExtractor $extractor = new TextExtractor(240),
    ) {}

    /**
     * @param string $canonicalUrl the landing page's absolute URL, empty when the site has no base URL
     *
     * @throws RuntimeException when the landing template cannot be read
     */
    public function build(SiteAssembly $assembly, string $canonicalUrl): string
    {
        return $this->shell->renderTemplate('landing.php', [
            'siteName'     => $assembly->siteName(),
            'canonicalUrl' => $canonicalUrl,
            'stylesheets'  => [$assembly->baseStylesheet(), $assembly->stylesheet()],
            'cards'        => $this->cards($assembly->projects(), $assembly->entryPages()),
            'logoUrl'      => $assembly->logoUrl(),
            'e'            => $this->shell->escape(...),
        ]);
    }

    /**
     * @param list<ProjectSummary> $projects
     * @param array<string, string> $entryPages
     *
     * @return list<array{description: string, title: string, url: string, version: string}>
     */
    private function cards(array $projects, array $entryPages): array
    {
        return array_map(
            fn(ProjectSummary $project): array => [
                'title'       => $project->title(),
                'url'         => $project->url(),
                'version'     => $project->versionLabel(),
                'description' => $this->description($project, $entryPages[$project->slug()] ?? ''),
            ],
            $projects,
        );
    }

    private function description(ProjectSummary $project, string $entryHtml): string
    {
        if ('' !== $project->description()) {
            return $project->description();
        }

        return $this->extractor->firstParagraph($entryHtml);
    }
}
