<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Support;

use Laminas\Diactoros\Request;
use Override;
use Psr\Http\Message\UriInterface;

/**
 * A request carrying bytes a PSR-7 implementation refuses, so the transport's own boundary check
 * is exercised instead of the message implementation rejecting first.
 *
 * Only the two accessors the transport reads are overridden; nothing else is expected to consume it.
 */
final class UnsafeRequest extends Request
{
    /**
     * @param array<string, list<string>> $headers
     */
    public function __construct(
        private readonly UriInterface $unsafeUri,
        private readonly array $unsafeHeaders,
    ) {
        parent::__construct('http://example.test/', 'GET');
    }

    #[Override]
    public function getHeaders(): array
    {
        return $this->unsafeHeaders;
    }

    #[Override]
    public function getUri(): UriInterface
    {
        return $this->unsafeUri;
    }
}
