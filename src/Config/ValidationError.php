<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Config;

use function sprintf;

/**
 * One validation failure, located by JSON pointer.
 */
final readonly class ValidationError
{
    public function __construct(
        private string $pointer,
        private string $message,
    ) {}

    public function describe(): string
    {
        return sprintf('%s: %s', '' === $this->pointer ? '(document)' : $this->pointer, $this->message);
    }

    public function message(): string
    {
        return $this->message;
    }

    public function pointer(): string
    {
        return $this->pointer;
    }
}
