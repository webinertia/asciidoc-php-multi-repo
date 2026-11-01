<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function hash;
use function hash_file;
use function is_dir;
use function is_file;
use function is_string;
use function rename;
use function rtrim;
use function sort;
use function sprintf;
use function str_replace;
use function strlen;
use function substr;
use function tempnam;
use function unlink;

/**
 * Filesystem operations that keep output deterministic: writes only change a file when its bytes
 * change, and recursive scans always return the same sorted order.
 */
final class Filesystem
{
    private const SORT_STRING = 2;

    public function ensureDirectory(string $directory): void
    {
        if ('' === $directory || is_dir($directory)) {
            return;
        }

        if (! @mkdir($directory, 0o777, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create directory "%s".', $directory));
        }
    }

    public function exists(string $path): bool
    {
        return is_file($path);
    }

    public function hash(string $path): string
    {
        $hash = @hash_file('sha256', $path);
        if (! is_string($hash)) {
            throw new RuntimeException(sprintf('Unable to hash "%s".', $path));
        }

        return $hash;
    }

    /**
     * Convenience wrapper used by callers that only need the hex digest of a string.
     */
    public function hashContents(string $contents): string
    {
        return hash('sha256', $contents);
    }

    public function read(string $path): string
    {
        $contents = @file_get_contents($path);
        if (! is_string($contents)) {
            throw new RuntimeException(sprintf('Unable to read "%s".', $path));
        }

        return $contents;
    }

    /**
     * Returns every file below $directory as a sorted list of relative paths using "/" separators.
     *
     * @return list<string>
     */
    public function scan(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $base = rtrim(Path::normalise($directory), DIRECTORY_SEPARATOR);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        /** @var list<string> $paths */
        $paths = [];
        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            $absolute = Path::normalise($file->getPathname());
            $relative = '' === $base ? $absolute : substr($absolute, strlen($base) + 1);
            $paths[]  = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
        }

        sort($paths, self::SORT_STRING);

        return $paths;
    }

    public function writeAtomic(string $path, string $contents): void
    {
        $directory = dirname($path);
        $this->ensureDirectory($directory);

        $temporary = @tempnam($directory, '.tmp-');
        if (false === $temporary) {
            throw new RuntimeException(sprintf('Unable to create a temporary file in "%s".', $directory));
        }

        try {
            if (@file_put_contents($temporary, $contents, LOCK_EX) === false) {
                throw new RuntimeException(sprintf('Unable to write "%s".', $path));
            }

            if (@rename($temporary, $path)) {
                return;
            }

            // Some platforms refuse to replace an existing destination. Removing it first keeps
            // the operation correct even though it is no longer atomic in that fallback path.
            if (is_file($path)) {
                @unlink($path);
            }

            if (@rename($temporary, $path)) {
                return;
            }

            throw new RuntimeException(sprintf('Unable to replace "%s".', $path));
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    /**
     * Writes the file only when its current bytes differ. Returns true when the file was written.
     */
    public function writeIfChanged(string $path, string $contents): bool
    {
        if (is_file($path)) {
            $existing = @file_get_contents($path);
            if (is_string($existing) && $existing === $contents) {
                return false;
            }
        }

        $this->writeAtomic($path, $contents);

        return true;
    }
}
