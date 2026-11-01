<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Support;

/**
 * The first structural problem found while scanning a JSON document.
 */
final readonly class JsonSyntaxLocation
{
    public function __construct(
        private int $offset,
        private string $pointer,
    ) {}

    public function offset(): int
    {
        return $this->offset;
    }

    public function pointer(): string
    {
        return $this->pointer;
    }
}
