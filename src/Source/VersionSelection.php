<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Source;

/**
 * The versions detected in one repository, plus anything worth warning about.
 *
 * `warnings` is a list of complete sentences in the order they were discovered. Nothing here is
 * printed; the caller decides where a warning goes.
 */
final readonly class VersionSelection
{
    /**
     * @param list<DocVersion> $versions ascending by version number, or a single `latest`
     * @param list<string>     $warnings
     */
    public function __construct(
        private array $versions,
        private array $warnings = [],
    ) {}

    /**
     * @return list<DocVersion>
     */
    public function versions(): array
    {
        return $this->versions;
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }
}
