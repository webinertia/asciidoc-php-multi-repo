<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Discovery;

use RuntimeException;

/**
 * Raised when a repository name cannot become a URL slug, or when two projects claim the same
 * slug. Both are configuration problems, so they map to exit code 2.
 */
final class SlugException extends RuntimeException {}
