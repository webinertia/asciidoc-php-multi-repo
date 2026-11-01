<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Source;

use Webware\AsciidocPhpMultiRepo\Config\SiteConfig;

use function array_keys;
use function count;
use function implode;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strpos;
use function substr;
use function usort;

/**
 * Selects the documentation versions of one repository.
 *
 * A subdirectory of the documentation directory whose name matches `source.versionPattern` is a
 * version. When nothing matches, the documentation directory itself is the single `latest` version.
 * When something matches, files outside every version directory are ignored and reported as a
 * warning rather than silently dropped.
 */
final class VersionDetector
{
    /**
     * The configuration holds a bare pattern such as `^v[1-9][0-9]*$`, so it is delimited here.
     */
    private static function delimit(string $pattern): string
    {
        $escaped = str_replace(
            search : '#',
            replace: '\\#',
            subject: $pattern,
        );

        return "#{$escaped}#";
    }

    private static function number(string $label): int
    {
        return (int) substr($label, str_contains($label, 'v') ? 1 : 0);
    }

    public function detect(FetchedRepository $repository, SiteConfig $config): VersionSelection
    {
        $directory = $repository->documentationDirectory();
        $prefix    = "{$directory}/";
        $pattern   = self::delimit($config->source()->versionPattern());

        $segments = [];
        $ignored  = [];

        foreach ($repository->files() as $file) {
            if (! str_starts_with($file, $prefix)) {
                continue;
            }

            $within = substr($file, strlen($prefix));
            $slash  = strpos(
                haystack: $within,
                needle  : '/',
            );

            if (false === $slash) {
                $ignored[] = $file;
                continue;
            }

            $segment = substr(
                string: $within,
                offset: 0,
                length: $slash,
            );

            if (1 === preg_match($pattern, $segment)) {
                $segments[$segment] = true;
                continue;
            }

            $ignored[] = $file;
        }

        if ([] === $segments) {
            return new VersionSelection([new DocVersion(DocVersion::LATEST, $directory, 0)]);
        }

        /** @var list<string> $labels */
        $labels = array_keys($segments);
        usort($labels, static fn(string $left, string $right): int => self::number($left) <=> self::number($right));

        $versions = [];

        foreach ($labels as $label) {
            $versions[] = new DocVersion($label, "{$directory}/{$label}", self::number($label));
        }

        $warnings = [];

        if ([] !== $ignored) {
            $warnings[] = sprintf(
                '%s: %d path(s) outside a version directory were ignored: %s',
                $repository->repositoryName(),
                count($ignored),
                implode(', ', $ignored),
            );
        }

        return new VersionSelection($versions, $warnings);
    }
}
