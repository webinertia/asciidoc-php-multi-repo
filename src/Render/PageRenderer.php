<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Render;

use RuntimeException;
use Webware\AsciidocPhp\Asciidoc;
use Webware\AsciidocPhp\Document;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfig;
use Webware\AsciidocPhp\MultiRepo\Discovery\DiscoveredRepository;
use Webware\AsciidocPhp\MultiRepo\Report\ReasonCode;
use Webware\AsciidocPhp\MultiRepo\Source\DocVersion;
use Webware\AsciidocPhp\MultiRepo\Source\PreparedSource;
use Webware\AsciidocPhp\MultiRepo\Source\SourceError;
use Webware\AsciidocPhp\MultiRepo\Source\SourceException;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use Webware\AsciidocPhp\MultiRepo\Theme\ThemeResolver;
use Webware\AsciidocPhp\SafeMode;

use function basename;
use function dirname;
use function file_exists;
use function getcwd;
use function is_file;
use function mb_substr;
use function preg_match_all;
use function preg_replace;
use function rtrim;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;
use function trim;
use function ucwords;

use const PREG_SET_ORDER;

/**
 * Composes the converter, the attribute policy, the table of contents, the link rewriter, the theme
 * and the page shell into one rendered page.
 *
 * Rendering performs no network access: it reads the already prepared source text and the file it
 * points at, and nothing else. The only failures it reports are its own, and they arrive as a
 * `SourceException` carrying a reason code and the source path, so the build can report them the same
 * way Group 2 reports discovery failures.
 *
 * @api
 */
