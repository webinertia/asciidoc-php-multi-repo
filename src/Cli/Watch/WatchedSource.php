<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Cli\Watch;

use Webware\AsciidocPhp\MultiRepo\Support\Path;

use function rtrim;

/**
 * One directory tree whose changes matter to a rebuild.
 *
 * A project source rebuilds that project. A global source (the package's own `resources/` tree, the
 * `site.json` file) rebuilds every project, because a theme asset or a site setting is shared by all
 * of them.
 */
final readonly class WatchedSource
{
    private function __construct(
        private ?string $project,
        private string $path,
    ) {}

    /**
     * A source whose changes invalidate only the named project.
     */
    public static function forProject(string $slug, string $path): self
    {
        return new self($slug, self::trim($path));
    }

    /**
     * A source whose changes invalidate every project.
     */
    public static function global(string $path): self
    {
        return new self(null, self::trim($path));
    }

    private static function trim(string $path): string
    {
        $trimmed = rtrim(
            string    : Path::normalise($path),
            characters: '/\\',
        );

        return '' === $trimmed ? $path : $trimmed;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function project(): ?string
    {
        return $this->project;
    }

    public function rebuildsEverything(): bool
    {
        return null === $this->project;
    }
}
