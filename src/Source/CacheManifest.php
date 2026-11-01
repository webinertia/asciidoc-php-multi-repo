<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Source;

/**
 * The index of what the cache currently holds, one entry per repository in slug order.
 */
final readonly class CacheManifest
{
    public const int SCHEMA_VERSION = 1;

    /**
     * @param list<CacheManifestEntry> $repositories
     */
    public function __construct(
        private array $repositories,
    ) {}

    /**
     * @return list<CacheManifestEntry>
     */
    public function repositories(): array
    {
        return $this->repositories;
    }

    /**
     * @return array{schemaVersion: int, repositories: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        $rows = [];

        foreach ($this->repositories as $repository) {
            $rows[] = $repository->toArray();
        }

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'repositories'  => $rows,
        ];
    }
}
