<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Site;

use RuntimeException;
use Webware\AsciidocPhp\MultiRepo\Build\ContentWriter;

use function rtrim;

/**
 * Assembles every aggregate page after the projects have rendered.
 *
 * The order matters only for readability; nothing here reads what another step wrote, because each
 * step derives its output from the assembly alone. That is what makes the aggregate pages identical
 * for identical inputs, and what makes them rebuild whenever the publishable set or any project's
 * metadata changes.
 *
 * @api
 */
final readonly class SiteAssembler
{
    public const string LANDING_FILE = 'index.html';

    public const string SITE_INDEX_FILE = 'search-index.json';

    public function __construct(
        private StaticAssetWriter $assets = new StaticAssetWriter(),
        private LandingPageBuilder $landing = new LandingPageBuilder(),
        private RedirectWriter $redirects = new RedirectWriter(),
        private ContentWriter $writer = new ContentWriter(),
    ) {}

    /**
     * @throws RuntimeException when a template or asset cannot be read
     */
    public function assemble(SiteAssembly $assembly): void
    {
        $this->assets->write($assembly);
        $this->writeLandingPage($assembly);
        $this->writeRedirects($assembly);
        $this->writeSearchIndexes($assembly);
    }

    private function canonical(SiteAssembly $assembly, string $url): string
    {
        $baseUrl = $assembly->baseUrl();

        if ('' === $baseUrl) {
            return $url;
        }

        return rtrim($baseUrl, characters: '/') . $url;
    }

    /**
     * @throws RuntimeException when the landing template cannot be read
     */
    private function writeLandingPage(SiteAssembly $assembly): void
    {
        $html = $this->landing->build(
            $assembly,
            $this->canonical($assembly, $assembly->urls()->basePath()),
        );

        $this->writer->write($assembly->outputDirectory(), self::LANDING_FILE, $html);
    }

    /**
     * @throws RuntimeException when the redirect template cannot be read
     */
    private function writeRedirects(SiteAssembly $assembly): void
    {
        foreach ($assembly->redirects() as $slug => $label) {
            $target = $assembly->urls()->version($slug, $label);

            $this->redirects->write(
                $assembly->outputDirectory(),
                $slug,
                $label,
                $target,
                $this->canonical($assembly, $target),
            );
        }
    }

    private function writeSearchIndexes(SiteAssembly $assembly): void
    {
        foreach ($assembly->searchIndexes() as $slug => $document) {
            $this->writer->write($assembly->outputDirectory(), "{$slug}/search-index.json", $document);
        }

        $this->writer->write(
            $assembly->outputDirectory(),
            self::SITE_INDEX_FILE,
            $assembly->siteSearchIndex(),
        );
    }
}
