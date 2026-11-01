<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Support;

use function is_array;
use function json_decode;
use function json_encode;
use function sprintf;

use JsonException;
use stdClass;

/**
 * JSON encoding and decoding with fixed, deterministic formatting.
 *
 * Encoding always produces the same bytes for the same value: fixed flags, unescaped slashes and
 * unicode, preserved zero fractions, and a single trailing newline. Decoding failures are reported
 * as a JsonDecodeException that names the source, the JSON pointer, and the byte offset.
 */
final class Json
{
    /**
     * Four-space indentation is what JSON_PRETTY_PRINT emits; it is used for human-readable reports.
     */
    public const int REPORT_FLAGS = JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_PRESERVE_ZERO_FRACTION
        | JSON_THROW_ON_ERROR;

    /**
     * Compact output is used for machine-read indexes.
     */
    public const int COMPACT_FLAGS = JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_PRESERVE_ZERO_FRACTION
        | JSON_THROW_ON_ERROR;

    private const int MAX_DEPTH = 512;

    public function encode(mixed $value, int $flags = self::REPORT_FLAGS): string
    {
        return json_encode($value, $flags) . "\n";
    }

    public function encodeCompact(mixed $value): string
    {
        return $this->encode($value, self::COMPACT_FLAGS);
    }

    /**
     * Decodes a document into associative arrays. An empty object and an empty array both decode to
     * an empty array, which callers must tolerate.
     */
    public function decode(string $json, string $source = 'JSON document'): mixed
    {
        try {
            return json_decode($json, true, self::MAX_DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw $this->mapFailure($json, $source, $exception);
        }
    }

    /**
     * Decodes a document that must be a JSON object.
     *
     * @return array<string, mixed>
     */
    public function decodeObject(string $json, string $source = 'JSON document'): array
    {
        $decoded = $this->decode($json, $source);

        // Re-decoding with objects preserved distinguishes a JSON object from a JSON array, which
        // the associative form cannot do for empty documents.
        if (! json_decode($json, false, self::MAX_DEPTH, JSON_THROW_ON_ERROR) instanceof stdClass) {
            throw new JsonDecodeException(
                sprintf('%s must contain a JSON object.', $source),
                $source,
                '',
                0,
            );
        }

        /** @var array<string, mixed> $decoded */
        return is_array($decoded) ? $decoded : [];
    }

    private function mapFailure(string $json, string $source, JsonException $exception): JsonDecodeException
    {
        $location = (new JsonScanner($json))->scan();

        return new JsonDecodeException(
            sprintf(
                'Invalid JSON in %s at byte offset %d (JSON pointer "%s"): %s',
                $source,
                $location->offset(),
                $location->pointer(),
                $exception->getMessage(),
            ),
            $source,
            $location->pointer(),
            $location->offset(),
        );
    }
}
