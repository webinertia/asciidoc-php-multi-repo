<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Theme;

use function hash;
use function sprintf;
use function substr;

/**
 * Produces content-addressed asset names.
 *
 * The hash is taken over the exact bytes the build writes, so a one byte change to a source asset
 * changes its published name and no cache can serve the stale copy.
 *
 * @api
 */
final readonly class AssetHasher
{
    public const int DEFAULT_LENGTH = 6;

    public function __construct(
        private int $length = self::DEFAULT_LENGTH,
    ) {}

    /**
     * The short hash of an asset's contents.
     */
    public function hash(string $contents): string
    {
        return substr(hash('sha256', $contents), 0, $this->length);
    }

    /**
     * The published name, for example `site.3f9c1a.css`.
     */
    public function hashedName(string $baseName, string $extension, string $contents): string
    {
        return sprintf('%s.%s.%s', $baseName, $this->hash($contents), $extension);
    }
}
