<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Discovery;

use RuntimeException;
use Throwable;

/**
 * Raised when the remote project list cannot be read, which maps to exit code 3.
 */
final class DiscoveryException extends RuntimeException
{
    public function __construct(
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
