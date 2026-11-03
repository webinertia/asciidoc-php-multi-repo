<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Config;

use Webware\AsciidocPhp\MultiRepo\Support\Path;

use function dirname;
use function is_string;
use function sprintf;

/**
 * Reads an offline discovery manifest and validates it against the packaged schema.
 */
final class ManifestLoader
{
    private readonly string $schemaPath;

    public function __construct(
        private readonly DocumentReader $reader = new DocumentReader(),
        ?string $schemaPath = null,
    ) {
        $this->schemaPath = $schemaPath ?? Path::join(dirname(__DIR__, levels: 2), 'config', 'manifest.schema.json');
    }

    /**
     * @throws ConfigException when the manifest is missing, malformed, or invalid
     */
    public function load(string $path): Manifest
    {
        $document = $this->reader->read($path, $this->schemaPath);
        $absolute = $this->reader->absolute($path);

        $this->assertSupportedSchemaVersion($document, $absolute);

        $repositories = $document['repositories'] ?? [];

        if (! is_array($repositories)) {
            // The schema has already rejected this shape; keep the static type honest.
            $repositories = [];
        }

        $entries = [];
        $base    = dirname($absolute);

        /** @var array<array-key, mixed> $repository */
        foreach ($repositories as $repository) {
            if (! is_array($repository)) {
                continue;
            }

            /** @var array<string, mixed> $repository */
            $entries[] = ManifestEntry::fromArray($repository, $base);
        }

        return new Manifest(Manifest::SUPPORTED_SCHEMA_VERSION, $entries, $absolute);
    }

    public function schemaPath(): string
    {
        return $this->schemaPath;
    }

    /**
     * @param array<string, mixed> $document
     */
    private function assertSupportedSchemaVersion(array $document, string $path): void
    {
        $version = $document['schemaVersion'] ?? null;

        if (! is_string($version) && ! is_int($version) && ! is_float($version)) {
            return;
        }

        if ((int) $version === Manifest::SUPPORTED_SCHEMA_VERSION) {
            return;
        }

        throw new ConfigException(
            sprintf(
                '%s: schemaVersion %s is not supported. This release supports schemaVersion %d.',
                $path,
                (string) $version,
                Manifest::SUPPORTED_SCHEMA_VERSION,
            ),
            '/schemaVersion',
        );
    }
}
