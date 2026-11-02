<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Cli\Serve;

use RuntimeException;
use Webware\AsciidocPhp\MultiRepo\Support\Path;

use function explode;
use function file_get_contents;
use function implode;
use function is_file;
use function pathinfo;
use function preg_match;
use function rawurldecode;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strpos;
use function strtolower;
use function substr;
use function trim;

use const PATHINFO_EXTENSION;

/**
 * Maps a request target onto a file in a built site.
 *
 * The resolution order is the one a static host uses for a directory based site: the path itself,
 * then the path as a directory holding `index.html`, then the path with `.html` appended. That order
 * is what makes `/slug/` serve the redirect document written to `{slug}/index.html`, `/slug/v2/`
 * serve the version entry page, and a clean URL such as `/slug/v2/guide` serve `guide.html`.
 *
 * Anything that does not resolve is answered from the site's own `404.html`, so a preview of an
 * unknown path looks exactly like the published site. A target that tries to leave the output
 * directory is treated as unknown rather than reported separately: there is nothing to gain from
 * telling a caller which of the two it was.
 */
final class StaticSiteHandler
{
    private const array CONTENT_TYPES = [
        'css'   => 'text/css',
        'gif'   => 'image/gif',
        'htm'   => 'text/html',
        'html'  => 'text/html',
        'ico'   => 'image/x-icon',
        'jpeg'  => 'image/jpeg',
        'jpg'   => 'image/jpeg',
        'js'    => 'text/javascript',
        'json'  => 'application/json',
        'map'   => 'application/json',
        'mjs'   => 'text/javascript',
        'otf'   => 'font/otf',
        'pdf'   => 'application/pdf',
        'png'   => 'image/png',
        'svg'   => 'image/svg+xml',
        'ttf'   => 'font/ttf',
        'txt'   => 'text/plain',
        'wasm'  => 'application/wasm',
        'webp'  => 'image/webp',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'xml'   => 'application/xml',
    ];

    /** Types that are always served with an explicit character set. */
    private const array TEXTUAL = [
        'application/json',
        'application/xml',
        'image/svg+xml',
        'text/css',
        'text/html',
        'text/javascript',
        'text/plain',
    ];

    private readonly string $root;

    private readonly string $basePath;

    public function __construct(
        string $root,
        string $basePath = '/',
        private readonly string $notFoundDocument = '404.html',
    ) {
        $this->root     = Path::normalise($root);
        $this->basePath = $this->normaliseBasePath($basePath);
    }

    /**
     * @throws \RuntimeException when a resolved file cannot be read
     */
    public function handle(string $method, string $target): StaticResponse
    {
        $method = strtolower($method);

        if ('get' !== $method && 'head' !== $method) {
            return StaticResponse::methodNotAllowed();
        }

        return $this->resolve($target);
    }

    /**
     * @throws \RuntimeException when a resolved file cannot be read
     */
    public function resolve(string $target): StaticResponse
    {
        $relative = $this->relativePath($target);

        if (null === $relative) {
            return $this->notFound();
        }

        foreach ($this->candidates($relative) as $candidate) {
            $absolute = Path::join($this->root, $candidate);

            if (! is_file($absolute)) {
                continue;
            }

            $contents = file_get_contents($absolute);

            if (false === $contents) {
                throw new RuntimeException("Unable to read \"{$absolute}\".");
            }

            return StaticResponse::ok($this->contentType($candidate), $contents);
        }

        return $this->notFound();
    }

    /**
     * @return list<string>
     */
    private function candidates(string $relative): array
    {
        if ('' === $relative) {
            return ['index.html'];
        }

        return [$relative, "{$relative}/index.html", "{$relative}.html"];
    }

    private function contentType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $type      = self::CONTENT_TYPES[$extension] ?? 'application/octet-stream';

        foreach (self::TEXTUAL as $textual) {
            if ($textual === $type) {
                return "{$type}; charset=utf-8";
            }
        }

        return $type;
    }

    private function normaliseBasePath(string $basePath): string
    {
        $normalised =
            '/'
            . trim(
                string    : Path::toUrlPath($basePath),
                characters: '/',
            );

        return '/' === $normalised ? '' : $normalised;
    }

    private function notFound(): StaticResponse
    {
        $document = Path::join($this->root, $this->notFoundDocument);

        if (is_file($document)) {
            $contents = file_get_contents($document);

            if (false !== $contents) {
                return StaticResponse::notFound($contents);
            }
        }

        return StaticResponse::notFound(
            "<!DOCTYPE html>\n<html lang=\"en\"><head><meta charset=\"utf-8\"><title>404 Not Found</title></head>"
                . "<body><h1>404 Not Found</h1><p>No page exists at this path in the built site.</p></body></html>\n",
        );
    }

    /**
     * The path of the request relative to the output directory, or null when it cannot name a file.
     */
    private function relativePath(string $target): ?string
    {
        $path = $target;

        $query = strpos(
            haystack: $path,
            needle  : '?',
        );
        if (false !== $query) {
            $path = substr(
                string: $path,
                offset: 0,
                length: $query,
            );
        }

        $fragment = strpos(
            haystack: $path,
            needle  : '#',
        );
        if (false !== $fragment) {
            $path = substr(
                string: $path,
                offset: 0,
                length: $fragment,
            );
        }

        if (! str_starts_with($path, '/')) {
            return null;
        }

        $path = rawurldecode($path);

        if (str_contains($path, "\0")) {
            return null;
        }

        if ('' !== $this->basePath) {
            $path = $this->stripBasePath($path);

            if (null === $path) {
                return null;
            }
        }

        /** @var list<string> $segments */
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }

            // A decoded backslash would be re-read as a separator when the path is joined to the
            // output directory on Windows, which would let "..\.." climb out of the site.
            if ('..' === $segment || str_contains($segment, '\\')) {
                return null;
            }

            if (preg_match('/[\x00-\x1f\x7f]/', $segment) === 1) {
                return null;
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    /**
     * The request path with the configured base path removed, or null when it does not sit under it.
     */
    private function stripBasePath(string $path): ?string
    {
        if ($path === $this->basePath) {
            return '/';
        }

        if (! str_starts_with($path, "{$this->basePath}/")) {
            return null;
        }

        return substr(
            string: $path,
            offset: strlen($this->basePath),
        );
    }
}
