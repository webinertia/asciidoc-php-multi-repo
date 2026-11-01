<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Source;

use Webware\AsciidocPhp\MultiRepo\Support\Path;

use function str_starts_with;

/**
 * One repository as it sits on disk after fetching, described only by data.
 *
 * Every path in `files` is relative to the repository root and uses "/" separators, so the caller
 * never has to know which platform produced it.
 */
final readonly class FetchedRepository
{
    /**
     * @param list<string> $files sorted paths relative to the repository root
     */
    public function __construct(
        private string $repositoryName,
        private string $revision,
        private string $rootPath,
        private string $documentationDirectory,
        private array $files,
        private int $attempts = 1,
        private bool $checkedOut = true,
    ) {}

    /**
     * How many fetch attempts the resolved revision took. One means it succeeded first time.
     */
    public function attempts(): int
    {
        return $this->attempts;
    }

    /**
     * Whether the worktree was updated. A re-fetch of an unchanged revision leaves it alone.
     */
    public function checkedOut(): bool
    {
        return $this->checkedOut;
    }

    /**
     * The absolute path of the documentation directory. It may not exist on disk; use
     * hasDocumentation() to decide.
     */
    public function docPath(): string
    {
        return Path::join($this->rootPath, $this->documentationDirectory);
    }

    public function documentationDirectory(): string
    {
        return $this->documentationDirectory;
    }

    /**
     * @return list<string>
     */
    public function files(): array
    {
        return $this->files;
    }

    public function hasDocumentation(): bool
    {
        $prefix = "{$this->documentationDirectory}/";

        foreach ($this->files as $file) {
            if (str_starts_with($file, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function repositoryName(): string
    {
        return $this->repositoryName;
    }

    public function revision(): string
    {
        return $this->revision;
    }

    public function rootPath(): string
    {
        return $this->rootPath;
    }
}
