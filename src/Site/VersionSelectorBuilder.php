<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Site;

use Webware\AsciidocPhp\MultiRepo\Render\PageShell;

use function implode;
use function sprintf;

/**
 * Renders the version list, newest first, marking the current version and the version the project
 * directory redirects to.
 *
 * The list is plain links, so it works with scripting disabled; `nav.js` only adds a disclosure
 * wrapper around it. Marking the latest version is separate from marking the current one, because
 * the two coincide only when the reader is already on the latest version.
 *
 * @api
 */
final readonly class VersionSelectorBuilder
{
    public function __construct(
        private PageShell $shell = new PageShell(),
    ) {}

    /**
     * @param list<VersionLink> $versions newest first
     */
    public function build(array $versions, string $currentLabel): string
    {
        if ([] === $versions) {
            return '';
        }

        $items = [];
        foreach ($versions as $version) {
            $items[] = sprintf(
                '<li class="version-item%s"><a href="%s"%s>%s%s</a></li>',
                $version->isLatest() ? ' version-latest' : '',
                $this->shell->escape($version->url()),
                $version->label() === $currentLabel ? ' aria-current="page"' : '',
                $this->shell->escape($version->label()),
                $version->isLatest() ? ' <span class="version-flag">(latest)</span>' : '',
            );
        }

        return sprintf(
            "<nav class=\"version-selector\" aria-label=\"Versions\" data-version-selector>\n"
                . "<p class=\"nav-label\">Versions</p>\n<ul>\n%s\n</ul>\n</nav>\n",
            implode("\n", $items),
        );
    }
}
