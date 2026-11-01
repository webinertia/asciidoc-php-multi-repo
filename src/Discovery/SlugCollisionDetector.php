<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Discovery;

use function sprintf;

/**
 * Rejects two discovered repositories that would publish under the same URL slug.
 *
 * The check runs on the discovered set before any repository is fetched, so a collision costs no
 * network traffic and no cache churn.
 */
final class SlugCollisionDetector
{
    /**
     * @param list<DiscoveredRepository> $repositories
     *
     * @throws SlugException naming both repositories that claim the slug
     */
    public function detect(array $repositories): void
    {
        /** @var array<string, string> $owners */
        $owners = [];

        foreach ($repositories as $repository) {
            $slug  = $repository->slug();
            $owner = $owners[$slug] ?? null;

            if (null !== $owner) {
                throw new SlugException(sprintf(
                    'Repositories "%s" and "%s" both resolve to the slug "%s". Set projects.%s.slug to a unique value.',
                    $owner,
                    $repository->name(),
                    $slug,
                    $repository->name(),
                ));
            }

            $owners[$slug] = $repository->name();
        }
    }
}
