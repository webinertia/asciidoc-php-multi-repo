<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Discovery;

use function array_key_exists;
use function explode;
use function preg_match;

/**
 * Reads the `Link` response header and returns the `rel="next"` target.
 *
 * GitHub paginates every list endpoint this way, so the parser is kept separate from the transport
 * and from the endpoint logic.
 */
final class GitHubLinkHeader
{
    public static function next(?string $header): ?string
    {
        if (null === $header) {
            return null;
        }

        foreach (explode(
            separator: ',',
            string   : $header,
        ) as $link) {
            $matches = [];

            if (
                1 !== preg_match(
                    pattern: '/<([^>]+)>\s*;\s*rel="?next"?/',
                    subject: $link,
                    matches: $matches,
                )
            ) {
                continue;
            }

            if (array_key_exists(1, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }
}
