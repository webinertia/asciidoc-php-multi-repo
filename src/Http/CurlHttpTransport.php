<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Http;

use CurlHandle;
use Override;

use function curl_error;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt_array;
use function is_string;
use function round;
use function sprintf;

use const CURLINFO_RESPONSE_CODE;
use const CURLOPT_CONNECTTIMEOUT_MS;
use const CURLOPT_FOLLOWLOCATION;
use const CURLOPT_HEADERFUNCTION;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_TIMEOUT_MS;

/**
 * The production transport: ext-curl, no redirects, a bounded timeout, and no credential in any
 * message it can raise.
 */
final class CurlHttpTransport implements HttpTransportInterface
{
    private const string USER_AGENT = 'webware-asciidoc-php-multi-repo';

    public function __construct(
        private readonly float $timeoutSeconds = 30.0,
    ) {}

    #[Override]
    public function get(string $url, array $headers = []): HttpResponse
    {
        $handle = curl_init($url);

        if (! $handle instanceof CurlHandle) {
            throw new HttpException(sprintf('Unable to initialise a cURL handle for %s.', $url));
        }

        $collector      = new HeaderCollector();
        $requestHeaders = [sprintf('User-Agent: %s', self::USER_AGENT)];

        foreach ($headers as $name => $value) {
            $requestHeaders[] = sprintf('%s: %s', $name, $value);
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER    => true,
            CURLOPT_FOLLOWLOCATION    => false,
            CURLOPT_CONNECTTIMEOUT_MS => $this->milliseconds(),
            CURLOPT_TIMEOUT_MS        => $this->milliseconds(),
            CURLOPT_HTTPHEADER        => $requestHeaders,
            CURLOPT_HEADERFUNCTION    => $collector,
        ]);

        $body = curl_exec($handle);

        if (! is_string($body)) {
            $error = curl_error($handle);

            throw new HttpException(sprintf(
                'HTTP request to %s failed: %s',
                $url,
                '' === $error ? 'unknown cURL error' : $error,
            ));
        }

        return new HttpResponse(
            (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE),
            $body,
            $collector->headers(),
        );
    }

    private function milliseconds(): int
    {
        return (int) round($this->timeoutSeconds * 1000.0);
    }
}
