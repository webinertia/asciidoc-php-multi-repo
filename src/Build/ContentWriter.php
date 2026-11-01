<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Build;

use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Path;

use function dirname;
use function explode;

/**
 * Writes a rendered page into the output directory.
 *
 * This is deliberately plain write semantics: the file is written every time the page is rendered.
 * Incremental writing, stale page removal, and the write-only-if-changed optimisation arrive with the
 * build determinism work, which is where the manifest that decides "changed" also arrives.
 *
 * @api
 */
final readonly class ContentWriter
{
    public function __construct(
        private Filesystem $filesystem = new Filesystem(),
    ) {}

    /**
     * Writes one page and returns the absolute path it was written to.
     */
    public function write(string $outputDirectory, string $relativePath, string $contents): string
    {
        $path = Path::join($outputDirectory, ...explode('/', $relativePath));

        $this->filesystem->ensureDirectory(dirname($path));
        $this->filesystem->writeAtomic($path, $contents);

        return $path;
    }
}
