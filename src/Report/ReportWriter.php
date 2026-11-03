<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Report;

use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Json;
use Webware\AsciidocPhp\MultiRepo\Support\Path;

use function dirname;
use function getcwd;

/**
 * Writes a build report to disk.
 *
 * A relative report path resolves against the working directory, matching every other path the CLI
 * accepts, and the parent directory is created so a first run into a fresh tree still writes.
 *
 * @api
 */
final readonly class ReportWriter
{
    public function __construct(
        private Json $json = new Json(),
        private Filesystem $files = new Filesystem(),
    ) {}

    /**
     * @return string the absolute path the report was written to
     */
    public function write(string $path, BuildReport $report): string
    {
        $resolved = Path::resolve(getcwd() ?: '.', $path);

        $this->files->ensureDirectory(dirname($resolved));
        $this->files->writeAtomic($resolved, $this->json->encode($report->toArray()));

        return $resolved;
    }
}
