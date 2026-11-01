<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Support;

use function ctype_xdigit;
use function hexdec;
use function mb_chr;
use function strlen;
use function strpos;
use function strtr;
use function substr;

/**
 * A minimal structural scanner used only to locate the first problem in a JSON document.
 *
 * The real parsing is done by json_decode. This scanner walks the document with a recursive
 * descent that checks structure only, so that a decode failure can be reported with a byte offset
 * and a JSON pointer instead of the generic message json_decode provides.
 */
final class JsonScanner
{
    private readonly int $length;

    private int $offset = 0;

    private ?int $errorOffset = null;

    private ?string $errorPointer = null;

    public function __construct(
        private readonly string $json,
    ) {
        $this->length = strlen($json);
    }

    public function scan(): JsonSyntaxLocation
    {
        $this->skipWhitespace();

        if ($this->offset >= $this->length) {
            return new JsonSyntaxLocation(0, '');
        }

        $this->scanValue('');

        if (null !== $this->errorOffset) {
            return new JsonSyntaxLocation($this->errorOffset, $this->errorPointer ?? '');
        }

        $this->skipWhitespace();

        if ($this->offset < $this->length) {
            return new JsonSyntaxLocation($this->offset, '');
        }

        return new JsonSyntaxLocation(0, '');
    }

    private function childPointer(string $pointer, string $key): string
    {
        return $pointer . '/' . strtr($key, ['~' => '~0', '/' => '~1']);
    }

    private function fail(string $pointer, int $offset): void
    {
        if (null !== $this->errorOffset) {
            return;
        }

        $this->errorOffset  = $offset;
        $this->errorPointer = $pointer;
    }

    private function peek(): string
    {
        return $this->json[$this->offset] ?? '';
    }

    private function scanArray(string $pointer): void
    {
        $this->offset++;
        $this->skipWhitespace();

        if ($this->peek() === ']') {
            $this->offset++;

            return;
        }

        $index = 0;

        while (true) {
            $this->skipWhitespace();
            $this->scanValue("{$pointer}/{$index}");

            if (null !== $this->errorOffset) {
                return;
            }

            $index++;
            $this->skipWhitespace();
            $next = $this->peek();

            if (',' === $next) {
                $this->offset++;

                continue;
            }

            if (']' === $next) {
                $this->offset++;

                return;
            }

            $this->fail($pointer, $this->offset);

            return;
        }
    }

    private function scanLiteral(string $literal, string $pointer): void
    {
        if (substr($this->json, $this->offset, strlen($literal)) !== $literal) {
            $this->fail($pointer, $this->offset);

            return;
        }

        $this->offset += strlen($literal);
    }

    private function scanNumber(string $pointer): void
    {
        $start = $this->offset;

        while ($this->offset < $this->length) {
            if (strpos('-+.0123456789eE', $this->json[$this->offset]) === false) {
                break;
            }

            $this->offset++;
        }

        if ($this->offset === $start) {
            $this->fail($pointer, $start);
        }
    }

    private function scanObject(string $pointer): void
    {
        $this->offset++;
        $this->skipWhitespace();

        if ($this->peek() === '}') {
            $this->offset++;

            return;
        }

        while (true) {
            $this->skipWhitespace();

            if ($this->peek() !== '"') {
                $this->fail($pointer, $this->offset);

                return;
            }

            $key = $this->scanString($pointer);
            if (null !== $this->errorOffset) {
                return;
            }

            $this->skipWhitespace();

            if ($this->peek() !== ':') {
                $this->fail($pointer, $this->offset);

                return;
            }

            $this->offset++;
            $this->skipWhitespace();
            $this->scanValue($this->childPointer($pointer, $key));

            if (null !== $this->errorOffset) {
                return;
            }

            $this->skipWhitespace();
            $next = $this->peek();

            if (',' === $next) {
                $this->offset++;

                continue;
            }

            if ('}' === $next) {
                $this->offset++;

                return;
            }

            $this->fail($pointer, $this->offset);

            return;
        }
    }

    private function scanString(string $pointer): string
    {
        $start = $this->offset;
        $this->offset++;
        $value = '';

        while ($this->offset < $this->length) {
            $character = $this->json[$this->offset];

            if ('"' === $character) {
                $this->offset++;

                return $value;
            }

            if ('\\' !== $character) {
                $value .= $character;
                $this->offset++;

                continue;
            }

            $escape = $this->json[$this->offset + 1] ?? '';

            if ('' === $escape) {
                break;
            }

            if ('u' === $escape) {
                $hex = substr($this->json, $this->offset + 2, 4);
                if (strlen($hex) !== 4 || ! ctype_xdigit($hex)) {
                    $this->fail($pointer, $this->offset);

                    return '';
                }

                $decoded      = mb_chr((int) hexdec($hex));
                $value        .= false === $decoded ? '' : $decoded;
                $this->offset += 6;

                continue;
            }

            $simple = strtr($escape, [
                '"'  => '"',
                '\\' => '\\',
                '/'  => '/',
                'b'  => "\x08",
                'f'  => "\x0c",
                'n'  => "\n",
                'r'  => "\r",
                't'  => "\t",
            ]);
            if ($simple === $escape) {
                $this->fail($pointer, $this->offset);

                return '';
            }

            $value        .= $simple;
            $this->offset += 2;
        }

        $this->fail($pointer, $start);

        return '';
    }

    private function scanValue(string $pointer): void
    {
        if (null !== $this->errorOffset) {
            return;
        }

        if ($this->offset >= $this->length) {
            $this->fail($pointer, $this->offset);

            return;
        }

        $character = $this->json[$this->offset];

        if ('{' === $character) {
            $this->scanObject($pointer);

            return;
        }

        if ('[' === $character) {
            $this->scanArray($pointer);

            return;
        }

        if ('"' === $character) {
            $this->scanString($pointer);

            return;
        }

        if ('t' === $character) {
            $this->scanLiteral('true', $pointer);

            return;
        }

        if ('f' === $character) {
            $this->scanLiteral('false', $pointer);

            return;
        }

        if ('n' === $character) {
            $this->scanLiteral('null', $pointer);

            return;
        }

        $this->scanNumber($pointer);
    }

    private function skipWhitespace(): void
    {
        while ($this->offset < $this->length) {
            $character = $this->json[$this->offset];
            if (' ' !== $character && "\t" !== $character && "\n" !== $character && "\r" !== $character) {
                return;
            }

            $this->offset++;
        }
    }
}
