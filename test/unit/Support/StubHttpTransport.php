<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Support;

use Webware\AsciidocPhp\MultiRepo\Http\HttpException;
use Webware\AsciidocPhp\MultiRepo\Http\HttpResponse;
use Webware\AsciidocPhp\MultiRepo\Http\HttpTransportInterface;

use function str_contains;

/**
 * A transport that answers from a table keyed by a URL fragment and records every request.
 *
 * A fragment that matches nothing raises, so a test that reaches an unexpected endpoint fails loudly
 * instead of silently returning an empty page.
 */
final class StubHttpTransport implements HttpTransportInterface
{
    /** @var list<array{url: string, headers: array<string, string>}> */
    public array $requests = [];

    /**
     * @param array<string, HttpResponse> $responses keyed by a URL fragment, checked in order
     */
    public function __construct(
        private readonly array $responses,
    ) {}

    public function get(string $url, array $headers = []): HttpResponse
    {
        $this->requests[] = ['url' => $url, 'headers' => $headers];

        foreach ($this->responses as $fragment => $response) {
            if (str_contains($url, $fragment)) {
                return $response;
            }
        }

        throw new HttpException("No stub response is registered for {$url}");
    }
}
