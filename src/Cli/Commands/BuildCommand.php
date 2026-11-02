<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Cli\Commands;

use Override;
use RuntimeException;
use Webware\AsciidocPhp\MultiRepo\Build\ContentWriter;
use Webware\AsciidocPhp\MultiRepo\Cli\ExitCode;
use Webware\AsciidocPhp\MultiRepo\Cli\Options;
use Webware\AsciidocPhp\MultiRepo\Cli\Output;
use Webware\AsciidocPhp\MultiRepo\Cli\ProjectFilter;
use Webware\AsciidocPhp\MultiRepo\Config\ConfigException;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfig;
use Webware\AsciidocPhp\MultiRepo\Discovery\DiscoveredRepository;
use Webware\AsciidocPhp\MultiRepo\Discovery\DiscoveryException;
use Webware\AsciidocPhp\MultiRepo\Discovery\DiscoveryFactory;
use Webware\AsciidocPhp\MultiRepo\Discovery\SlugException;
use Webware\AsciidocPhp\MultiRepo\Render\PageRenderer;
use Webware\AsciidocPhp\MultiRepo\Source\DocVersion;
use Webware\AsciidocPhp\MultiRepo\Source\RepositorySources;
use Webware\AsciidocPhp\MultiRepo\Source\SourceCollector;
use Webware\AsciidocPhp\MultiRepo\Source\SourceException;
use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use Webware\AsciidocPhp\MultiRepo\Theme\ThemeResolver;

use function count;
use function getcwd;
use function sprintf;

/**
 * Discovers, fetches, renders, and writes the whole site.
 *
 * A page that cannot be rendered is reported with its reason code and its source path, and the run
 * finishes with a degraded exit code rather than an internal error, so the pages that did render are
 * still published and the output says exactly what failed.
 */
final class BuildCommand implements CommandInterface
{
    public function __construct(
        private readonly DiscoveryFactory $discovery = new DiscoveryFactory(),
        private readonly SourceCollector $collector = new SourceCollector(),
        private readonly Filesystem $filesystem = new Filesystem(),
        private readonly ContentWriter $writer = new ContentWriter(),
    ) {}

    /**
     * @throws ConfigException when the manifest cannot be loaded
     * @throws RuntimeException when a shipped asset is missing
     */
    #[Override]
    public function execute(Options $options, SiteConfig $config, Output $output): ExitCode
    {
        if (null !== $options->basePath) {
            $config = $config->withSite($config->site()->withBasePath($options->basePath));
        }

        try {
            $repositories = ProjectFilter::apply(
                $this->discovery->create($options->manifest)->discover($config),
                $options,
            );
        } catch (SlugException $exception) {
            $output->error($exception->getMessage());

            return ExitCode::ConfigError;
        } catch (DiscoveryException $exception) {
            $output->error($exception->getMessage());

            return ExitCode::DiscoveryError;
        }

        $outputDirectory = $this->outputDirectory($options, $config);
        $cacheDirectory  = $this->cacheDirectory($options, $config);
        $theme           = new ThemeResolver(theme: $config->theme());
        $renderer        = new PageRenderer(theme: $theme);

        $this->filesystem->ensureDirectory($outputDirectory);
        $this->writeAssets($outputDirectory, $config, $repositories, $theme);

        $statuses = [];

        foreach ($repositories as $repository) {
            if (! $repository->onboarded()) {
                $output->detail(sprintf('%s: not onboarded, skipped', $repository->slug()));
                $statuses[] = 'skipped';

                continue;
            }

            if ($repository->archived()) {
                $output->warning(sprintf('%s: archived, skipped', $repository->slug()));
                $statuses[] = 'skipped';

                continue;
            }

            $sources = $this->collector->collect(
                $repository,
                $config,
                $cacheDirectory,
                $options->refs[$repository->name()] ?? null,
            );

            foreach ($sources->warnings() as $warning) {
                $output->warning($warning);
            }

            foreach ($sources->errors() as $error) {
                $output->warning(sprintf(
                    '%s: %s (%s)',
                    $error->path() ?? $repository->name(),
                    $error->message(),
                    $error->reasonCode()->value,
                ));
            }

            $count = $this->renderProject($config, $repository, $sources, $renderer, $outputDirectory, $output);

            $output->write(sprintf('%s: %d page(s)', $repository->slug(), $count));

            $statuses[] = $sources->isSuccessful() && ! $this->hasFailures($sources, $count)
                ? 'ok'
                : 'render-failed';
        }

        $output->write(sprintf('output directory: %s', $outputDirectory));

        return ExitCode::fromProjectStatuses(...$statuses);
    }

    #[Override]
    public function name(): string
    {
        return 'build';
    }

    #[Override]
    public function summary(): string
    {
        return 'discover, fetch, render, assemble, write, and report.';
    }

    private function cacheDirectory(Options $options, SiteConfig $config): string
    {
        if (null === $options->cache) {
            return $config->output()->cacheDirectory();
        }

        $base = getcwd();

        return Path::resolve(false === $base ? '.' : $base, $options->cache);
    }

    private function hasFailures(RepositorySources $sources, int $rendered): bool
    {
        return [] !== $sources->errors() || count($sources->sources()) !== $rendered;
    }

    private function outputDirectory(Options $options, SiteConfig $config): string
    {
        if (null === $options->out) {
            return $config->output()->directory();
        }

        $base = getcwd();

        return Path::resolve(false === $base ? '.' : $base, $options->out);
    }

    /**
     * @throws RuntimeException when a shipped asset is missing
     */
    private function renderProject(
        SiteConfig $config,
        DiscoveredRepository $repository,
        RepositorySources $sources,
        PageRenderer $renderer,
        string $outputDirectory,
        Output $output,
    ): int {
        $versions = [];
        foreach ($sources->versions() as $version) {
            $versions[$version->label()] = $version;
        }

        $count = 0;

        foreach ($sources->sources() as $source) {
            $version = $versions[$source->version()] ?? null;

            if (! $version instanceof DocVersion) {
                $output->warning(sprintf(
                    '%s: %s has no matching version, skipped',
                    $repository->name(),
                    $source->relativePath(),
                ));

                continue;
            }

            try {
                $page = $renderer->render($config, $repository, $version, $source);
            } catch (SourceException $exception) {
                $error = $exception->error();
                $output->warning(sprintf(
                    '%s: %s (%s)',
                    $error->path() ?? $source->relativePath(),
                    $error->message(),
                    $error->reasonCode()->value,
                ));

                continue;
            }

            $this->writer->write($outputDirectory, $page->outputPath(), $page->html());
            $output->detail(sprintf('%s: rendered %s', $repository->slug(), $page->outputPath()));
            $count++;
        }

        return $count;
    }

    /**
     * @param list<DiscoveredRepository> $repositories
     *
     * @throws RuntimeException when a shipped asset is missing
     */
    private function writeAssets(
        string $outputDirectory,
        SiteConfig $config,
        array $repositories,
        ThemeResolver $theme,
    ): void {
        $assets = [
            $theme->siteName()        => $theme->siteCss(),
            $theme->themeScriptName() => $theme->themeScript(),
        ];

        foreach ($repositories as $repository) {
            $accent                              = $config->project($repository->name())?->accent();
            $assets[$theme->accentName($accent)] = $theme->accentCss($accent);
        }

        foreach ($assets as $name => $contents) {
            $this->writer->write($outputDirectory, "assets/{$name}", $contents);
        }
    }
}
