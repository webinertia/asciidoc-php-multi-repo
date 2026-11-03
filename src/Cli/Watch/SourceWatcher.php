<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Cli\Watch;

use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Path;

use function clearstatcache;
use function filemtime;
use function filesize;
use function hash_file;
use function is_dir;
use function is_file;
use function is_string;
use function ksort;
use function sprintf;

/**
 * Reads the current state of every watched source so two snapshots can be compared.
 *
 * The signature is modification time, size and content hash together. Modification time alone is
 * not enough: coarse filesystem timestamps and a same-length edit can both leave it unchanged, and
 * the whole point of the poll is to notice the edit the user just made.
 */
final readonly class SourceWatcher
{
    private const int SORT_STRING = 2;

    /**
     * @param list<WatchedSource> $sources
     */
    public function __construct(
        private array $sources,
        private Filesystem $filesystem = new Filesystem(),
    ) {}

    public function snapshot(): WatchSnapshot
    {
        clearstatcache();

        /** @var array<string, string> $signatures */
        $signatures = [];
        /** @var array<string, string> $owners */
        $owners = [];

        foreach ($this->sources as $source) {
            $owner = $source->project() ?? '';

            foreach ($this->filesIn($source->path()) as $file) {
                $signatures[$file] = $this->signature($file);
                $owners[$file]     = $owner;
            }
        }

        ksort($signatures, self::SORT_STRING);
        ksort($owners, self::SORT_STRING);

        return new WatchSnapshot($signatures, $owners);
    }

    /**
     * @return list<WatchedSource>
     */
    public function sources(): array
    {
        return $this->sources;
    }

    /**
     * @return list<string>
     */
    private function filesIn(string $path): array
    {
        if (is_file($path)) {
            return [$path];
        }

        if (! is_dir($path)) {
            return [];
        }

        /** @var list<string> $files */
        $files = [];

        foreach ($this->filesystem->scan($path) as $relative) {
            $files[] = Path::join($path, $relative);
        }

        return $files;
    }

    private function signature(string $file): string
    {
        if (! is_file($file)) {
            return 'missing';
        }

        $mtime = filemtime($file);
        $size  = filesize($file);
        $hash  = hash_file('sha256', $file);

        return sprintf(
            '%s:%s:%s',
            false === $mtime ? '0' : (string) $mtime,
            false === $size ? '0' : (string) $size,
            is_string($hash) ? $hash : '',
        );
    }
}
