<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Discovery;

/**
 * One repository selected for the site, before it has been fetched.
 *
 * `path` is set only when discovery had a local checkout to point at, which is the case for the
 * offline manifest. A `null` path means the fetcher must resolve the repository remotely.
 */
final readonly class DiscoveredRepository
{
    public function __construct(
        private string $name,
        private string $slug,
        private string $defaultBranch = 'main',
        private bool $archived = false,
        private bool $onboarded = true,
        private ?string $path = null,
    ) {}

    public function archived(): bool
    {
        return $this->archived;
    }

    public function defaultBranch(): string
    {
        return $this->defaultBranch;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function onboarded(): bool
    {
        return $this->onboarded;
    }

    public function path(): ?string
    {
        return $this->path;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function withSlug(string $slug): self
    {
        return new self(
            $this->name,
            $slug,
            $this->defaultBranch,
            $this->archived,
            $this->onboarded,
            $this->path,
        );
    }
}
