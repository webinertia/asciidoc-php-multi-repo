<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Source;

use Webware\AsciidocPhp\MultiRepo\Discovery\DiscoveredRepository;

/**
 * Everything Group 2 learned about one repository, including the problems it found.
 *
 * A failure never throws past this object: `errors` carries the reason codes the report needs, and
 * a repository with an error is simply not renderable.
 */
final readonly class RepositorySources
{
    /**
     * @param list<DocVersion>     $versions
     * @param list<PageSet>        $pages
     * @param list<PreparedSource> $sources
     * @param list<string>         $warnings
     * @param list<SourceError>    $errors
     */
    public function __construct(
        private DiscoveredRepository $repository,
        private ?FetchedRepository $fetched = null,
        private array $versions = [],
        private array $pages = [],
        private array $sources = [],
        private array $warnings = [],
        private array $errors = [],
    ) {}

    public static function failed(
        DiscoveredRepository $repository,
        ?FetchedRepository $fetched,
        SourceError $error,
    ): self {
        return new self(
            repository: $repository,
            fetched   : $fetched,
            errors    : [$error],
        );
    }

    /**
     * The documentation directory relative to the repository root, or null when it is absent.
     */
    public function documentationPath(): ?string
    {
        if (! $this->fetched instanceof FetchedRepository || ! $this->fetched->hasDocumentation()) {
            return null;
        }

        return $this->fetched->documentationDirectory();
    }

    /**
     * @return list<SourceError>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function fetched(): ?FetchedRepository
    {
        return $this->fetched;
    }

    public function isSuccessful(): bool
    {
        return [] === $this->errors;
    }

    /**
     * @return list<PageSet>
     */
    public function pages(): array
    {
        return $this->pages;
    }

    public function repository(): DiscoveredRepository
    {
        return $this->repository;
    }

    public function revision(): ?string
    {
        return $this->fetched?->revision();
    }

    /**
     * @return list<PreparedSource>
     */
    public function sources(): array
    {
        return $this->sources;
    }

    /**
     * @return list<DocVersion>
     */
    public function versions(): array
    {
        return $this->versions;
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }
}
