<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Source;

/**
 * One renderable page.
 *
 * `relativePath` is relative to the repository root and uses "/" separators, so it is stable across
 * platforms. `absolutePath` is the file the preparer reads.
 */
final readonly class DocPage
{
    public function __construct(
        private string $version,
        private string $relativePath,
        private string $absolutePath,
    ) {}

    public function absolutePath(): string
    {
        return $this->absolutePath;
    }

    public function relativePath(): string
    {
        return $this->relativePath;
    }

    public function version(): string
    {
        return $this->version;
    }
}
