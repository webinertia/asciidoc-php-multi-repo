<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Source;

use function strrpos;
use function strtolower;
use function substr;

/**
 * The markup format of a documentation file.
 *
 * Detection is by extension only. Nothing is parsed, so a Markdown file is recognised and rejected
 * before a converter ever sees it.
 */
enum SourceFormat: string
{
    case AsciiDoc = 'asciidoc';
    case Markdown = 'markdown';
    case Unknown  = 'unknown';

    /**
     * The lowercase extension including the leading dot, or an empty string when there is none.
     */
    public static function extension(string $path): string
    {
        $dot = strrpos(
            haystack: $path,
            needle  : '.',
        );

        return false === $dot ? '' : strtolower(substr($path, $dot));
    }

    public static function fromPath(string $path): self
    {
        return match (self::extension($path)) {
            '.adoc', '.asciidoc'         => self::AsciiDoc,
            '.md', '.markdown', '.mdown' => self::Markdown,
            default                      => self::Unknown,
        };
    }

    public function isAsciiDoc(): bool
    {
        return self::AsciiDoc === $this;
    }
}
