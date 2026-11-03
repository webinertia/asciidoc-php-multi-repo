# Decision: the converter base stylesheet is published and linked

Status: accepted, supersedes research.md R2 and the stylesheet wording in tasks.md T032 and T035.

## Context

R2 of `research.md` recorded excluding the converter's `resources/css/asciidoctor.css` and hand
authoring every document rule in `resources/css/site.css`. The owner reviewed the aggregated site and
rejected that decision: the converter stylesheet holds the base rules for rendering an AsciiDoc
document, and not loading it means the site reimplements them.

## Decision

The multi-repo site publishes the converter's base stylesheet as a content hashed asset and links it
before its own stylesheet. The shell links three stylesheets in a fixed order:

1. `asciidoctor.*.css`, the base layer, read from the installed `webware/asciidoc-php` package
2. `site.*.css`, the override layer: colour tokens, site chrome, and dark theme corrections
3. `accent.*.css`, the single project accent rule

The base stylesheet is never inlined. `SiteConverter::renderStylesheet()` keeps returning an empty
string, and `AttributePolicy` forces both `noembed` and `linkcss` so no code path can fall back to an
inline `<style>` block. The base sheet is a fixed light palette with no custom properties, so
`site.css` keeps the colour overrides the dark theme needs.

`ThemeResolver::baseCss()` locates the file by reflecting on `Html5Converter`, because the converter
keeps the path in a private constant. A missing file raises a `RuntimeException` rather than
degrading to a page without a base layer.

## Guard

`PageRendererTest` asserts a rendered document contains no `<style` element and that the base link
precedes the site link. That assertion is the rule; removing the base sheet has to break a test.

## Consequences

The base rules are the converter's, not a copy, so a converter stylesheet change reaches the site on
the next dependency update. Document rules that `site.css` still declares are overrides, and a
duplicate of a base rule is a defect to remove.
