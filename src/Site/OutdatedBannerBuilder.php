<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Site;

use Webware\AsciidocPhp\MultiRepo\Render\PageShell;

use function sprintf;

/**
 * Builds the notice a page carries when it is not the version the project points readers at.
 *
 * The notice links to the same page in the latest version when that page exists there, and falls
 * back to the latest version's entry page otherwise. It never renders on the latest version itself,
 * and never renders for a project that publishes only `latest`.
 *
 * @api
 */
final readonly class OutdatedBannerBuilder
{
    public function __construct(
        private PageShell $shell = new PageShell(),
    ) {}

    public function build(
        string $versionLabel,
        ?string $latestLabel,
        ?string $samePageUrl,
        ?string $entryPageUrl,
    ): string {
        if (null === $latestLabel || $latestLabel === $versionLabel) {
            return '';
        }

        $target = $samePageUrl ?? $entryPageUrl;

        if (null === $target || '' === $target) {
            return '';
        }

        return sprintf(
            "<aside class=\"outdated-banner\" aria-label=\"Version notice\">\n"
                . "<p>You are reading <strong>%s</strong>. The current version is <a href=\"%s\">%s</a>.</p>\n"
                . "</aside>\n",
            $this->shell->escape($versionLabel),
            $this->shell->escape($target),
            $this->shell->escape($latestLabel),
        );
    }
}
