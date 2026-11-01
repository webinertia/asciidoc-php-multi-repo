<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Support;

use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\StreamFactory;
use Psr\Http\Message\ResponseInterface;

/**
 * Builds a PSR-7 response through the same PSR-17 factories the production transport uses, so a
 * fake answers with exactly the message shape the caller expects.
 */
final class StubHttpResponse
{
    /**
     * @param array<string, string> $headers
     */
    public static function create(int $status = 200, string $body = '', array $headers = []): ResponseInterface
    {
        $response = new ResponseFactory()
            ->createResponse($status)
            ->withBody(new StreamFactory()->createStream($body));

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
