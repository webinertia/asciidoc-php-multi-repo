/*
 * Renders `[source,mermaid]` blocks with the vendored Mermaid build.
 *
 * The converter emits those blocks as `<pre class="highlight"><code class="language-mermaid">`, so
 * the diagram source arrives as page text and the disclosure the author wrapped it in is already
 * their own markup. This file replaces only the contents of that `<pre>` with the drawn SVG, which
 * leaves the surrounding `<details>` element intact and its summary clickable.
 *
 * The page colour mode is read from the `data-theme` attribute the shell paints before first paint,
 * so the diagram is drawn in the matching Mermaid theme and redrawn when the toggle changes it.
 */
(function () {
    'use strict';

    var renderer = window.mermaid;

    if (!renderer) {
        return;
    }

    var root = document.documentElement;
    var codes = document.querySelectorAll('code.language-mermaid, code[data-lang="mermaid"]');
    var diagrams = [];
    var sequence = 0;

    if (0 === codes.length) {
        return;
    }

    for (var i = 0; i < codes.length; i++) {
        var code = codes[i];
        var host = typeof code.closest === 'function' ? code.closest('pre') : null;

        if (!host) {
            host = code;
        }

        host.classList.add('mermaid-diagram');
        diagrams.push({ host: host, source: code.textContent });
    }

    function theme() {
        return root.getAttribute('data-theme') === 'light' ? 'default' : 'dark';
    }

    /*
     * A failed draw leaves Mermaid's own scratch element behind in some releases. It is removed
     * before the result is inserted, so the lookup cannot find the diagram that was just drawn.
     */
    function discardScratch(id) {
        var candidates = ['d' + id, id];

        for (var i = 0; i < candidates.length; i++) {
            var stray = document.getElementById(candidates[i]);

            if (stray && stray.parentNode) {
                stray.parentNode.removeChild(stray);
            }
        }
    }

    function draw(diagram) {
        var id = 'mermaid-diagram-' + (++sequence);

        renderer.initialize({
            startOnLoad: false,
            securityLevel: 'strict',
            theme: theme()
        });

        renderer.render(id, diagram.source).then(function (result) {
            discardScratch(id);
            diagram.host.innerHTML = result.svg;
        }, function () {
            // Keep the author's source text when the diagram cannot be drawn.
            discardScratch(id);
        });
    }

    var painted = root.getAttribute('data-theme');

    function follow() {
        var current = root.getAttribute('data-theme');

        if (current === painted) {
            return;
        }

        painted = current;

        for (var i = 0; i < diagrams.length; i++) {
            draw(diagrams[i]);
        }
    }

    if (typeof MutationObserver === 'function') {
        new MutationObserver(follow).observe(root, {
            attributes: true,
            attributeFilter: ['data-theme']
        });
    }

    for (var j = 0; j < diagrams.length; j++) {
        draw(diagrams[j]);
    }
})();
