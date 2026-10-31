# Phase 0 Research: Multi-Repo Documentation Site Builder

**Feature**: `specs/001-multi-repo-docs-site`

**Purpose**: resolve the technical unknowns that shape the plan, against the real code of the pinned
converter and the real shape of the documentation repositories.

## R1. Can the converter's document shell be replaced through the public interface?

**Question**: the site needs its own `<html>` document with a project switcher, version selector, and
a site-owned sidebar. Can that be produced without modifying `webware/asciidoc-php`?

**Evidence**:

- `ConverterInterface::convert(AbstractNode $node, ?string $transform = null, array $opts = [])` is
  public and non-final.
- `Html5Converter` is a concrete class, not `final`, and its `convertDocument()` builds the whole
  document.
- `AbstractNode::convert()` delegates to `$this->document->getConverter()->convert($this)`.
- `Asciidoc::convert()` and `Asciidoc::convertFile()` accept a `converter` option, and
  `Document::__construct` and `Document::setConverter()` accept an instance.

**Decision**: `Render\SiteConverter extends Html5Converter` and overrides `convertDocument()` to
return article markup only. `PageShell` wraps that fragment in the site document.

**Rejected alternatives**:

- Post-process the converter's full HTML with string or DOM surgery. Rejected: brittle against markup
  changes, and it cannot remove the converter's head cleanly without parsing.
- Render with `header_footer` false and wrap manually. Rejected: `convertEmbedded()` still returns a
  `<div id="content">` wrapper and the head, canonical, and asset links would still have to be built
  separately, so the saving is one method and the cost is a second code path.

## R2. How is the converter's default stylesheet kept out of the output?

**Evidence**: `Html5Converter::renderStylesheet()` honours `:linkcss:`, `:stylesheet:`,
`:stylesdir:`, `:noembed:`, and `:stylesheet!:`; when none is set it embeds
`resources/css/asciidoctor.css` inline.

**Decision**: apply all three defences. Pass `stylesheet!` and `noembed` in the attribute set,
override `renderStylesheet()` to return an empty string, and link the site stylesheet in the shell.
A single defence would be enough today, but a converter release that changes attribute handling
would silently reintroduce a duplicate or external stylesheet, which SC-007 forbids.

**Verification**: the render test asserts the output contains no `asciidoctor.css`, no `<style>`
element from the converter, and exactly two local stylesheet links.

## R3. How is output made deterministic when the converter reads the clock?

**Evidence**: `Document::initBuiltinAttributes()` derives `docdate`, `doctime`, `docdatetime`,
`localdate`, `localtime`, and `localdatetime` from the current time. Caller-supplied `attributes` are
applied after the built-ins, so a caller value wins. `Document::setAttribute(..., overridable: false)`
exists for locking.

**Decision**: `AttributePolicy` supplies fixed empty values for all six attributes on every page.
Because the shell is site-owned, those values are never printed anyway; the overrides exist so that a
document body that references `{docdate}` also produces stable output. `DeterminismGuard` scans the
output for clock-shaped strings and fails the build if one appears, which catches the case where a
future converter change moves the attribute names.

**Rejected alternative**: freezing the process clock. Rejected: it needs an extension or a shim and
would affect unrelated code.

## R4. How do redirects work on GitHub Pages?

**Evidence**: GitHub Pages serves a static branch. It cannot issue a 301 from repository content.
`404.html` is the only server-side hook, and it is not suitable for path rewriting.

**Decision**: write a redirect document at each project root containing a zero-delay meta refresh, a
canonical link to the target, a `noindex` robots directive, and a plain anchor. This satisfies FR-030
and FR-031, works without JavaScript, and keeps crawlers pointed at the canonical target.

**Rejected alternatives**:

- A `404.html` script that rewrites the path. Rejected: it depends on JavaScript and breaks the
  no-script requirement.
- Relying on a directory listing or a generated index at the project root. Rejected: it duplicates the
  landing page and gives the project root two competing canonical URLs.

## R5. How is search implemented without a new binary?

**Evidence**: the site has no server runtime, and the constitution forbids new runtime binaries. A
2,000-page site is small enough for a client-side index.

**Decision**: per-project JSON index plus a site-level index, loaded on demand, scored in vanilla
JavaScript with field weights (title 3, heading 2, body 1) and a URL tie-break. Text is
whitespace-normalised and truncated at a word boundary.

**Rejected alternatives**:

- Pagefind or Lunr. Rejected: Pagefind is a binary, Lunr is a third-party library from an external
  origin or a vendored dependency with its own licence and update burden.
- A pre-built inverted index. Rejected: it is larger than the documents at this scale and makes the
  index harder to inspect and diff.

## R6. How are repositories fetched?

