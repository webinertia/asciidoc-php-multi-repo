<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Http;

/**
 * The only thing discovery needs from the network, so a test can replace it outright.
 *
 * @internal
 */
interface HttpTransportInterface
{
    /**
     * @param array<string, string> $headers request headers keyed by name
     *
     * @throws HttpException when the request cannot be completed
     */
    public function get(string $url, array $headers = []): HttpResponse;
}
