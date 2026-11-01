<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Source;

use Webware\AsciidocPhpMultiRepo\Report\ReasonCode;
use Webware\AsciidocPhpMultiRepo\Support\Filesystem;

use function mb_check_encoding;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function substr;

/**
 * The single place a documentation file is turned into text.
 *
 * Reading, format detection, encoding validation, and line-ending normalisation all happen here so
 * that every consumer sees the same bytes and the same failures.
 */
final class SourcePreparer
{
    public function __construct(
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {}

    /**
     * Converts CRLF and lone CR to LF and removes a leading byte order mark.
     */
    private static function normalise(string $contents): string
    {
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = substr(
                string: $contents,
                offset: 3,
            );
        }

        return str_replace(
            search : ["\r\n", "\r"],
            replace: "\n",
            subject: $contents,
        );
    }

    /**
     * @throws SourceException when the file is not AsciiDoc or is not valid UTF-8
     */
    public function prepare(DocPage $page): PreparedSource
    {
        $format = SourceFormat::fromPath($page->relativePath());

        if (! $format->isAsciiDoc()) {
            throw new SourceException(new SourceError(
                ReasonCode::NoAsciidocSources,
                sprintf(
                    '%s is %s, not AsciiDoc, so it cannot be rendered.',
                    $page->relativePath(),
                    $format->value,
                ),
                $page->version(),
                $page->relativePath(),
                'Convert the page to AsciiDoc, or remove it from the documentation directory.',
            ));
        }

        $contents = $this->filesystem->read($page->absolutePath());

        if (! mb_check_encoding(
            value   : $contents,
            encoding: 'UTF-8',
        )) {
            throw new SourceException(new SourceError(
                ReasonCode::InvalidEncoding,
                sprintf('%s is not valid UTF-8.', $page->relativePath()),
                $page->version(),
                $page->relativePath(),
                'Re-encode the file as UTF-8 without a byte order mark.',
            ));
        }

        $text = self::normalise($contents);

        return new PreparedSource(
            $page,
            $format,
            $text,
            $this->filesystem->hashContents($text),
        );
    }
}