**Evidence**: repositories are public, so no credential is needed for content. `git` is available in
the action image and the container stack, and is the only tool guaranteed to be present.

**Decision**: `git init`, `git remote add`, `git fetch --depth 1 origin <defaultBranch>`,
`git checkout FETCH_HEAD` into a per-repository cache directory, invoked through `proc_open` with an
argument array. The resolved `FETCH_HEAD` SHA becomes the cache revision key. Retries wrap the whole
sequence.

**Rejected alternatives**:

- The GitHub archive tarball endpoint. Rejected: it adds a second HTTP client path and a tar
  extraction dependency, and it does not give a natural revision key.
- A full clone. Rejected: history is irrelevant and a shallow fetch is an order of magnitude faster.

## R7. How is discovery implemented?

**Evidence**: the organization custom property `ci-target` already exists and is read from the
organization's repository list. The equivalent for documentation is `doc-target`. A token is required
because custom property values are not readable anonymously at the organization level.

**Decision**: `GitHubPropertyDiscovery` calls the GitHub REST API through `ext-curl`, reading the
token from the environment variable named in configuration, following pagination, and treating only
the configured onboard value as onboarded. `ManifestDiscovery` provides the offline path used by
tests and air-gapped builds.

**Rejected alternatives**:

- Shelling out to `gh`. Rejected: the action image would have to ship the CLI, and its JSON surface
  changes independently of this package.
- GraphQL. Rejected: custom property values are not exposed there in the same shape, so REST is the
  narrower dependency.

## R8. What is the incremental cache key?

**Evidence**: a project's output depends on its resolved configuration, its source bytes, the
repository revision, and the theme assets. It does not depend on wall-clock time, other projects'
content, or the output directory.

**Decision**: the SHA-256 of a canonical JSON document containing the project's resolved configuration
slice, the repository name and resolved revision, the relative path and content hash of every
published source file, the content hash of every theme and script asset, and the package's output
format version. Aggregate pages are rebuilt whenever the publishable set, any project's metadata, or
the theme changes.

**Rejected alternatives**:

- Keying on revision alone. Rejected: it misses local edits during `watch` and configuration changes.
- Keying on source hashes alone. Rejected: it misses theme changes, which alter every page's head.
- Comparing rendered output instead of inputs. Rejected: it requires rendering first, which defeats
  the purpose.

## R9. How are versions ordered?

**Evidence**: the organization uses `N.N.x` branch names for releases but documentation versions are
directories named `vN`. The fixtures include `v1`, `v2`, and the specification's edge cases include
`v10`.

**Decision**: a version directory matches `^v[1-9][0-9]*$`, and ordering compares the integer suffix,
so `v10` outranks `v2`. The label `latest` is not numeric and is used only for unversioned
documentation, where it is also the highest available version.

**Rejected alternatives**:

- Lexicographic ordering. Rejected: `v10` would sort below `v2`.
- Semantic version directories (`v2.1.0`). Rejected: the user's decision is `docs/vN` directories,
  and the URL layout is `/slug/vN/`.

## R10. How is the sidebar TOC built without the converter's TOC?

**Evidence**: `Html5Converter::renderSidebarToc()` uses `$document->getSections()`, and `Section`
exposes `getId()`, `getLevel()`, and `getTitle()`. `convertSection()` emits `<hN id="...">`.

**Decision**: `TocExtractor` reads the same section list and renders the sidebar in the site shell.
This decouples the sidebar from `:toc-placement:` and keeps the markup under site control, while the
anchor ids remain the converter's, so the links resolve. A section with an empty id fails the page
with `toc-target-missing` rather than publishing a dead link.

**Rejected alternatives**:

- Using `:toc: left` and styling the converter's TOC. Rejected: the markup is converter-owned and
  cannot carry the site's accessibility attributes or the outdated banner placement.
- Parsing the rendered HTML for headings. Rejected: it is a second source of truth and would disagree
  with the ids the converter actually emitted.

## R11. Which Markdown question is unresolved, and what is the seam?

**Evidence**: both target repositories currently hold Markdown. The user has settled that v1 fails a
Markdown-only project, and separately stated that a Markdown to AsciiDoc converter is planned so
repositories can keep Markdown later.

**Decision**: v1 accepts AsciiDoc only and reports `no-asciidoc-sources`. `Source\SourcePreparer` is
the single place where a source file becomes renderer input, and `Source\SourceFormat` already
distinguishes `Asciidoc`, `Markdown`, and `Unsupported`. A future conversion stage extends
`SourcePreparer` only.

**Rejected alternative**: detecting Markdown now and converting with a placeholder path. Rejected:
it would ship an untested conversion path and a second failure mode in v1.
