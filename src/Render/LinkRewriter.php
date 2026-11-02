<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Render;

use function array_filter;
use function array_pop;
use function array_values;
use function dirname;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function preg_split;
use function rtrim;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strpos;
use function substr;
use function trim;

/**
 * Rewrites the links in a converted article fragment for the published site.
 *
 * Three things happen to an internal link: the `.adoc` extension becomes `.html`, the relative path
 * is resolved against the page's own published URL, and the site base path is prefixed. External
 * links are left exactly as written except for `rel="noopener"`.
 *
 * Resolution happens before prefixing, so a relative link never depends on where the base path sits
 * and no generated link starts with a hard-coded root slash when a base path is configured.
 *
 * @api
 */
final class LinkRewriter
{
    private const string SCHEME = '~^(?:[a-z][a-z0-9+.-]*:|//)~i';

    public function isExternal(string $url): bool
    {
        return 1 === preg_match(self::SCHEME, $url);
    }

    /**
     * Rewrites every anchor in the fragment.
     *
     * @param string $pageUrl the page's site-relative URL, for example `/project/latest/index.html`
     * @param string $basePath the site base path, `/` or `/docs/`
     */
    public function rewrite(string $html, string $pageUrl, string $basePath): string
    {
        return (string) preg_replace_callback(
            '/<a\b([^>]*)>/i',
            /** @param array<array-key, string> $match */
            fn(array $match): string => "<a{$this->rewriteAnchor($match[1] ?? '', $pageUrl, $basePath)}>",
            $html,
        );
    }

    /**
     * The published URL for an internal link target.
     */
    public function rewriteUrl(string $url, string $pageUrl, string $basePath): string
    {
        if ($this->isExternal($url)) {
            return $url;
        }

        $suffix = '';
        $hashAt = strpos($url, '#');
        if (false !== $hashAt) {
            $suffix = substr($url, $hashAt);
            $url    = substr($url, 0, $hashAt);
        }

        $queryAt = strpos($url, '?');
        if (false !== $queryAt) {
            $suffix = substr($url, $queryAt) . $suffix;
            $url    = substr($url, 0, $queryAt);
        }

        if ('' === $url) {
            return $this->rewriteFragmentTarget($suffix, $pageUrl, $basePath);
        }

        $path     = (string) preg_replace('/\.adoc$/i', '.html', $url);
        $resolved = $this->resolve($pageUrl, $path);

        return rtrim($basePath, '/') . $resolved . $suffix;
    }

    private function ensureNoopener(string $attributes): string
    {
        $match = [];

        if (1 === preg_match('/\brel\s*=\s*"([^"]*)"/i', $attributes, $match)) {
            $parts  = preg_split('/\s+/', trim($match[1] ?? ''));
            $values = array_values(array_filter(
                is_array($parts) ? $parts : [],
                static fn(string $value): bool => '' !== $value,
            ));

            if (in_array('noopener', $values, strict: true)) {
                return $attributes;
            }

            $values[] = 'noopener';

            return str_replace($match[0] ?? '', 'rel="' . implode(' ', $values) . '"', $attributes);
        }

        return rtrim($attributes) . ' rel="noopener"';
    }

    private function normalise(string $path): string
    {
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }

            if ('..' === $segment) {
                if ([] !== $segments) {
                    array_pop($segments);
                }

                continue;
            }

            $segments[] = $segment;
        }

        return '/' . implode('/', $segments);
    }

    private function resolve(string $pageUrl, string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $this->normalise($path);
        }

        return $this->normalise(dirname($pageUrl) . '/' . $path);
    }

    private function rewriteAnchor(string $attributes, string $pageUrl, string $basePath): string
    {
        if (! str_contains($attributes, 'href')) {
            return $attributes;
        }

        $match = [];

        if (1 !== preg_match('/\bhref\s*=\s*"([^"]*)"/i', $attributes, $match)) {
            return $attributes;
        }

        $href = $match[1] ?? '';

        if ($this->isExternal($href)) {
            return $this->ensureNoopener($attributes);
        }

        $rewritten = $this->rewriteUrl($href, $pageUrl, $basePath);

        return (string) preg_replace_callback(
            '/\bhref\s*=\s*"[^"]*"/i',
            static fn(): string => "href=\"{$rewritten}\"",
            $attributes,
            limit: 1,
        );
    }

    /**
     * An `xref` to another page survives the converter as a fragment ending in `.adoc`, so it is
     * resolved against the page's own directory rather than published as an in-page anchor.
     */
    private function rewriteFragmentTarget(string $fragment, string $pageUrl, string $basePath): string
    {
        $match = [];

        if (1 !== preg_match('/^#(.*\.adoc)(?:#(.*))?$/i', $fragment, $match)) {
            return $fragment;
        }

        $path     = (string) preg_replace('/\.adoc$/i', '.html', $match[1] ?? '');
        $resolved = rtrim($basePath, '/') . $this->resolve($pageUrl, $path);
        $anchor   = $match[2] ?? '';

        if ('' !== $anchor) {
            $anchor = "#{$anchor}";
        }

        return $resolved . $anchor;
    }
}
