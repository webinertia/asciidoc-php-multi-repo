<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Cli;

use Composer\InstalledVersions;

use function class_exists;
use function is_string;
use function preg_match;

/**
 * Resolves the package version for `--version` and for the machine readable report.
 *
 * A development checkout has no tag, so the Composer metadata reports a branch alias rather than a
 * release. Only a three part release number is accepted; everything else falls back to the sentinel
 * below so the report schema pattern still matches.
 */
final class Version
{
    public const string PACKAGE = 'webware/asciidoc-php-multi-repo';

    public const string FALLBACK = '0.0.0-dev';

    public static function current(): string
    {
        if (! class_exists(InstalledVersions::class)) {
            return self::FALLBACK;
        }

        $version = InstalledVersions::getPrettyVersion(self::PACKAGE);

        if (! is_string($version)) {
            return self::FALLBACK;
        }

        if (preg_match('/^v?([0-9]+\.[0-9]+\.[0-9]+)$/', $version, $matches) !== 1 || ! isset($matches[1])) {
            return self::FALLBACK;
        }

        return $matches[1];
    }
}
