<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Cli\Watch;

use function array_unique;
use function array_values;
use function count;
use function implode;
use function sort;
use function sprintf;

/**
 * The work one poll implies: which files changed, and which projects therefore have to be rebuilt.
 *
 * A change to a global source collapses the batch to `everything`, because there is no useful sense
 * in which a shared stylesheet affects one project.
 */
final readonly class WatchChange
{
    private const int SORT_STRING = 2;

    /**
     * @param list<string> $paths    Absolute paths of the files that appeared, changed, or vanished.
     * @param list<string> $projects Slugs of the projects the changed files belong to.
     */
    public function __construct(
        private array $paths = [],
        private array $projects = [],
        private bool $everything = false,
    ) {}

    public static function none(): self
    {
        return new self();
    }

    public function describe(): string
    {
        if ($this->everything) {
            return sprintf('%d file(s) changed, rebuilding every project', count($this->paths));
        }

        return sprintf(
            '%d file(s) changed, rebuilding %s',
            count($this->paths),
            [] === $this->projects ? 'no project' : implode(', ', $this->projects),
        );
    }

    public function everything(): bool
    {
        return $this->everything;
    }

    public function isEmpty(): bool
    {
        return [] === $this->paths && ! $this->everything;
    }

    public function merge(self $other): self
    {
        $paths = array_values(array_unique([...$this->paths, ...$other->paths]));
        sort($paths, self::SORT_STRING);

        $projects = array_values(array_unique([...$this->projects, ...$other->projects]));
        sort($projects, self::SORT_STRING);

        return new self($paths, $projects, $this->everything || $other->everything);
    }

    /**
     * @return list<string>
     */
    public function paths(): array
    {
        return $this->paths;
    }

    /**
     * @return list<string>
     */
    public function projects(): array
    {
        return $this->projects;
    }
}
