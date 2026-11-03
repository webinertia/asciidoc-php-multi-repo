<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Build;

use InvalidArgumentException;
use RuntimeException;
use Webware\AsciidocPhp\MultiRepo\Cli\ExitCode;
use Webware\AsciidocPhp\MultiRepo\Cli\Options;
use Webware\AsciidocPhp\MultiRepo\Cli\Output;
use Webware\AsciidocPhp\MultiRepo\Config\BuildConfig;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfig;
use Webware\AsciidocPhp\MultiRepo\Discovery\DiscoveredRepository;
use Webware\AsciidocPhp\MultiRepo\Render\PageRenderer;
use Webware\AsciidocPhp\MultiRepo\Render\RenderedPage;
use Webware\AsciidocPhp\MultiRepo\Report\BuildReport;
use Webware\AsciidocPhp\MultiRepo\Report\ProjectResult;
use Webware\AsciidocPhp\MultiRepo\Report\ReasonCode;
use Webware\AsciidocPhp\MultiRepo\Search\SearchIndexBuilder;
use Webware\AsciidocPhp\MultiRepo\Search\SiteIndexBuilder;
use Webware\AsciidocPhp\MultiRepo\Site\NavigationBuilder;
use Webware\AsciidocPhp\MultiRepo\Site\ProjectSummary;
use Webware\AsciidocPhp\MultiRepo\Site\RedirectWriter;
use Webware\AsciidocPhp\MultiRepo\Site\SiteAssembler;
use Webware\AsciidocPhp\MultiRepo\Site\SiteAssembly;
use Webware\AsciidocPhp\MultiRepo\Site\UrlBuilder;
use Webware\AsciidocPhp\MultiRepo\Site\VersionLink;
use Webware\AsciidocPhp\MultiRepo\Source\DocVersion;
use Webware\AsciidocPhp\MultiRepo\Source\PreparedSource;
use Webware\AsciidocPhp\MultiRepo\Source\RepositorySources;
use Webware\AsciidocPhp\MultiRepo\Source\SourceCollector;
use Webware\AsciidocPhp\MultiRepo\Source\SourceError;
use Webware\AsciidocPhp\MultiRepo\Source\SourceException;
use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Theme\ThemeResolver;

use function array_keys;
use function count;
use function microtime;
use function preg_match;
use function sprintf;
use function strcmp;
use function strlen;
use function strpos;
use function substr;
use function usort;

/**
 * Runs one build: collect, render, assemble, guard, report.
 *
 * The runner is where failure isolation lives. One repository that cannot be fetched, has no
 * documentation, or fails to render is recorded as that project's outcome and the remaining
 * projects still build, so a single broken dependency never costs the whole site. The status
 * vocabulary is the one `contracts/report.schema.json` defines, and it is decided here rather than
 * by the caller, because the counts the summary prints and the codes the report carries must agree.
 *
 * Rendering happens in two passes. The first converts every page, which is the expensive part and
 * the part that decides which projects are publishable. The second renders each page's shell once
 * the chrome exists, which needs the complete publishable set. Nothing is converted twice.
 *
 * @api
 */
final class BuildRunner
{
    public function __construct(
        private readonly SourceCollector $collector = new SourceCollector(),
        private readonly ContentWriter $writer = new ContentWriter(),
        private readonly Filesystem $filesystem = new Filesystem(),
        private readonly IncrementalCache $cache = new IncrementalCache(),
        private readonly SiteAssembler $assembler = new SiteAssembler(),
        private readonly DeterminismGuard $guard = new DeterminismGuard(),
    ) {}

