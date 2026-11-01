<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhpMultiRepo\Support;

use Webware\AsciidocPhpMultiRepo\Support\Filesystem;
use Webware\AsciidocPhpMultiRepo\Support\Path;

use function bin2hex;
use function is_dir;
use function random_bytes;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function unlink;

/**
 * Creates a unique scratch directory per test and removes it again.
 */
trait TempDirectoryTrait
{
    private string $tempDirectory = '';

    protected function createTempDirectory(): string
    {
        $directory = Path::join(
            Path::normalise(sys_get_temp_dir()),
            'asciidoc-multi-repo-test-' . bin2hex(random_bytes(6)),
        );

        new Filesystem()->ensureDirectory($directory);

        return $this->tempDirectory = $directory;
    }

    protected function removeTempDirectory(): void
    {
        if ('' !== $this->tempDirectory) {
            $this->deleteDirectory($this->tempDirectory);
            $this->tempDirectory = '';
        }
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);

        if (false === $entries) {
            return;
        }

        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path = Path::join($directory, $entry);

            if (is_dir($path)) {
                $this->deleteDirectory($path);

                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }
}
