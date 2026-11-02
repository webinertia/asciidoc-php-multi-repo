<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Search;

use function html_entity_decode;
use function mb_strlen;
use function mb_strrpos;
use function mb_substr;
use function preg_match;
use function preg_replace;
use function str_replace;
use function trim;

use const ENT_HTML5;
use const ENT_QUOTES;

/**
 * Turns rendered article HTML into the plain text a search index and a landing card carry.
 *
 * Truncation happens on a word boundary rather than a character boundary, because a description cut
 * mid-word reads as corruption on a landing page. A limit of zero means "no limit", which is the
 * convention the render configuration already uses.
 *
 * @api
 */
final readonly class TextExtractor
{
    private const string WHITESPACE = '/\s+/u';

    public function __construct(
        private int $limit = 2000,
    ) {}

    /**
     * The first paragraph of a rendered article, which is the landing page description fallback.
     */
    public function firstParagraph(string $html): string
    {
        if (1 !== preg_match('~<p(?:\s[^>]*)?>(.*?)</p>~s', $this->stripBlocks($html), $matches)) {
            return '';
        }

        return $this->truncate($this->collapse($this->stripTags($matches[1] ?? '')));
    }

    public function limit(): int
    {
        return $this->limit;
    }

    /**
     * Strips markup, collapses whitespace, and truncates on a word boundary.
     */
    public function plain(string $html, ?int $limit = null): string
    {
        return $this->truncate($this->collapse($this->stripTags($this->stripBlocks($html))), $limit);
    }

    /**
     * Truncates on a word boundary. A limit of zero or less disables truncation.
     */
    public function truncate(string $text, ?int $limit = null): string
    {
        $limit ??= $this->limit;

        if ($limit <= 0 || mb_strlen($text) <= $limit) {
            return $text;
        }

        $cut   = mb_substr($text, 0, $limit);
        $space = $this->lastSpace($cut);

        if ($space > 0) {
            $cut = mb_substr($cut, 0, $space);
        }

        return trim($cut);
    }

    private function collapse(string $text): string
    {
        $text = str_replace(["\xC2\xA0", "\t", "\r", "\n"], ' ', $text);
        $text = preg_replace(self::WHITESPACE, ' ', $text) ?? $text;

        return trim($text);
    }

    private function decode(string $text): string
    {
        if (1 !== preg_match('~&[a-zA-Z0-9#]+;~', $text)) {
            return $text;
        }

        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function lastSpace(string $text): int
    {
        $position = mb_strrpos($text, ' ');

        return false === $position ? -1 : $position;
    }

    /**
     * Removes whole elements whose text content is never prose.
     */
    private function stripBlocks(string $html): string
    {
        return preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', ' ', $html) ?? $html;
    }

    private function stripTags(string $html): string
    {
        $text = preg_replace('~<[^>]*>~', ' ', $html) ?? $html;

        return $this->decode($text);
    }
}
