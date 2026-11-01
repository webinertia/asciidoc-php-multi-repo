<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Config;

use Webware\AsciidocPhpMultiRepo\Support\Path;

/**
 * One repository entry in an offline discovery manifest.
 */
final readonly class ManifestEntry
{
    public function __construct(
        private string $name,
        private ?string $slug,
        private string $defaultBranch,
        private bool $archived,
        private bool $onboarded,
        private ?string $path,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, ?string $baseDirectory): self
    {
        $path = Values::optionalString($data, 'path');

        return new self(
            Values::requiredString($data, 'name'),
            Values::optionalString($data, 'slug'),
            Values::string($data, 'defaultBranch', 'main'),
            Values::boolean($data, 'archived', false),
            Values::boolean($data, 'onboarded', true),
            $path === null || $baseDirectory === null ? $path : Path::resolve($baseDirectory, $path),
        );
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): ?string
    {
        return $this->slug;
    }

    public function defaultBranch(): string
    {
        return $this->defaultBranch;
    }

    public function archived(): bool
    {
        return $this->archived;
    }

    public function onboarded(): bool
    {
        return $this->onboarded;
    }

    /**
     * The local checkout of the repository, resolved against the manifest directory.
     */
    public function path(): ?string
    {
        return $this->path;
    }
}
