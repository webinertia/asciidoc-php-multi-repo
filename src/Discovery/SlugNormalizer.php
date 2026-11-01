<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Discovery;

use function preg_replace;
use function sprintf;
use function strtolower;
use function trim;

/**
 * Turns a repository name into the URL slug that carries its documentation.
 *
 * The result is lowercase, uses single ASCII hyphens as separators, and never starts or ends with
 * one. Every character outside `[a-z0-9]` becomes a separator, so `Message_Bus`, `message.bus`, and
 * `message--bus` all resolve to `message-bus` and a rename that only changes punctuation cannot
 * silently move a URL. Case is lowercased but never split, so `MessageBus` becomes `messagebus`.
 */
final class SlugNormalizer
{
    /**
     * @throws SlugException when the name contains no character usable in a URL slug
     */
    public function normalise(string $name): string
    {
        $slug = trim(
            (string) preg_replace(
                pattern    : '/[^a-z0-9]+/',
                replacement: '-',
                subject    : strtolower($name),
            ),
            characters: '-',
        );

        if ('' === $slug) {
            throw new SlugException(
                sprintf('"%s" contains no characters usable in a URL slug; set projects.%s.slug.', $name, $name),
            );
        }

        return $slug;
    }
}
