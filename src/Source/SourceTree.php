<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Source;

use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use Webware\AsciidocPhp\MultiRepo\Support\ProcessRunner;

use function file_exists;
use function rtrim;
use function str_starts_with;
use function substr;
use function trim;

use const DIRECTORY_SEPARATOR;

/**
 * Reads a local source tree: the files below a root and the revision that identifies them.
 */
final readonly class SourceTree
{
    private const float GIT_TIMEOUT_SECONDS = 120.0;

    private const int REVISION_LENGTH = 64;

    public function __construct(
        private Filesystem $filesystem = new Filesystem(),
        private ProcessRunner $runner = new ProcessRunner(),
    ) {}

    /**
     * Every file below the root, without the git metadata directory and without the paths this
     * build writes.
     *
     * @param list<string> $excluded absolute paths that must not be treated as sources
     *
     * @return list<string>
     */
    public function files(string $root, array $excluded = []): array
    {
        $files = [];

        foreach ($this->filesystem->scan($root) as $file) {
            if (str_starts_with($file, '.git/')) {
                continue;
            }

            if ($this->isGenerated($root, $file, $excluded)) {
                continue;
            }

            $files[] = $file;
        }

        return $files;
    }

    /**
     * The commit when the root is a git repository, a digest of the listed files otherwise.
     *
     * @param list<string> $files
     */
    public function revision(string $root, array $files): string
    {
        // `file_exists`, not `is_dir`: in a git worktree (and in a submodule) `.git` is a file
        // holding a `gitdir:` pointer, and treating that as "not a repository" would fall back to
        // digesting the whole tree, including the build output this process is writing.
        if (file_exists(Path::join($root, '.git'))) {
            $result = $this->runner->run(
                ['git', 'rev-parse', 'HEAD'],
                $root,
                self::GIT_TIMEOUT_SECONDS,
            );

            if ($result->successful()) {
                return trim($result->stdout());
            }
        }

        $material = '';

        foreach ($files as $file) {
            $hash     = $this->filesystem->hash(Path::join($root, $file));
            $material .= "{$file}:{$hash}\n";
        }

        return substr(
            string: $this->filesystem->hashContents($material),
            offset: 0,
            length: self::REVISION_LENGTH,
        );
    }

    /**
     * @param list<string> $excluded
     */
    private function isGenerated(string $root, string $file, array $excluded): bool
    {
        $absolute = Path::normalise(Path::join($root, $file));

        foreach ($excluded as $path) {
            $prefix = rtrim(Path::normalise($path), characters: '/\\');

            if ('' === $prefix) {
                continue;
            }

            if ($absolute === $prefix || str_starts_with($absolute, $prefix . DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }
}
