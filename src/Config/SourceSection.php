<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Config;

/**
 * The `source` section: where documentation lives inside a repository.
 */
final readonly class SourceSection
{
    /**
     * @param list<string> $entryCandidates
     * @param list<string> $extensions
     * @param list<string> $excludePatterns
     */
    public function __construct(
        private string $directory,
        private bool $defaultBranchOnly,
        private array $entryCandidates,
        private array $extensions,
        private string $versionPattern,
        private array $excludePatterns,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::string($data, 'directory', 'docs'),
            Values::boolean($data, 'defaultBranchOnly', true),
            Values::stringList($data, 'entryCandidates', ['index.adoc', 'README.adoc']),
            Values::stringList($data, 'extensions', ['.adoc', '.asciidoc']),
            Values::string($data, 'versionPattern', '^v[1-9][0-9]*$'),
            Values::stringList($data, 'excludePatterns', ['**/_*.adoc', '**/_*.asciidoc']),
        );
    }

    public function defaultBranchOnly(): bool
    {
        return $this->defaultBranchOnly;
    }

    public function directory(): string
    {
        return $this->directory;
    }

    /**
     * @return list<string>
     */
    public function entryCandidates(): array
    {
        return $this->entryCandidates;
    }

    /**
     * @return list<string>
     */
    public function excludePatterns(): array
    {
        return $this->excludePatterns;
    }

    /**
     * @return list<string>
     */
    public function extensions(): array
    {
        return $this->extensions;
    }

    public function versionPattern(): string
    {
        return $this->versionPattern;
    }
}