final readonly class PageRenderer
{
    private const string INCLUDE_PATTERN = '/^include::([^\[]+)\[/m';

    private const string GITHUB_BLOB = 'https://github.com/%s/%s/blob/%s/%s';

    public function __construct(
        private PageShell $shell = new PageShell(),
        private ThemeResolver $theme = new ThemeResolver(),
        private TocExtractor $toc = new TocExtractor(),
        private LinkRewriter $links = new LinkRewriter(),
    ) {}

    /**
     * The published path of a page, relative to the output directory.
     */
    public function outputPath(
        DiscoveredRepository $repository,
        DocVersion $version,
        PreparedSource $source,
    ): string {
        $relative = $source->relativePath();
        $prefix   = $version->prefix();

        if (str_starts_with($relative, $prefix)) {
            $relative = substr($relative, strlen($prefix));
        }

        $inner = Path::withExtension($relative, 'html');

        return "{$repository->slug()}/{$version->label()}/{$inner}";
    }

    /**
     * @throws SourceException when the page cannot be rendered
     * @throws RuntimeException when a shipped asset is missing
     */
    public function render(
        SiteConfig $config,
        DiscoveredRepository $repository,
        DocVersion $version,
        PreparedSource $source,
    ): RenderedPage {
        $this->assertIncludesResolve($source);

        $converter  = new SiteConverter();
        $attributes = new AttributePolicy($config->render()->syntaxHighlighting());

        $html = Asciidoc::convertFile($source->page()->absolutePath(), [
            'backend'       => 'html5',
            'doctype'       => 'article',
            'safe'          => $this->safeMode($config->render()->safeMode()),
            'attributes'    => $attributes->attributes(),
            'header_footer' => false,
            'base_dir'      => $this->baseDirectory(dirname($source->page()->absolutePath())),
            'converter'     => $converter,
        ]);

        $document = $converter->lastDocument();

        if (! $document instanceof Document) {
            throw new SourceException($this->failure(
                $source,
                'The converter produced no document for this page.',
                'Report this as a defect: the page was parsed but no document was captured.',
            ));
        }

        $outputPath = $this->outputPath($repository, $version, $source);
        $urlPath    = "/{$outputPath}";
        $basePath   = $config->site()->basePath();
        $body       = $this->links->rewrite($html, $urlPath, $basePath);
        $entries    = $this->toc->extract($document, $config->render()->tocLevels(), $source->relativePath());

        $pageTitle = trim((string) $document->getDocTitle());

        if ('' === $pageTitle) {
            $pageTitle = $this->humanise(basename($source->relativePath(), '.adoc'));
        }

        $projectTitle = $this->projectTitle($config, $repository);
        $accent       = $config->project($repository->name())?->accent();
        $siteName     = $config->site()->name();

        $context = new PageContext(
            siteName        : $siteName,
            projectTitle    : $projectTitle,
            pageTitle       : $pageTitle,
            versionLabel    : $version->label(),
            canonicalUrl    : rtrim($config->site()->baseUrl(), '/') . rtrim($basePath, '/') . $urlPath,
            body            : $body,
            toc             : $entries,
            stylesheets     : [
                $this->theme->assetUrl($basePath, $this->theme->siteName()),
                $this->theme->assetUrl($basePath, $this->theme->accentName($accent)),
            ],
            scripts         : [
                $this->theme->assetUrl($basePath, $this->theme->themeScriptName()),
            ],
            sourceUrl       : sprintf(
                self::GITHUB_BLOB,
                $config->organization()->name(),
                $repository->name(),
                $repository->defaultBranch(),
                $source->relativePath(),
            ),
            colorMode       : $this->theme->colorMode(),
            defaultColorMode: $this->theme->defaultColorMode(),
        );

        $headings = [];
        foreach ($entries as $entry) {
            $headings[] = $entry->title();
        }

        return new RenderedPage(
            html      : $this->shell->render($context),
            outputPath: $outputPath,
            urlPath   : $urlPath,
            title     : $pageTitle,
            toc       : $entries,
            headings  : $headings,
            searchText: $this->searchText($body, $config->render()->searchExtractLimit()),
        );
    }

    /**
     * @throws SourceException when an include target is missing
     */
    private function assertIncludesResolve(PreparedSource $source): void
    {
        $matches = [];
        preg_match_all(self::INCLUDE_PATTERN, $source->text(), $matches, PREG_SET_ORDER);

        if ([] === $matches) {
            return;
        }

        $baseDirectory = dirname($source->page()->absolutePath());

        foreach ($matches as $match) {
            $target = trim($match[1] ?? '');

            if ('' === $target || str_contains($target, '://')) {
                continue;
            }

            $resolved = str_starts_with($target, '/') ? $target : "{$baseDirectory}/{$target}";

            if (is_file($resolved) || file_exists($resolved)) {
                continue;
            }

            throw new SourceException($this->failure(
                $source,
                sprintf('The include target "%s" does not exist.', $target),
                'Add the missing file or remove the include directive, then rebuild.',
            ));
        }
    }

    /**
     * The directory the converter resolves includes against.
     *
     * The converter's path resolver drops a Windows drive prefix when it normalises an absolute
     * path, which makes every include resolve to nothing and get suppressed. Passing a directory
     * relative to the working directory sidesteps that, and it resolves to the same place.
     */
    private function baseDirectory(string $directory): string
    {
        $cwd = getcwd();

        if (false === $cwd) {
            return $directory;
        }

        $relative = Path::relativeTo($cwd, $directory);

        if (Path::isAbsolute($relative) || str_starts_with($relative, '..')) {
            return $directory;
        }

        return $relative;
    }

    private function failure(PreparedSource $source, string $message, string $correctiveAction): SourceError
    {
        return new SourceError(
            ReasonCode::RenderFailed,
            $message,
            $source->version(),
            $source->relativePath(),
            $correctiveAction,
        );
    }

    private function humanise(string $value): string
    {
        return ucwords(strtolower(preg_replace('/[-_]+/', ' ', $value) ?? $value));
    }

    private function projectTitle(SiteConfig $config, DiscoveredRepository $repository): string
    {
        $override = $config->project($repository->name())?->title();

        if (null !== $override && '' !== trim($override)) {
            return $override;
        }

        return $this->humanise($repository->name());
    }

    private function safeMode(string $mode): SafeMode
    {
        return match (strtolower($mode)) {
            'unsafe' => SafeMode::UNSAFE,
            'server' => SafeMode::SERVER,
            'secure' => SafeMode::SECURE,
            default  => SafeMode::SAFE,
        };
    }

    private function searchText(string $html, int $limit): string
    {
        $text = (string) preg_replace('/\s+/', ' ', (string) preg_replace('/<[^>]*>/', ' ', $html));
        $text = trim($text);

        if ($limit > 0 && mb_substr($text, 0, $limit) !== $text) {
            return mb_substr($text, 0, $limit);
        }

        return $text;
    }
}
