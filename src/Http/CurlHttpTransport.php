<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Http;

use CurlHandle;
use Override;

use function curl_error;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt_array;
use function is_string;
use function preg_match;
use function preg_replace;
use function round;
use function sprintf;
use function strlen;
use function strtr;
use function substr;

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

    /**
     * RFC 7230 `tchar`, the only characters a header field name may contain.
     */
    private const string HEADER_NAME_PATTERN = '/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/';

    /**
     * A CR, an LF, or a NUL ends the header line, so any of them lets a value forge a second one.
     */
    private const string HEADER_VALUE_PATTERN = '/[\r\n\x00]/';

    /**
     * Control characters in a URL reach libcurl on the same untrusted path as a header.
     */
    private const string URL_CONTROL_PATTERN = '/[\x00-\x1F\x7F]/';

    private const int URL_DESCRIPTION_LIMIT = 64;

    public function __construct(
        private readonly float $timeoutSeconds = 30.0,
    ) {}

    #[Override]
    public function get(string $url, array $headers = []): HttpResponse
    {
        $this->assertUrlIsSendable($url);

        $requestHeaders = $this->requestHeaders($headers);

        $handle = curl_init($url);

        if (! $handle instanceof CurlHandle) {
            throw new HttpException(sprintf('Unable to initialise a cURL handle for %s.', $url));
        }

        $collector = new HeaderCollector();

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

    /**
     * The message names the header and never its value, because the value may be the credential.
     *
     * @throws HttpException when the header would forge a line, or carries a CR or an LF
     */
    private function assertHeaderIsSendable(string $name, string $value): void
    {
        if (1 !== preg_match(self::HEADER_NAME_PATTERN, $name)) {
            throw new HttpException(sprintf(
                'Refusing to send a request: "%s" is not a valid HTTP header name.',
                $this->describe($name),
            ));
        }

        if (1 === preg_match(self::HEADER_VALUE_PATTERN, $value)) {
            throw new HttpException(sprintf(
                'Refusing to send a request: the value of the "%s" header contains a line break.',
                $name,
            ));
        }
    }

    /**
     * @throws HttpException when the URL carries a control character
     */
    private function assertUrlIsSendable(string $url): void
    {
        if (1 !== preg_match(self::URL_CONTROL_PATTERN, $url)) {
            return;
        }

        throw new HttpException(sprintf(
            'Refusing to send a request: the URL "%s" contains a control character.',
            $this->describe($url),
        ));
    }

    /**
     * Control characters become their escaped form so a rejected name cannot inject a log line.
     */
    private function describe(string $text): string
    {
        $escaped = strtr($text, [
            "\0" => '\0',
            "\t" => '\t',
            "\n" => '\n',
            "\r" => '\r',
        ]);

        $escaped = (string) preg_replace(
            self::URL_CONTROL_PATTERN,
            replacement: '',
            subject: $escaped,
        );

        if (strlen($escaped) <= self::URL_DESCRIPTION_LIMIT) {
            return $escaped;
        }

        return substr($escaped, offset: 0, length: self::URL_DESCRIPTION_LIMIT) . '...';
    }

    private function milliseconds(): int
    {
        return (int) round($this->timeoutSeconds * 1000.0);
    }

    /**
     * Rejects anything that would forge a header line. libcurl does not reject a CR or an LF in a
     * header value; it sends it, so the second line arrives as a header of its own.
     *
     * @param array<string, string> $headers
     *
     * @return list<string>
     *
     * @throws HttpException when a header would forge a line, or carries a CR or an LF
     */
    private function requestHeaders(array $headers): array
    {
        $requestHeaders = [sprintf('User-Agent: %s', self::USER_AGENT)];

        foreach ($headers as $name => $value) {
            $this->assertHeaderIsSendable($name, $value);

            $requestHeaders[] = sprintf('%s: %s', $name, $value);
        }

        return $requestHeaders;
    }
}
