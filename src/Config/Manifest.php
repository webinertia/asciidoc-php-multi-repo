<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Config;

/**
 * The offline discovery manifest: a repository list that replaces organization
 * property discovery.
 */
final readonly class Manifest
{
    public const int SUPPORTED_SCHEMA_VERSION = 1;

    /**
     * @param list<ManifestEntry> $repositories
     */
    public function __construct(
        private int $schemaVersion,
        private array $repositories,
        private string $path,
    ) {}

    /**
     * @return list<string>
     */
    public function names(): array
    {
        $names = [];

        foreach ($this->repositories as $entry) {
            $names[] = $entry->name();
        }

        return $names;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return list<ManifestEntry>
     */
    public function repositories(): array
    {
        return $this->repositories;
    }

    public function schemaVersion(): int
    {
        return $this->schemaVersion;
    }
}
