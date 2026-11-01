<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Source;

use RuntimeException;
use Throwable;

/**
 * Carries a reportable source problem rather than a programming fault.
 */
final class SourceException extends RuntimeException
{
    public function __construct(
        private readonly SourceError $error,
        ?Throwable $previous = null,
    ) {
        parent::__construct($error->message(), 0, $previous);
    }

    public function error(): SourceError
    {
        return $this->error;
    }
}
