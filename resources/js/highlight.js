/*
 * Draws the syntax colours in a listing block with the vendored highlight.js bundle.
 *
 * The converter emits `<code class="language-php hljs" data-lang="php">` with the source text
 * unparsed, so the colours only appear once this runs. The stylesheet that paints the token classes
 * is linked by the page shell next to this script, and it carries both modes, so flipping the theme
 * repaints the tokens with no work here.
 *
 * A Mermaid block keeps the same shape (`class="language-mermaid hljs"`), and it is left alone:
 * the diagram payload renders it to SVG, and tokenising it here would replace the diagram source
 * with spans that renderer no longer recognises.
 */
(function () {
    'use strict';

    var SELECTOR = 'pre > code[class*="language-"]';

    function isMermaid(node) {
        return node.classList.contains('language-mermaid')
            || node.getAttribute('data-lang') === 'mermaid';
    }

    function draw() {
        if (typeof window.hljs !== 'object' || window.hljs === null) {
            return;
        }

        var nodes = document.querySelectorAll(SELECTOR);

        for (var index = 0; index < nodes.length; index += 1) {
            var node = nodes[index];

            if (isMermaid(node) || node.hasAttribute('data-highlighted')) {
                continue;
            }

            window.hljs.highlightElement(node);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', draw);
    } else {
        draw();
    }
}());
