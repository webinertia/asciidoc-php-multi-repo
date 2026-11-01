<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Http;

use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;
use Throwable;

/**
 * Raised when the transport itself fails, as opposed to the server answering with an error status.
 *
 * PSR-18 requires `sendRequest()` to signal a transport failure by throwing a
 * `ClientExceptionInterface` rather than by returning an error response, so the transport's own
 * exception is one.
 */
final class HttpException extends RuntimeException implements ClientExceptionInterface
{
    public function __construct(
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
