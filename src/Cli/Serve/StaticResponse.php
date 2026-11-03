<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Cli\Serve;

use function sprintf;
use function strlen;

/**
 * One HTTP/1.1 response, rendered as the exact bytes the static server writes to a socket.
 *
 * Every response closes the connection, and every response is uncacheable, so a preview after a
 * rebuild always shows the new bytes rather than a browser cache entry.
 */
final readonly class StaticResponse
{
    private function __construct(
        private int $status,
        private string $reasonPhrase,
        private string $contentType,
        private string $body,
    ) {}

    public static function badRequest(string $message): self
    {
        return new self(400, 'Bad Request', 'text/plain; charset=utf-8', "{$message}\n");
    }

    public static function methodNotAllowed(): self
    {
        return new self(405, 'Method Not Allowed', 'text/plain; charset=utf-8', "Method Not Allowed\n");
    }

    public static function notFound(string $body): self
    {
        return new self(404, 'Not Found', 'text/html; charset=utf-8', $body);
    }

    public static function ok(string $contentType, string $body): self
    {
        return new self(200, 'OK', $contentType, $body);
    }

    public function body(): string
    {
        return $this->body;
    }

    public function contentType(): string
    {
        return $this->contentType;
    }

    /**
     * The status line and the headers, including the body length, terminated by a blank line.
     */
    public function headerBlock(): string
    {
        return sprintf(
            "HTTP/1.1 %d %s\r\nContent-Type: %s\r\nContent-Length: %d\r\nCache-Control: no-store\r\nConnection: close\r\n\r\n",
            $this->status,
            $this->reasonPhrase,
            $this->contentType,
            strlen($this->body),
        );
    }

    public function reasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    public function status(): int
    {
        return $this->status;
    }
}
