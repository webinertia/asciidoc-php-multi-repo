<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Config;

use Webware\AsciidocPhpMultiRepo\Support\Path;

use function dirname;
use function is_string;
use function sprintf;

/**
 * Reads a site.json document, validates it against the packaged schema, and
 * produces a SiteConfig.
 */
final class SiteConfigLoader
{
    private readonly string $schemaPath;

    public function __construct(
        private readonly DocumentReader $reader = new DocumentReader(),
        ?string $schemaPath = null,
    ) {
        $this->schemaPath = $schemaPath ?? Path::join(dirname(__DIR__, 2), 'config', 'site.schema.json');
    }

    /**
     * @throws ConfigException when the file is missing, unreadable, malformed, or invalid
     */
    public function load(string $path): SiteConfig
    {
        $document = $this->reader->read($path, $this->schemaPath);
        $absolute = $this->reader->absolute($path);

        $this->assertSupportedSchemaVersion($document, $absolute);

        return SiteConfig::fromArray($document, $absolute);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return $this->reader->schema($this->schemaPath);
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

        if (SiteConfig::SUPPORTED_SCHEMA_VERSION === $version) {
            return;
        }

        if (! is_string($version) && ! is_int($version) && ! is_float($version)) {
            // Leave the shape of the value to the schema so the reported
            // pointer and message stay consistent with every other failure.
            return;
        }

        if ((int) $version === SiteConfig::SUPPORTED_SCHEMA_VERSION) {
            return;
        }

        throw new ConfigException(
            sprintf(
                '%s: schemaVersion %s is not supported. This release supports schemaVersion %d.',
                $path,
                (string) $version,
                SiteConfig::SUPPORTED_SCHEMA_VERSION,
            ),
            '/schemaVersion',
        );
    }
}
