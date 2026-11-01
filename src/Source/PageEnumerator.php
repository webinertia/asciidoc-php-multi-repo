<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Source;

use Webware\AsciidocPhp\MultiRepo\Config\SiteConfig;
use Webware\AsciidocPhp\MultiRepo\Report\ReasonCode;
use Webware\AsciidocPhp\MultiRepo\Support\Path;

use function implode;
use function in_array;
use function preg_match;
use function preg_quote;
use function sprintf;
use function str_starts_with;
use function strlen;
use function strrpos;
use function strtr;
use function substr;

/**
 * Lists the renderable pages of one version.
 *
 * Only configured extensions survive, underscore-prefixed files and configured exclude patterns are
 * dropped, and nested directories keep their structure. The entry page is the first configured
 * candidate that exists; a version without one is a `missing-entry` failure that names the
 * candidates rather than a silent omission.
 */
final class PageEnumerator
{
    private static function isPartial(string $within): bool
    {
        $slash = strrpos(
            haystack: $within,
            needle  : '/',
        );
        $baseName = false === $slash ? $within : substr($within, $slash + 1);

        return str_starts_with($baseName, '_');
    }

    /**
     * @param list<string> $patterns
     */
    private static function matchesAny(array $patterns, string $within): bool
    {
        foreach ($patterns as $pattern) {
            if (1 === preg_match(self::regex($pattern), $within)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Translates a glob with `**`, `*`, and `?` into an anchored pattern. `**` crosses directory
     * separators; `*` and `?` do not.
     */
    private static function regex(string $pattern): string
    {
        $quoted = strtr(preg_quote(
            str      : $pattern,
            delimiter: '#',
        ), [
            '\*\*/' => '(?:.*/)?',
            '\*\*'  => '.*',
            '\*'    => '[^/]*',
            '\?'    => '[^/]',
        ]);

        return "#^{$quoted}$#";
    }

    /**
     * @throws SourceException with `no-asciidoc-sources` or `missing-entry`
     */
    public function enumerate(
        FetchedRepository $repository,
        DocVersion $version,
        SiteConfig $config,
    ): PageSet {
        $prefix     = $version->prefix();
        $extensions = $config->source()->extensions();
        $patterns   = $config->source()->excludePatterns();

        $pages = [];

        foreach ($repository->files() as $file) {
            if (! str_starts_with($file, $prefix)) {
                continue;
            }

            $within = substr($file, strlen($prefix));

            if (! in_array(SourceFormat::extension($within), $extensions, strict: true)) {
                continue;
            }

            if (self::isPartial($within) || self::matchesAny($patterns, $within)) {
                continue;
            }

            $pages[] = new DocPage($version->label(), $file, Path::join($repository->rootPath(), $file));
        }

        if ([] === $pages) {
            throw new SourceException(new SourceError(
                ReasonCode::NoAsciidocSources,
                sprintf(
                    '%s: %s contains no AsciiDoc page with a configured extension (%s).',
                    $repository->repositoryName(),
                    $version->directory(),
                    implode(', ', $extensions),
                ),
                $version->label(),
                $version->directory(),
                'Add an AsciiDoc page, or correct source.extensions.',
            ));
        }

        $entry = $this->entry($pages, $prefix, $config);

        if (! $entry instanceof DocPage) {
            throw new SourceException(new SourceError(
                ReasonCode::MissingEntry,
                sprintf(
                    '%s: %s has no entry page. Looked for %s.',
                    $repository->repositoryName(),
                    $version->directory(),
                    implode(', ', $config->source()->entryCandidates()),
                ),
                $version->label(),
                $version->directory(),
                'Add one of the entry candidates at the root of the version directory.',
            ));
        }

        return new PageSet($version, $entry, $pages);
    }

    /**
     * @param list<DocPage> $pages
     */
    private function entry(array $pages, string $prefix, SiteConfig $config): ?DocPage
    {
        foreach ($config->source()->entryCandidates() as $candidate) {
            $wanted = $prefix . $candidate;

            foreach ($pages as $page) {
                if ($page->relativePath() === $wanted) {
                    return $page;
                }
            }
        }

        return null;
    }
}