    /**
     * @param list<DiscoveredRepository> $repositories in discovery order
     *
     * @throws InvalidArgumentException when a project reports a status outside the schema vocabulary
     * @throws RuntimeException         when a theme asset cannot be read
     */
    public function run(
        SiteConfig $config,
        array $repositories,
        Options $options,
        BuildConfig $buildConfig,
        Output $output,
    ): BuildRun {
        $started         = microtime(true);
        $outputDirectory = $buildConfig->outputDirectory();
        $cacheDirectory  = $buildConfig->cacheDirectory();

        $theme    = new ThemeResolver(theme: $config->theme());
        $renderer = new PageRenderer(theme: $theme);
        $urls     = new UrlBuilder($config->site()->basePath());
        $search   = new SearchIndexBuilder(urls: $urls);

        $assets     = $this->assets($theme, $config, $repositories);
        $assetNames = array_keys($assets);

        $this->filesystem->ensureDirectory($outputDirectory);

        $projects = $this->collectProjects(
            $config,
            $repositories,
            $options,
            $buildConfig,
            $output,
            $renderer,
            $assetNames,
            $cacheDirectory,
        );

        $summaries     = [];
        $entryPages    = [];
        $redirects     = [];
        $searchIndexes = [];
        $versions      = [];
        $pages         = [];

        foreach ($projects as $project) {
            $slug = $project['repository']->slug();

            if ([] === $project['pages']) {
                continue;
            }

            $labels = array_keys($project['pages']);
            $this->sortLabels($labels);

            $target = RedirectWriter::targetLabel($labels);

            $summaries[] = new ProjectSummary(
                slug        : $slug,
                title       : $project['result']->title(),
                versionLabel: $target,
                url         : $urls->project($slug),
                order       : $config->project($project['repository']->name())?->order() ?? PHP_INT_MAX,
                description : $config->project($project['repository']->name())?->description() ?? '',
            );

            $redirects[$slug]     = $target;
            $searchIndexes[$slug] = $search->toJson(
                $slug,
                $project['result']->title(),
                $project['pages'],
                $target,
            );

            $versions[$slug] = $this->versionLinks($labels, $target, $urls, $slug);
            $pages[$slug]    = $this->pageSets($slug, $project['pages']);

            $entry = $project['pages'][$target][0] ?? null;
            foreach ($project['pages'][$target] ?? [] as $page) {
                if ('index.html' !== $this->innerPath($slug, $target, $page->outputPath())) {
                    continue;
                }

                $entry = $page;

                break;
            }

            $entryPages[$slug] = $entry instanceof RenderedPage ? $entry->html() : '';
        }

        usort($summaries, ProjectSummary::compare(...));

        $navigation = new NavigationBuilder($summaries, $versions, $pages, $urls);

        $this->writePages($config, $navigation, $projects, $outputDirectory, $output, $renderer);

        $assembly = new SiteAssembly(
            outputDirectory: $outputDirectory,
            config         : $config,
            urls           : $urls,
            projects       : $summaries,
            entryPages     : $entryPages,
            redirects      : $redirects,
            searchIndexes  : $searchIndexes,
            siteSearchIndex: new SiteIndexBuilder()->toJson($summaries, $urls),
            assets         : $assets,
            stylesheet     : $urls->asset($theme->siteName()),
        );

        $this->assembler->assemble($assembly);

        $violations = $this->guard->inspect($outputDirectory);
        foreach ($violations as $violation) {
            $output->error(sprintf('non-deterministic output: %s', $violation));
        }

        $results = [];
        foreach ($projects as $project) {
            $results[] = $project['result'];
        }

        $report = BuildReport::fromResults($results, ExitCode::Success->value);
        $report = $report->withExitCode(ExitCode::fromBuildReport($report->toArray())->value);

        $output->timing('build', microtime(true) - $started);

        return new BuildRun($report, $outputDirectory, $violations);
    }

