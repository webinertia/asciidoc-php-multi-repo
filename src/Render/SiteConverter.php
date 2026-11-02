<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Render;

use Override;
use Webware\AsciidocPhp\Converter\AbstractConverter;
use Webware\AsciidocPhp\Converter\Html5Converter;
use Webware\AsciidocPhp\Document;
use Webware\AsciidocPhp\Node\AbstractNode;

/**
 * The converter the site renders pages with: the HTML5 converter without its document chrome.
 *
 * `Html5Converter` is `final` and its `convertDocument()` and `renderStylesheet()` are `private`, so
 * a subclass cannot override them. This class therefore extends `AbstractConverter` and delegates
 * every non-document node to a `Html5Converter` instance, which is what keeps section markup,
 * heading ids, and inline substitutions byte-identical to the parent converter. Only the document
 * node is produced here, and it is produced as the article fragment the page shell wraps:
 *
 *     <article class="doc">
 *     ...sections...
 *     </article>
 *
 * The block loop is deliberately the same loop the parent uses for its embedded transform, so the
 * fragment is exactly the parent's embedded output with a different wrapper.
 *
 * The parent converter is stateless, and a `SiteConverter` instance is created per rendered page, so
 * the captured document cannot leak from one page to the next.
 *
 * @api
 */
final class SiteConverter extends AbstractConverter
{
    public const string ARTICLE_OPEN = "<article class=\"doc\">\n";

    public const string ARTICLE_CLOSE = "</article>\n";

    private ?Document $lastDocument = null;

    public function __construct(
        private readonly Html5Converter $html = new Html5Converter(),
    ) {}

    /**
     * @param array<string, mixed> $opts
     */
    #[Override]
    public function convert(AbstractNode $node, ?string $transform = null, array $opts = []): string
    {
        if ($node instanceof Document) {
            return $this->convertDocument($node);
        }

        return $this->html->convert($node, $transform, $opts);
    }

    /**
     * The article fragment for a parsed document.
     */
    public function convertDocument(Document $document): string
    {
        $this->lastDocument = $document;

        $html = '';
        foreach ($document->getBlocks() as $block) {
            $html .= $block->convert();
        }

        return self::ARTICLE_OPEN . $html . self::ARTICLE_CLOSE;
    }

    #[Override]
    public function handles(string $transform): bool
    {
        return 'document' === $transform || $this->html->handles($transform);
    }

    /**
     * The document this converter produced most recently, which the table of contents is read from.
     */
    public function lastDocument(): ?Document
    {
        return $this->lastDocument;
    }

    /**
     * The site shell owns the stylesheet, so the converter never emits one.
     */
    public function renderStylesheet(): string
    {
        return '';
    }
}
