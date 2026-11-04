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

use function array_unique;
use function array_values;
use function basename;
use function dirname;
use function file_exists;
use function getcwd;
use function is_file;
use function mb_substr;
use function preg_match;
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
        ?PageChrome $chrome = null,
    ): RenderedPage {
        $this->assertIncludesResolve($source);

        $converter  = new SiteConverter();
        $attributes = new AttributePolicy();

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
            $pageTitle = $this->humanise(basename($source->relativePath(), suffix: '.adoc'));
        }

        $projectTitle = $this->projectTitle($config, $repository);
        $accent       = $config->project($repository->name())?->accent();
        $siteName     = $config->site()->name();
        $chrome       ??= new PageChrome();

        /*
         * The stylesheet and script lists are resolved against the body, because only the
         * conversion knows whether the page carries a diagram or a listing block, and against the
         * site configuration, because it decides whether a listing block is coloured at all.
         */
        $assets = $this->pageAssets($config, $basePath, $accent, $body, $chrome);

        $context = new PageContext(
            siteName        : $siteName,
            projectTitle    : $projectTitle,
            pageTitle       : $pageTitle,
            versionLabel    : $version->label(),
            canonicalUrl    : rtrim($config->site()->baseUrl(), characters: '/')
                . rtrim($basePath, characters: '/')
                . $urlPath,
            body            : $body,
            toc             : $entries,
            stylesheets     : $assets['stylesheets'],
            scripts         : $assets['scripts'],
            sourceUrl       : sprintf(
                self::GITHUB_BLOB,
                $config->organization()->name(),
                $repository->name(),
                $repository->defaultBranch(),
                $source->relativePath(),
            ),
            colorMode       : $this->theme->colorMode(),
            defaultColorMode: $this->theme->defaultColorMode(),
            iconFontCdn     : $this->theme->config()->iconFontCdn(),
            logoUrl         : $this->theme->logoUrl($basePath),
            chrome          : $chrome,
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
            context   : $context,
        );
    }

    /**
     * The same page with the site chrome attached.
     *
     * The chrome is built once every page has rendered, so a page's shell is rendered a second time
     * from the conversion it already carries. Nothing is converted twice. The scripts this page
     * already carries are kept, because they were resolved against the body and the site config on
     * the first render, and only the chrome's own scripts are added.
     *
     * @throws RuntimeException when a theme asset cannot be read
     */
    public function withChrome(RenderedPage $page, PageChrome $chrome, string $basePath): RenderedPage
    {
        $context = $page->context();

        if (null === $context) {
            return $page;
        }

        $scripts = [...$context->scripts(), ...$this->chromeScripts($basePath, $chrome)];

        return $page->withHtml($this->shell->render(
            $context->withChrome($chrome, array_values(array_unique($scripts))),
        ));
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

    /**
     * The scripts the site chrome needs, which are absent when the chrome is empty.
     *
     * @return list<string>
     *
     * @throws RuntimeException when a theme asset cannot be read
     */
    private function chromeScripts(string $basePath, PageChrome $chrome): array
    {
        $scripts = [];

        if ($chrome->hasProjectSwitcher() || $chrome->hasVersionSelector()) {
            $scripts[] = $this->theme->assetUrl($basePath, $this->theme->navScriptName());
        }

        if ($chrome->hasSearch()) {
            $scripts[] = $this->theme->assetUrl($basePath, $this->theme->searchCoreName());
            $scripts[] = $this->theme->assetUrl($basePath, $this->theme->searchScriptName());
        }

        return $scripts;
    }

    /**
     * The scripts that colour a listing block, empty when the page has none to colour.
     *
     * @return list<string>
     *
     * @throws RuntimeException when a theme asset cannot be read
     */
    private function codeScripts(string $basePath, string $body): array
    {
        if (! $this->hasCodeBlock($body)) {
            return [];
        }

        return [
            $this->theme->assetUrl($basePath, $this->theme->highlightScriptName()),
            $this->theme->assetUrl($basePath, $this->theme->highlightLoaderName()),
        ];
    }

    /**
     * The stylesheet that paints a highlighted listing block, empty when the page has none.
     *
     * @return list<string>
     *
     * @throws RuntimeException when a theme asset cannot be read
     */
    private function codeStylesheets(string $basePath, string $body): array
    {
        if (! $this->hasCodeBlock($body)) {
            return [];
        }

        return [$this->theme->assetUrl($basePath, $this->theme->highlightName())];
    }

    /**
     * The scripts that draw a diagram, empty when the page has none.
     *
     * @return list<string>
     *
     * @throws RuntimeException when a theme asset cannot be read
     */
    private function diagramScripts(string $basePath, string $body): array
    {
        if (! $this->hasMermaid($body)) {
            return [];
        }

        return [
            $this->theme->assetUrl($basePath, $this->theme->mermaidScriptName()),
            $this->theme->assetUrl($basePath, $this->theme->mermaidLoaderName()),
        ];
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

    /**
     * Whether the converted body carries a listing block the highlighter should colour.
     *
     * The converter writes `class="language-php hljs"` and `data-lang="php"` on a source block, so
     * the language class is the marker. A Mermaid block carries the same shape, and it is excluded:
     * it is rendered to a diagram, and handing it to the highlighter would replace its source text
     * with token spans the diagram loader no longer recognises.
     */
    private function hasCodeBlock(string $body): bool
    {
        return 1 === preg_match('/class="language-(?!mermaid)/', $body);
    }

    /**
     * Whether the converted body carries a diagram block.
     */
    private function hasMermaid(string $body): bool
    {
        return str_contains($body, 'language-mermaid') || str_contains($body, 'data-lang="mermaid"');
    }

    private function humanise(string $value): string
    {
        return ucwords(strtolower(preg_replace('/[-_]+/', replacement: ' ', subject: $value) ?? $value));
    }

    /**
     * The stylesheets and scripts this page needs, in load order.
     *
     * The body decides whether a diagram has to be drawn and whether a listing block has to be
     * coloured, because only the conversion knows what the author wrote. The chrome decides whether
     * the navigation and search scripts are needed at all. Site configuration decides whether a
     * listing block is coloured, because the vendored highlighter can be switched off. The vendored
     * bundles are large, so a page that needs neither never links them.
     *
     * @return array{stylesheets: list<string>, scripts: list<string>}
     *
     * @throws RuntimeException when a theme asset cannot be read
     */
    private function pageAssets(
        SiteConfig $config,
        string $basePath,
        ?string $accent,
        string $body,
        PageChrome $chrome,
    ): array {
        $codeScripts     = [];
        $codeStylesheets = [];

        if ($config->render()->syntaxHighlighting()) {
            $codeScripts     = $this->codeScripts($basePath, $body);
            $codeStylesheets = $this->codeStylesheets($basePath, $body);
        }

        return [
            'stylesheets' => [
                $this->theme->assetUrl($basePath, $this->theme->baseName()),
                $this->theme->assetUrl($basePath, $this->theme->siteName()),
                $this->theme->assetUrl($basePath, $this->theme->accentName($accent)),
                ...$codeStylesheets,
            ],
            'scripts'     => [
                $this->theme->assetUrl($basePath, $this->theme->themeScriptName()),
                ...$this->diagramScripts($basePath, $body),
                ...$codeScripts,
                ...$this->chromeScripts($basePath, $chrome),
            ],
        ];
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
        $text = (string) preg_replace(
            '/\s+/',
            replacement: ' ',
            subject: (string) preg_replace('/<[^>]*>/', replacement: ' ', subject: $html),
        );
        $text = trim($text);

        if ($limit > 0 && mb_substr($text, start: 0, length: $limit) !== $text) {
            return mb_substr($text, start: 0, length: $limit);
        }

        return $text;
    }
}