    /**
     * Reports what a build would do without publishing anything.
     *
     * Every project goes through the same collection and rendering the build performs, so a broken
     * include or a missing entry is reported with the reason code and the corrective action a real
     * build would carry. No page, asset, or aggregate document is written; only the render cache is
     * touched, and it holds nothing the output directory owns.
     *
     * @param list<DiscoveredRepository> $repositories in discovery order
     *
     * @throws InvalidArgumentException when a project reports a status outside the schema vocabulary
     * @throws RuntimeException         when a page cannot be rendered
     */
    public function validate(
        SiteConfig $config,
        array $repositories,
        Options $options,
        BuildConfig $buildConfig,
        Output $output,
    ): BuildReport {
        $theme    = new ThemeResolver(theme: $config->theme());
        $renderer = new PageRenderer(theme: $theme);

        $projects = $this->collectProjects(
            $config,
            $repositories,
            $options,
            $buildConfig,
            $output,
            $renderer,
            array_keys($this->assets($theme, $config, $repositories)),
            $buildConfig->cacheDirectory(),
        );

        $results = [];
        foreach ($projects as $project) {
            $results[] = $project['result'];
        }

        $report = BuildReport::fromResults($results, ExitCode::Success->value);

        return $report->withExitCode(ExitCode::fromBuildReport($report->toArray())->value);
    }

    /**
     * @param list<DiscoveredRepository> $repositories
     *
     * @return array<string, string>
     *
     * @throws RuntimeException when a theme asset cannot be read
     */
    private function assets(ThemeResolver $theme, SiteConfig $config, array $repositories): array
    {
        $assets = [
            $theme->siteName()         => $theme->siteCss(),
            $theme->themeScriptName()  => $theme->themeScript(),
            $theme->navScriptName()    => $theme->navScript(),
            $theme->searchCoreName()   => $theme->searchCore(),
            $theme->searchScriptName() => $theme->searchScript(),
        ];

        foreach ($repositories as $repository) {
            $accent = $config->project($repository->name())?->accent();

            $assets[$theme->accentName($accent)] = $theme->accentCss($accent);
        }

        $assets[$theme->accentName()] = $theme->accentCss();

        return $assets;
    }

    /**
     * Collects, renders, and grades one project.
     *
     * @param list<string> $assetNames
     *
     * @return array{repository: DiscoveredRepository, sources: ?RepositorySources, pages: array<string, list<RenderedPage>>, errors: list<SourceError>, result: ProjectResult}
     *
     * @throws InvalidArgumentException when a project reports a status outside the schema vocabulary
     * @throws SourceException          when a source cannot be collected
     * @throws RuntimeException         when a page cannot be rendered
     */
    private function buildProject(
        SiteConfig $config,
        DiscoveredRepository $repository,
        Options $options,
        BuildConfig $buildConfig,
        Output $output,
        PageRenderer $renderer,
        array $assetNames,
        string $cacheDirectory,
    ): array {
        $slug = $repository->slug();

        if (! $repository->onboarded()) {
            $output->detail(sprintf('%s: not onboarded, skipped', $slug));

            return $this->skipped($repository, 'not onboarded');
        }

        if ($repository->archived()) {
            $output->warning(sprintf('%s: archived, skipped', $slug));

            return $this->skipped($repository, 'archived');
        }

        $output->progress(sprintf('%s: collecting sources', $slug));

        $sources = $this->collector->collect(
            $repository,
            $config,
            $cacheDirectory,
            $options->refs[$repository->name()] ?? null,
        );

        foreach ($sources->warnings() as $warning) {
            $output->warning($warning);
        }

        $errors = $sources->errors();
        foreach ($errors as $error) {
            $output->warning(sprintf(
                '%s: %s (%s)',
                $error->path() ?? $repository->name(),
                $error->message(),
                $error->reasonCode()->value,
            ));
        }

        $byLabel = [];
        foreach ($sources->versions() as $version) {
            $byLabel[$version->label()] = $version;
        }

        /** @var array<string, list<RenderedPage>> $rendered */
        $rendered = [];

        foreach ($sources->sources() as $source) {
            $version = $byLabel[$source->version()] ?? null;

            if (! $version instanceof DocVersion) {
                $output->warning(sprintf(
                    '%s: %s has no matching version, skipped',
                    $repository->name(),
                    $source->relativePath(),
                ));

                continue;
            }

            try {
                $page = $this->renderPage(
                    $config,
                    $repository,
                    $version,
                    $source,
                    $sources->revision(),
                    $renderer,
                    $buildConfig,
                    $cacheDirectory,
                    $assetNames,
                );
            } catch (SourceException $exception) {
                $errors[] = $exception->error();
                $output->warning(sprintf(
                    '%s: %s (%s)',
                    $exception->error()->path() ?? $source->relativePath(),
                    $exception->error()->message(),
                    $exception->error()->reasonCode()->value,
                ));

                continue;
            }

            $rendered[$version->label()][] = $page;
            $output->detail(sprintf('%s: rendered %s', $slug, $page->outputPath()));
        }

        $count = 0;
        foreach ($rendered as $pages) {
            $count += count($pages);
        }

        $status = $this->status($sources, $errors);

        $output->progress(sprintf('%s: %d page(s) %s', $slug, $count, $status));

        $labels = array_keys($rendered);
        $this->sortLabels($labels);

        $result = new ProjectResult(
            repository       : $repository->name(),
            slug             : $slug,
            status           : $status,
            title            : $config->project($repository->name())?->title(),
            revision         : $sources->revision(),
            versions         : $labels,
            pageCount        : count($sources->sources()),
            renderedPageCount: $count,
            errors           : $errors,
            warnings         : [],
            fetchAttempts    : $sources->fetched()?->attempts() ?? 0,
        );

        return [
            'repository' => $repository,
            'sources'    => $sources,
            'pages'      => $rendered,
            'errors'     => $errors,
            'result'     => $result,
        ];
    }

