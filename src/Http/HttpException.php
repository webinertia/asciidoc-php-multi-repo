<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Http;

use RuntimeException;
use Throwable;

/**
 * Raised when the transport itself fails, as opposed to the server answering with an error status.
 */
final class HttpException extends RuntimeException
{
    public function __construct(
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
