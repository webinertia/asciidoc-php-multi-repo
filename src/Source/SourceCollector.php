<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Source;

use Webware\AsciidocPhpMultiRepo\Config\SiteConfig;
use Webware\AsciidocPhpMultiRepo\Discovery\DiscoveredRepository;
use Webware\AsciidocPhpMultiRepo\Report\ReasonCode;

use function sprintf;

/**
 * Runs the whole Group 2 pipeline for one repository.
 *
 * Fetching, version detection, page enumeration, and source preparation are composed here and
 * nowhere else, so a caller cannot accidentally skip a stage. Problems are collected as reason
 * codes instead of being thrown, because one unbuildable repository must not stop the others.
 */
final class SourceCollector
{
    public function __construct(
        private readonly RepositoryFetcher $fetcher = new RepositoryFetcher(),
        private readonly VersionDetector $versions = new VersionDetector(),
        private readonly PageEnumerator $enumerator = new PageEnumerator(),
        private readonly SourcePreparer $preparer = new SourcePreparer(),
    ) {}

    /**
     * @param null|string $ref overrides the repository default branch
     */
    public function collect(
        DiscoveredRepository $repository,
        SiteConfig $config,
        string $cacheDirectory,
        ?string $ref = null,
    ): RepositorySources {
        try {
            $fetched = $this->fetcher->fetch($repository, $config, $cacheDirectory, $ref);
        } catch (SourceException $exception) {
            return RepositorySources::failed($repository, null, $exception->error());
        }

        if (! $fetched->hasDocumentation()) {
            return RepositorySources::failed($repository, $fetched, new SourceError(
                ReasonCode::NoAsciidocSources,
                sprintf(
                    '%s: no documentation directory at %s/.',
                    $repository->name(),
                    $config->source()->directory(),
                ),
                null,
                $config->source()->directory(),
                'Add a documentation directory, or correct source.directory.',
            ));
        }

        $selection = $this->versions->detect($fetched, $config);
        $pages     = [];
        $sources   = [];
        $errors    = [];

        foreach ($selection->versions() as $version) {
            try {
                $set = $this->enumerator->enumerate($fetched, $version, $config);
            } catch (SourceException $exception) {
                $errors[] = $exception->error();
                continue;
            }

            $pages[] = $set;

            foreach ($set->pages() as $page) {
                try {
                    $sources[] = $this->preparer->prepare($page);
                } catch (SourceException $exception) {
                    $errors[] = $exception->error();
                }
            }
        }

        return new RepositorySources(
            repository: $repository,
            fetched   : $fetched,
            versions  : $selection->versions(),
            pages     : $pages,
            sources   : $sources,
            warnings  : $selection->warnings(),
            errors    : $errors,
        );
    }
}
