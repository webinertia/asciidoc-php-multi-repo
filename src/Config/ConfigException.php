<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Config;

use RuntimeException;
use Throwable;

/**
 * Raised for any configuration problem that maps to exit code 2.
 */
final class ConfigException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $pointer = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * The JSON pointer of the offending value, or an empty string when the problem is not tied to a
     * single value.
     */
    public function pointer(): string
    {
        return $this->pointer;
    }
}
