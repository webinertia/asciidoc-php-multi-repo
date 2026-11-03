<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Support;

use function array_fill;
use function array_pop;
use function array_slice;
use function count;
use function explode;
use function implode;
use function ltrim;
use function preg_match;
use function rtrim;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strrpos;
use function substr;

/**
 * Separator-agnostic path helpers.
 *
 * Internally every path is handled in a canonical form that uses forward slashes, and is converted
 * to the platform separator only when a value leaves the class. Windows and POSIX inputs may be
 * mixed freely: both separators are accepted on input.
 */
final class Path
{
    private function __construct() {}

    public static function isAbsolute(string $path): bool
    {
        $canonical = self::toCanonical($path);

        return (
            str_starts_with($canonical, '/')
                || preg_match('#^[A-Za-z]:/#', $canonical) === 1
        );
    }

    public static function join(string ...$parts): string
    {
        $canonical = '';
        foreach ($parts as $part) {
            if ('' === $part) {
                continue;
            }

            $part = self::toCanonical($part);
            if ('' === $canonical) {
                $canonical = $part;
                continue;
            }

            $canonical = rtrim($canonical, characters: '/') . '/' . ltrim($part, characters: '/');
        }

        return self::toPlatform(self::normaliseCanonical($canonical));
    }

    public static function normalise(string $path): string
    {
        if ('' === $path) {
            return '';
        }

        return self::toPlatform(self::normaliseCanonical($path));
    }

    /**
     * Returns the path of $to expressed relative to the directory $from.
     */
    public static function relativeTo(string $from, string $to): string
    {
        [$fromPrefix, $fromSegments] = self::split(self::toCanonical($from));
        [$toPrefix, $toSegments] = self::split(self::toCanonical($to));

        if ($fromPrefix !== $toPrefix) {
            return self::toPlatform(self::assemble($toPrefix, $toSegments));
        }

        $common = 0;
        while (
            isset($fromSegments[$common], $toSegments[$common])
            && $fromSegments[$common] === $toSegments[$common]
        ) {
            $common++;
        }

        $segments = [
            ...array_fill(0, count($fromSegments) - $common, value: '..'),
            ...array_slice($toSegments, $common),
        ];

        $relative = self::assemble('', $segments);

        return self::toPlatform('' === $relative ? '.' : $relative);
    }

    /**
     * Resolves $path against $base when $path is relative, and returns it unchanged otherwise.
     */
    public static function resolve(string $base, string $path): string
    {
        if ('' === $path) {
            return self::normalise($base);
        }

        if (self::isAbsolute($path)) {
            return self::normalise($path);
        }

        return self::join($base, $path);
    }

    /**
     * Returns the path in the form used inside a URL: forward slashes, no duplicate separators,
     * no leading "./".
     */
    public static function toUrlPath(string $path): string
    {
        return self::normaliseCanonical(self::toCanonical($path));
    }

    /**
     * Replaces the extension of the last path segment. A leading dot in $extension is optional.
     */
    public static function withExtension(string $path, string $extension): string
    {
        $canonical = self::toCanonical($path);
        $separator = strrpos($canonical, needle: '/');
        $directory = false === $separator ? '' : substr($canonical, offset: 0, length: $separator + 1);
        $basename  = false === $separator ? $canonical : substr($canonical, $separator + 1);
        $dot       = strrpos($basename, needle: '.');

        if (false !== $dot && $dot > 0) {
            $basename = substr($basename, offset: 0, length: $dot);
        }

        $extension = ltrim($extension, characters: '.');

        return self::toPlatform($directory . $basename . ('' === $extension ? '' : ".{$extension}"));
    }

    /**
     * @param list<string> $segments
     */
    private static function assemble(string $prefix, array $segments): string
    {
        $joined = implode('/', $segments);

        return match ($prefix) {
            ''      => $joined,
            '/'     => "/{$joined}",
            default => $prefix . $joined,
        };
    }

    private static function normaliseCanonical(string $canonical): string
    {
        if ('' === $canonical) {
            return '';
        }

        [$prefix, $segments] = self::split(self::toCanonical($canonical));

        return self::assemble($prefix, $segments);
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private static function split(string $canonical): array
    {
        $prefix = '';
        $rest   = $canonical;

        if (preg_match('#^([A-Za-z]):/#', $rest, $matches) === 1) {
            $prefix = "{$matches[1]}:/";
            $rest   = substr($rest, strlen($matches[0]));
        } elseif (str_starts_with($rest, '//')) {
            $prefix = '//';
            $rest   = substr($rest, offset: 2);
        } elseif (str_starts_with($rest, '/')) {
            $prefix = '/';
            $rest   = substr($rest, offset: 1);
        }

        /** @var list<string> $segments */
        $segments = [];
        $floor    = '//' === $prefix ? 2 : 0;

        foreach (explode('/', $rest) as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }

            if ('..' === $segment) {
                $last = count($segments) - 1;
                if ($last >= $floor && '..' !== $segments[$last]) {
                    array_pop($segments);
                } elseif ('' === $prefix) {
                    $segments[] = '..';
                }

                continue;
            }

            $segments[] = $segment;
        }

        return [$prefix, $segments];
    }

    private static function toCanonical(string $path): string
    {
        return str_replace('\\', replace: '/', subject: $path);
    }

    private static function toPlatform(string $canonical): string
    {
        return str_replace('/', DIRECTORY_SEPARATOR, $canonical);
    }
}
