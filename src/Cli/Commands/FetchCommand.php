<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Cli\Commands;

use Override;
use Webware\AsciidocPhp\MultiRepo\Cli\ExitCode;
use Webware\AsciidocPhp\MultiRepo\Cli\Options;
use Webware\AsciidocPhp\MultiRepo\Cli\Output;
use Webware\AsciidocPhp\MultiRepo\Cli\ProjectFilter;
use Webware\AsciidocPhp\MultiRepo\Config\ConfigException;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfig;
use Webware\AsciidocPhp\MultiRepo\Discovery\DiscoveryException;
use Webware\AsciidocPhp\MultiRepo\Discovery\DiscoveryFactory;
use Webware\AsciidocPhp\MultiRepo\Discovery\SlugException;
use Webware\AsciidocPhp\MultiRepo\Report\ReasonCode;
use Webware\AsciidocPhp\MultiRepo\Source\CacheManifest;
use Webware\AsciidocPhp\MultiRepo\Source\CacheManifestEntry;
use Webware\AsciidocPhp\MultiRepo\Source\SourceCollector;
use Webware\AsciidocPhp\MultiRepo\Source\SourceError;
use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Json;
use Webware\AsciidocPhp\MultiRepo\Support\Path;

use function count;
use function getcwd;
use function sprintf;

/**
 * Fetches every onboarded repository and writes the cache manifest.
 *
 * Per-repository problems are reported as warnings and recorded in the manifest with their reason
 * code. They do not change the exit code: `fetch` succeeds when discovery succeeded and the
 * manifest was written, and `build` is what turns a problem into a degraded run.
 */
final class FetchCommand implements CommandInterface
{
    public const string CACHE_MANIFEST_FILE = 'cache-manifest.json';

    public function __construct(
        private readonly DiscoveryFactory $discovery = new DiscoveryFactory(),
        private readonly SourceCollector $collector = new SourceCollector(),
        private readonly Filesystem $filesystem = new Filesystem(),
        private readonly Json $json = new Json(),
    ) {}

    /**
     * @throws ConfigException when the manifest cannot be loaded
     */
    #[Override]
    public function execute(Options $options, SiteConfig $config, Output $output): ExitCode
    {
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

        $cacheDirectory = $this->cacheDirectory($options, $config);
        $entries        = [];
        $withSources    = 0;

        foreach ($repositories as $repository) {
            if (! $repository->onboarded()) {
                $output->detail(sprintf('%s: not onboarded, skipped', $repository->slug()));
                continue;
            }

            if ($repository->archived()) {
                $output->warning(sprintf('%s: archived, skipped', $repository->slug()));
                $entries[] = new CacheManifestEntry(
                    $repository->name(),
                    $repository->slug(),
                    null,
                    null,
                    new SourceError(
                        ReasonCode::Archived,
                        sprintf('%s is archived, so its documentation is not published.', $repository->name()),
                        null,
                        null,
                        'Unarchive the repository, or remove it from discovery.',
                    ),
                );

                continue;
            }

            $output->progress(sprintf('%s: fetching', $repository->slug()));

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
                    $repository->name(),
                    $error->message(),
                    $error->reasonCode()->value,
                ));
            }

            $output->detail(sprintf(
                '%s: %d version(s), %d page(s), revision %s',
                $repository->slug(),
                count($sources->versions()),
                count($sources->sources()),
                $sources->revision() ?? 'unknown',
            ));

            if ($sources->isSuccessful()) {
                $withSources++;
            }

            $entries[] = new CacheManifestEntry(
                $repository->name(),
                $repository->slug(),
                $sources->revision(),
                $sources->documentationPath(),
                $sources->errors()[0] ?? null,
            );
        }

        $manifestPath = Path::join($cacheDirectory, self::CACHE_MANIFEST_FILE);

        $this->filesystem->writeIfChanged(
            $manifestPath,
            $this->json->encode(new CacheManifest($entries)->toArray()),
        );

        $output->write(sprintf(
            '%d repositories, %d with sources, %d with problems',
            count($entries),
            $withSources,
            count($entries) - $withSources,
        ));
        $output->write(sprintf('cache manifest: %s', $manifestPath));

        return ExitCode::Success;
    }

    #[Override]
    public function name(): string
    {
        return 'fetch';
    }

    #[Override]
    public function summary(): string
    {
        return 'Fetch documentation sources into the cache.';
    }

    private function cacheDirectory(Options $options, SiteConfig $config): string
    {
        if (null === $options->cache) {
            return $config->output()->cacheDirectory();
        }

        $base = getcwd();

        return Path::resolve(false === $base ? '.' : $base, $options->cache);
    }
}
