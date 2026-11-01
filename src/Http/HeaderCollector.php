<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Http;

use CurlHandle;

use function str_starts_with;
use function strlen;
use function strpos;
use function strtolower;
use function substr;
use function trim;

/**
 * Accumulates response header lines from a cURL transfer.
 *
 * A new status line resets the map, so a redirect chain leaves only the final response's headers.
 * Values are kept as a list per name because PSR-7 allows a header to repeat.
 */
final class HeaderCollector
{
    /** @var array<string, list<string>> */
    private array $headers = [];

    /**
     * @return array<string, list<string>>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    private function add(string $line): void
    {
        $separator = strpos(
            haystack: $line,
            needle  : ':',
        );

        if (false === $separator) {
            return;
        }

        $name = strtolower(trim(substr(
            string: $line,
            offset: 0,
            length: $separator,
        )));

        if ('' === $name) {
            return;
        }

        $this->headers[$name][] = trim(substr($line, $separator + 1));
    }

    public function __invoke(CurlHandle $_handle, string $line): int
    {
        $trimmed = trim($line);

        if ('' === $trimmed) {
            return strlen($line);
        }

        if (str_starts_with($trimmed, 'HTTP/')) {
            $this->headers = [];

            return strlen($line);
        }

        $this->add($trimmed);

        return strlen($line);
    }
}
