<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Cli\Watch;

use function array_key_exists;
use function array_keys;
use function array_unique;
use function array_values;
use function sort;

/**
 * The state of every watched file at one moment: a signature per path, plus the project that owns
 * the path.
 *
 * Ownership travels with the snapshot so a diff never has to re-match a path against a directory
 * prefix, which keeps the comparison a plain key walk and makes a deleted file as visible as a
 * changed one.
 */
final readonly class WatchSnapshot
{
    private const int SORT_STRING = 2;

    /**
     * @param array<string, string> $signatures Absolute path to a cheap change signature.
     * @param array<string, string> $owners     Absolute path to the owning project slug, '' for a
     *                                          source that rebuilds every project.
     */
    public function __construct(
        private array $signatures = [],
        private array $owners = [],
    ) {}

    /**
     * The change that turns this snapshot into $next.
     */
    public function changeAgainst(self $next): WatchChange
    {
        /** @var list<string> $paths */
        $paths = [];
        /** @var list<string> $projects */
        $projects   = [];
        $everything = false;

        foreach ($next->signatures as $path => $signature) {
            if (($this->signatures[$path] ?? null) === $signature) {
                continue;
            }

            $paths[] = $path;
            $owner   = $next->owners[$path] ?? '';

            if ('' === $owner) {
                $everything = true;

                continue;
            }

            $projects[] = $owner;
        }

        foreach (array_keys($this->signatures) as $path) {
            if (array_key_exists($path, $next->signatures)) {
                continue;
            }

            $paths[] = $path;
            $owner   = $this->owners[$path] ?? '';

            if ('' === $owner) {
                $everything = true;

                continue;
            }

            $projects[] = $owner;
        }

        if ([] === $paths && ! $everything) {
            return WatchChange::none();
        }

        sort($paths, self::SORT_STRING);

        $unique = array_values(array_unique($projects));
        sort($unique, self::SORT_STRING);

        return new WatchChange($paths, $unique, $everything);
    }

    public function isEmpty(): bool
    {
        return [] === $this->signatures;
    }

    /**
     * @return array<string, string>
     */
    public function signatures(): array
    {
        return $this->signatures;
    }
}