    /**
     * Collects, renders, and grades every project without writing anything.
     *
     * @param list<DiscoveredRepository> $repositories
     * @param list<string>               $assetNames
     *
     * @return list<array{repository: DiscoveredRepository, sources: ?RepositorySources, pages: array<string, list<RenderedPage>>, errors: list<SourceError>, result: ProjectResult}>
     *
     * @throws InvalidArgumentException when a project reports a status outside the schema vocabulary
     * @throws SourceException          when a source cannot be collected
     * @throws RuntimeException         when a page cannot be rendered
     */
    private function collectProjects(
        SiteConfig $config,
        array $repositories,
        Options $options,
        BuildConfig $buildConfig,
        Output $output,
        PageRenderer $renderer,
        array $assetNames,
        string $cacheDirectory,
    ): array {
        $projects = [];

        foreach ($repositories as $repository) {
            $projects[] = $this->buildProject(
                $config,
                $repository,
                $options,
                $buildConfig,
                $output,
                $renderer,
                $assetNames,
                $cacheDirectory,
            );
        }

        return $projects;
    }

    private function innerPath(string $slug, string $label, string $pagePath): string
    {
        $prefix = "{$slug}/{$label}/";

        return 0 === strpos($pagePath, $prefix) ? substr($pagePath, strlen($prefix)) : 'index.html';
    }

    /**
     * @param array<string, list<RenderedPage>> $pages
     *
     * @return array<string, array<string, true>>
     */
    private function pageSets(string $slug, array $pages): array
    {
        $sets = [];

        foreach ($pages as $label => $rendered) {
            foreach ($rendered as $page) {
                $sets[$label][$this->innerPath($slug, $label, $page->outputPath())] = true;
            }
        }

        return $sets;
    }

