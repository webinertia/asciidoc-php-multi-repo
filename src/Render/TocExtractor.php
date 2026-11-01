<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Render;

use Generator;
use Webware\AsciidocPhp\Document;
use Webware\AsciidocPhp\MultiRepo\Report\ReasonCode;
use Webware\AsciidocPhp\MultiRepo\Source\SourceError;
use Webware\AsciidocPhp\MultiRepo\Source\SourceException;
use Webware\AsciidocPhp\Node\AbstractNode;
use Webware\AsciidocPhp\Node\Section;

use function array_filter;
use function array_unique;
use function array_values;
use function in_array;
use function sprintf;
use function trim;

/**
 * Reads a page's table of contents from the parsed document.
 *
 * Sections are walked in document order (depth first, so a level-3 heading follows the level-2
 * heading it sits under) and only the configured levels are returned. The configured levels are the
 * published heading levels, so level 2 is a `==` section and level 3 is a `===` section, which is the
 * same numbering `toclevels` uses. A section with no usable id fails the page instead of publishing a
 * sidebar link that goes nowhere, which is the `toc-target-missing` rule in the specification.
 *
 * @api
 */
final class TocExtractor
{
    /**
     * @param list<int> $levels
     *
     * @return list<TocEntry>
     *
     * @throws SourceException when a section that belongs in the table of contents has no id
     */
    public function extract(Document $document, array $levels, string $sourcePath = ''): array
    {
        $wanted = array_values(array_unique(array_filter(
            $levels,
            static fn(int $level): bool => $level > 0,
        )));

        /** @var list<TocEntry> $entries */
        $entries = [];

        foreach ($this->sections($document->getBlocks()) as $section) {
            $headingLevel = $section->getLevel() + 1;

            if (! in_array($headingLevel, $wanted, strict: true)) {
                continue;
            }

            $id    = trim((string) $section->getId());
            $title = (string) $section->getTitle();

            if ('' === $id) {
                throw new SourceException(new SourceError(
                    ReasonCode::TocTargetMissing,
                    sprintf(
                        'Section "%s" has no id, so its table-of-contents link would go nowhere.',
                        $title,
                    ),
                    null,
                    '' === $sourcePath ? null : $sourcePath,
                    'Give the section an explicit id, for example [#section-id] above its title.',
                ));
            }

            $entries[] = new TocEntry($headingLevel, $id, $title);
        }

        return $entries;
    }

    /**
     * @param list<AbstractNode> $blocks
     *
     * @return Generator<int, Section>
     */
    private function sections(array $blocks): Generator
    {
        foreach ($blocks as $block) {
            if (! $block instanceof Section) {
                continue;
            }

            yield $block;

            yield from $this->sections($block->getBlocks());
        }
    }
}
