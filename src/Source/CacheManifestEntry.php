<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Source;

/**
 * One row of the cache manifest: what was fetched, and why it could not be.
 */
final readonly class CacheManifestEntry
{
    public function __construct(
        private string $name,
        private string $slug,
        private ?string $revision,
        private ?string $documentationPath,
        private ?SourceError $error = null,
    ) {}

    public function error(): ?SourceError
    {
        return $this->error;
    }

    public function hasError(): bool
    {
        return $this->error instanceof SourceError;
    }

    /**
     * @return array{
     *     name: string,
     *     slug: string,
     *     revision: ?string,
     *     documentationPath: ?string,
     *     error: ?array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'name'              => $this->name,
            'slug'              => $this->slug,
            'revision'          => $this->revision,
            'documentationPath' => $this->documentationPath,
            'error'             => $this->error?->toArray(),
        ];
    }
}
