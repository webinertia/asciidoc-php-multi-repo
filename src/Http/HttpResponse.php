<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Http;

use function strtolower;

/**
 * A completed HTTP exchange. Header names are stored lowercase so lookups are case insensitive.
 */
final readonly class HttpResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private int $status,
        private string $body,
        private array $headers = [],
    ) {}

    public function body(): string
    {
        return $this->body;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status <= 299;
    }
}
