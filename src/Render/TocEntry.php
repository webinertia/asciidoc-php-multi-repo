<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Render;

/**
 * One entry of a page's table of contents.
 *
 * The title is the raw section title as written in the source, not the converted heading, so the
 * shell can escape it exactly once.
 *
 * @api
 */
final readonly class TocEntry
{
    public function __construct(
        private int $level,
        private string $id,
        private string $title,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function level(): int
    {
        return $this->level;
    }

    public function title(): string
    {
        return $this->title;
    }

    /**
     * @return array{level: int, id: string, title: string}
     */
    public function toArray(): array
    {
        return [
            'level' => $this->level,
            'id'    => $this->id,
            'title' => $this->title,
        ];
    }
}
