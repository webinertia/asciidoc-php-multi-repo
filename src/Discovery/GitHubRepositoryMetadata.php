<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Discovery;

use function is_array;
use function is_string;

/**
 * Maps the repository search payload to the two facts discovery needs: the default branch and
 * whether the repository is archived.
 *
 * A repository missing from the search result still has to be discovered, so `for()` answers with
 * the same defaults the configuration uses.
 */
final readonly class GitHubRepositoryMetadata
{
    /**
     * @param array<string, array{defaultBranch: string, archived: bool}> $repositories
     */
    private function __construct(
        private array $repositories,
    ) {}

    /**
     * @param list<mixed> $pages
     */
    public static function fromPages(array $pages): self
    {
        $repositories = [];

        foreach ($pages as $page) {
            if (! is_array($page)) {
                continue;
            }

            $items = $page['items'] ?? null;

            if (! is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $fullName = $item['full_name'] ?? null;

                if (! is_string($fullName)) {
                    continue;
                }

                $repositories[$fullName] = self::entry($item);
            }
        }

        return new self($repositories);
    }

    /**
     * @param array<array-key, mixed> $item
     *
     * @return array{defaultBranch: string, archived: bool}
     */
    private static function entry(array $item): array
    {
        $branch = $item['default_branch'] ?? null;

        return [
            'defaultBranch' => is_string($branch) && '' !== $branch ? $branch : 'main',
            'archived'      => true === ($item['archived'] ?? null),
        ];
    }

    /**
     * @return array{defaultBranch: string, archived: bool}
     */
    public function for(string $fullName): array
    {
        return $this->repositories[$fullName] ?? self::entry([]);
    }
}
