<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Report;

/**
 * The closed set of failure reasons a report may carry.
 *
 * The values are the `reasonCode` enum of `contracts/report.schema.json`. Adding a case here
 * without adding it to the published schema, or the reverse, breaks the contract test.
 */
enum ReasonCode: string
{
    case Archived           = 'archived';
    case ConfigError        = 'config-error';
    case DiscoveryFailed    = 'discovery-failed';
    case FetchFailed        = 'fetch-failed';
    case IncludeOutsideRoot = 'include-outside-root';
    case InvalidEncoding    = 'invalid-encoding';
    case MissingEntry       = 'missing-entry';
    case NoAsciidocSources  = 'no-asciidoc-sources';
    case RenderFailed       = 'render-failed';
    case TocTargetMissing   = 'toc-target-missing';

    public function label(): string
    {
        return $this->value;
    }
}
