<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Source;

/**
 * One documentation version of one repository.
 *
 * `directory` is relative to the repository root and never ends with a slash. An unversioned
 * repository yields a single version labelled `latest` whose directory is the documentation
 * directory itself.
 */
final readonly class DocVersion
{
    public const string LATEST = 'latest';

    public function __construct(
        private string $label,
        private string $directory,
        private int $number = 0,
    ) {}

    public function directory(): string
    {
        return $this->directory;
    }

    public function isLatest(): bool
    {
        return self::LATEST === $this->label;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function number(): int
    {
        return $this->number;
    }

    /**
     * The prefix every path inside this version starts with.
     */
    public function prefix(): string
    {
        return "{$this->directory}/";
    }
}
