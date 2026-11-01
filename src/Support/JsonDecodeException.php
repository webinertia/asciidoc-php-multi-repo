<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Support;

use RuntimeException;

/**
 * Raised when a JSON document cannot be decoded. Carries the JSON pointer and byte offset of the
 * first problem so that configuration errors point at the offending value.
 */
final class JsonDecodeException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $source,
        private readonly string $pointer,
        private readonly int $offset,
    ) {
        parent::__construct($message);
    }

    public function offset(): int
    {
        return $this->offset;
    }

    public function pointer(): string
    {
        return $this->pointer;
    }

    public function source(): string
    {
        return $this->source;
    }
}
