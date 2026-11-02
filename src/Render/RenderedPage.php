<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Render;

/**
 * The result of rendering one page.
 *
 * `outputPath` is relative to the output directory and uses "/" separators. `urlPath` is the same
 * path with a leading slash, before the base path is applied, which is what internal link
 * resolution and the table of contents work from.
 *
 * @api
 */
final readonly class RenderedPage
{
    /**
     * @param list<TocEntry> $toc
     * @param list<string>   $headings
     */
    public function __construct(
        private string $html,
        private string $outputPath,
        private string $urlPath,
        private string $title,
        private array $toc = [],
        private array $headings = [],
        private string $searchText = '',
    ) {}

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return $this->headings;
    }

    public function html(): string
    {
        return $this->html;
    }

    public function outputPath(): string
    {
        return $this->outputPath;
    }

    /**
     * The record Group 5's search index is built from.
     *
     * @return array{title: string, text: string, headings: list<string>}
     */
    public function searchDocument(): array
    {
        return [
            'title'    => $this->title,
            'text'     => $this->searchText,
            'headings' => $this->headings,
        ];
    }

    public function searchText(): string
    {
        return $this->searchText;
    }

    public function title(): string
    {
        return $this->title;
    }

    /**
     * @return list<TocEntry>
     */
    public function toc(): array
    {
        return $this->toc;
    }

    public function urlPath(): string
    {
        return $this->urlPath;
    }
}