    /**
     * @param list<string> $assetNames
     *
     * @throws SourceException  when the page cannot be rendered
     * @throws RuntimeException when a theme asset cannot be read
     */
    private function renderPage(
        SiteConfig $config,
        DiscoveredRepository $repository,
        DocVersion $version,
        PreparedSource $source,
        ?string $revision,
        PageRenderer $renderer,
        BuildConfig $buildConfig,
        string $cacheDirectory,
        array $assetNames,
    ): RenderedPage {
        $accent = $config->project($repository->name())?->accent();
        $key    = $this->cache->key([
            'slug'               => $repository->slug(),
            'title'              => $config->project($repository->name())?->title() ?? '',
            'accent'             => $accent ?? '',
            'tocLevels'          => $config->render()->tocLevels(),
            'searchExtractLimit' => $config->render()->searchExtractLimit(),
            'safeMode'           => $config->render()->safeMode(),
            'syntaxHighlighting' => $config->render()->syntaxHighlighting(),
            'basePath'           => $config->site()->basePath(),
            'baseUrl'            => $config->site()->baseUrl(),
            'siteName'           => $config->site()->name(),
            'theme'              => $config->theme()->name(),
            'repository'         => $repository->name(),
            'revision'           => $revision ?? '',
            'version'            => $version->label(),
            'path'               => $source->relativePath(),
            'content'            => $source->hash(),
            'assets'             => $assetNames,
        ]);

        if (! $buildConfig->full()) {
            $cached = $this->cache->load($cacheDirectory, $key);

            if ($cached instanceof RenderedPage) {
                return $cached;
            }
        }

        $page = $renderer->render($config, $repository, $version, $source);
        $this->cache->store($cacheDirectory, $key, $page);

        return $page;
    }

    /**
     * @return array{repository: DiscoveredRepository, sources: ?RepositorySources, pages: array<string, list<RenderedPage>>, errors: list<SourceError>, result: ProjectResult}
     *
     * @throws InvalidArgumentException when a project reports a status outside the schema vocabulary
     */
    private function skipped(DiscoveredRepository $repository, string $reason): array
    {
        return [
            'repository' => $repository,
            'sources'    => null,
            'pages'      => [],
            'errors'     => [],
            'result'     => new ProjectResult(
                repository   : $repository->name(),
                slug         : $repository->slug(),
                status       : ProjectResult::SKIPPED,
                warnings     : [new SourceError(ReasonCode::Archived, $reason)],
                fetchAttempts: 0,
            ),
        ];
    }

    /**
     * @param list<string> $labels
     */
    private function sortLabels(array &$labels): void
    {
        usort($labels, static function (string $left, string $right): int {
            $leftNumber  = 1 === preg_match('/^v([0-9]+)$/', $left, $matches) ? (int) $matches[1] : 0;
            $rightNumber = 1 === preg_match('/^v([0-9]+)$/', $right, $matches) ? (int) $matches[1] : 0;

            if ($leftNumber === $rightNumber) {
                return strcmp($left, $right);
            }

            return $rightNumber <=> $leftNumber;
        });
    }

    /**
     * @param list<SourceError> $errors
     */
    private function status(RepositorySources $sources, array $errors): string
    {
        if (! $sources->isSuccessful()) {
            return ProjectResult::FAILED;
        }

        return [] === $errors ? ProjectResult::OK : ProjectResult::PARTIAL;
    }

    /**
     * @param list<string> $labels newest first
     *
     * @return list<VersionLink>
     */
    private function versionLinks(array $labels, string $target, UrlBuilder $urls, string $slug): array
    {
        $links = [];

        foreach ($labels as $label) {
            $links[] = new VersionLink($label, $urls->version($slug, $label), $label === $target);
        }

        return $links;
    }

    /**
     * @param list<array{repository: DiscoveredRepository, sources: ?RepositorySources, pages: array<string, list<RenderedPage>>, errors: list<SourceError>, result: ProjectResult}> $projects
     *
     * @throws RuntimeException when a theme asset cannot be read
     */
    private function writePages(
        SiteConfig $config,
        NavigationBuilder $navigation,
        array $projects,
        string $outputDirectory,
        Output $output,
        PageRenderer $renderer,
    ): void {
        $basePath = $config->site()->basePath();

        foreach ($projects as $project) {
            $slug = $project['repository']->slug();

            foreach ($project['pages'] as $label => $pages) {
                foreach ($pages as $page) {
                    $chrome = $navigation->chrome($slug, $label, $page->outputPath());
                    $final  = $renderer->withChrome($page, $chrome, $basePath);

                    $this->writer->write($outputDirectory, $final->outputPath(), $final->html());
                }
            }

            $output->detail(sprintf('%s: wrote pages', $slug));
        }
    }
}
