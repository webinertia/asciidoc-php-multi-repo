<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Config;

use RuntimeException;

/**
 * Raised when a schema itself is malformed: an unsupported keyword, a broken reference, or an
 * invalid regular expression. A malformed schema is a programming error, never a user error.
 */
final class SchemaException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $pointer = '',
    ) {
        parent::__construct($message);
    }

    public function pointer(): string
    {
        return $this->pointer;
    }
}
