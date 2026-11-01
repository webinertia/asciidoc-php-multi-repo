<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Source;

/**
 * A documentation file that has been read, validated, and normalised, together with its origin.
 */
final readonly class PreparedSource
{
    public function __construct(
        private DocPage $page,
        private SourceFormat $format,
        private string $text,
        private string $sha256,
    ) {}

    public function format(): SourceFormat
    {
        return $this->format;
    }

    public function hash(): string
    {
        return $this->sha256;
    }

    public function page(): DocPage
    {
        return $this->page;
    }

    public function relativePath(): string
    {
        return $this->page->relativePath();
    }

    /**
     * The file contents with `\n` line endings and no byte order mark.
     */
    public function text(): string
    {
        return $this->text;
    }

    public function version(): string
    {
        return $this->page->version();
    }
}
