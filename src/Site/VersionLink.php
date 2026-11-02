<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Site;

/**
 * One entry of the version selector.
 *
 * `latest` marks the version the project directory redirects to, which is the highest numeric
 * version, and `latest` itself only when the project publishes no numeric version.
 *
 * @api
 */
final readonly class VersionLink
{
    public function __construct(
        private string $label,
        private string $url,
        private bool $latest,
    ) {}

    public function isLatest(): bool
    {
        return $this->latest;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function url(): string
    {
        return $this->url;
    }
}
