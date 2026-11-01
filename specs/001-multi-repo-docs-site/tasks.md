# Tasks: Multi-Repo Documentation Site Builder

**Feature**: `specs/001-multi-repo-docs-site`

**Plan**: [plan.md](./plan.md)

**Spec**: [spec.md](./spec.md)

**Input**: Implementation plan, data model, and contracts in this directory.

## Execution strategy

One pull request per group. Groups are ordered so that every PR lands with a working, testable
increment and never depends on unmerged work from a later group.

| Group | PR title | Depends on | Delivers |
| --- | --- | --- | --- |
| 1 | `feat: package skeleton, configuration, and contracts` | none | Phase 1 and Phase 2 foundations |
| 2 | `feat: discovery, fetch, and version detection` | Group 1 | Story 1 source pipeline |
| 3 | `feat: rendering, page shell, and theme` | Group 2 | Story 1 output and Story 3 chrome |
| 4 | `feat: navigation, redirects, and landing page` | Group 3 | Story 3 complete |
| 5 | `feat: search index and browser search` | Group 4 | Story 4 |
| 6 | `feat: incremental builds, determinism, and reporting` | Group 5 | Story 2 and Story 5 |
| 7 | `feat: container stack, CLI contract, and onboarding docs` | Group 6 | Story 6, Story 7, onboarding |
| 8 | `docs: author, operator, theme, and troubleshooting guides` | Group 7 | Documentation set and dogfooded package docs |

Tasks marked `[P]` inside a group can be executed in parallel by different people because they touch
disjoint files. A task without `[P]` must follow the task above it in the same group.

## Phase 1: Setup (Group 1)

- [X] T001 Create the package manifest: `composer.json` with name `webware/asciidoc-php-multi-repo`,
  PHP constraint `~8.4.0 || ~8.5.0`, autoload `Webware\AsciidocPhpMultiRepo\` from `src/`, the binary
  `bin/asciidoc-php-multi-repo`, `require` on `webware/asciidoc-php`, `ext-json`, `ext-curl`,
  `ext-mbstring`, and dev requirements `phpunit/phpunit` and `phpstan/phpstan`. Acceptance: running
  `composer validate --strict` succeeds and `composer install` completes on PHP 8.4.
- [X] T002 [P] Add `phpunit.xml.dist`, `phpstan.neon` at level 10 with `src/` and `bin/` analysed, and
  Composer scripts `test` and `sa`. Acceptance: `composer test` runs zero tests and exits 0;
  `composer sa` reports no errors on the empty skeleton.
- [X] T003 [P] Add `.gitignore` covering `vendor/`, `build/`, `.cache/`, and PHPUnit caches.
  Acceptance: a build into `build/site` and a cache in `.cache/multi-repo` leave `git status` clean.
- [X] T004 Create `src/Support/Path.php` with separator-agnostic `join()`, `normalise()`,
  `relativeTo()`, `withExtension()`, and `toUrlPath()`. Acceptance: unit tests cover Windows and
  POSIX inputs, mixed separators, `..` segments, and trailing separators; no test uses a hard-coded
  separator.
- [X] T005 Create `src/Support/ProcessRunner.php` wrapping `proc_open` with an argument array, captured
  stdout and stderr, a timeout, and a non-zero-exit exception carrying both streams. Acceptance:
  tests prove no shell interpolation occurs (an argument containing a space and a quote reaches the
  child verbatim) and that a failing command raises with stderr preserved.
- [X] T006 [P] Create `src/Support/Filesystem.php` with atomic write (temp file plus rename in the same
  directory), write-only-if-changed, recursive scan returning sorted paths, and a SHA-256 file hash.
  Acceptance: writing identical bytes leaves the file modification time unchanged; a partial write
  failure leaves no temp file behind.
- [X] T007 [P] Create `src/Support/Json.php` with deterministic encode (fixed flags, no escaped
  slashes, four-space indent for reports and compact for indexes) and decode with error mapping.
  Acceptance: encoding the same array twice yields identical bytes; invalid JSON raises a typed
  exception naming the path and byte offset.
- [X] T008 Create `src/Config/SiteConfig.php`, `ProjectOverride.php`, `ThemeConfig.php`,
  `BuildConfig.php` as immutable value objects with named constructors and accessors. Acceptance:
  every accessor is covered by a test; no setter exists.
- [X] T009 Create `src/Config/SchemaValidator.php`, a dependency-free JSON Schema subset validator
  supporting `type`, `required`, `properties`, `additionalProperties`, `enum`, `pattern`,
  `minimum`, `maximum`, `items`, and `$ref` to local definitions. Acceptance: unit tests cover a pass
  case and one failing case per keyword; unknown keywords raise rather than pass silently.
- [X] T010 Create `config/site.schema.json` matching `contracts/site.schema.json` and wire
  `src/Config/SiteConfigLoader.php` to load, decode, validate, apply defaults, and resolve relative
  paths against the config file's directory. Acceptance: a config in a temp directory resolves output
  paths relative to itself regardless of the process working directory; an unknown top-level key
  fails with a message naming the key and its JSON pointer.
- [X] T011 [P] Add `tests/fixtures/org/` with one directory per fixture repository: `versioned`
  (`docs/v1`, `docs/v2`, entry pages, a nested directory, a second page), `unversioned` (`docs/` with
  `index.adoc`), `markdown-only` (`docs/v1/*.md`), `missing-entry` (`docs/v1/other.adoc`),
  `broken-include` (`docs/v1/index.adoc` including a missing file), `archived`, and `mixed`
  (version dirs plus a loose `.adoc`). Acceptance: a test enumerates the fixtures and asserts the
  expected set, so a later accidental deletion fails a test rather than silently weakening coverage.
- [X] T012 Create `tests/fixtures/manifest.json` describing the fixture organization in the offline
  manifest format, including slug, default branch, and archived flag. Acceptance: the manifest parses
  through `Config\` validation and lists exactly the fixture directories on disk.

## Phase 2: Foundational CLI and contracts (Group 1)

- [X] T013 Create `src/Cli/ExitCode.php` as an enum with `Success = 0`, `Degraded = 1`,
  `ConfigError = 2`, `DiscoveryError = 3`, `InternalError = 4`, plus a `fromBuildReport()` mapping.
  Acceptance: a table-driven test maps every project-status combination to the documented code,
  including all-`skipped` mapping to 0 and any `partial` mapping to 1.
- [X] T014 Create `src/Cli/Options.php` as an immutable parsed-options object and
  `src/Cli/Application.php` with global flag parsing, subcommand dispatch, `--help`, and `--version`.
  Acceptance: unknown flags exit 2 with usage on stderr; `--help` exits 0 and lists every documented
  flag; parsing is covered by tests for each flag and for repeatable flags.
- [X] T015 Create `src/Cli/Output.php` for stderr progress, stdout summary, `-q`, `-v`, `-t`, and
  `--no-color` with colour disabled automatically when not a TTY. Acceptance: in quiet mode only
  errors reach stderr and the summary still reaches stdout; a non-TTY run emits no ANSI escapes.
- [X] T016 Create `bin/asciidoc-php-multi-repo` that requires the Composer autoloader from either
  `vendor/autoload.php` or the installed package location, builds argv, and exits with
  `Application::run()`'s code. Acceptance: invoking the binary with no arguments prints usage and
  exits 2; invoking it from a different working directory resolves the same config when `-c` is
  absolute.
- [X] T017 Add the stub subcommands `discover`, `fetch`, `build`, `watch`, `serve`, `validate`, and
  `clean` as classes in `src/Cli/Command/` that parse their options and exit 4 with a "not
  implemented" message. Acceptance: `--help` lists all seven; each stub is reachable and returns 4.
- [X] T018 Write `contracts/cli.md`, `contracts/report.schema.json`,
  `contracts/search-index.schema.json`, and `contracts/site.schema.json` in this directory. Acceptance:
  `composer validate` passes on the schema files' JSON syntax; a test loads each schema through
  `SchemaValidator` and validates a minimal conforming document.
- [X] T019 Add `tests/Contract/ContractDocumentationTest.php` asserting that every flag named in
  `contracts/cli.md` is accepted by `Application` and that every exit code in the document exists in
  `ExitCode`. Acceptance: removing a flag from the code without updating the document fails the test.

**Checkpoint Group 1**: the package installs, validates a config, parses every documented flag, and
has a contract document that is tested against the code. No site output yet.

## Phase 3: Discovery, fetch, and version detection (Group 2, Story 1)

- [ ] T020 Create `src/Discovery/SlugNormalizer.php` implementing lowercase and single-hyphen
  collapse for characters outside `[a-z0-9]`, plus trimming of leading and trailing hyphens.
  Acceptance: table-driven tests cover `Message_Bus`, `message.bus`, `message--bus`, `MessageBus`,
  names that collapse to empty (which must raise), and names that already conform.
- [ ] T021 Create `src/Discovery/SlugCollisionDetector.php` rejecting two projects with the same slug
  with a message naming both repositories. Acceptance: a config with an override colliding with a
  discovered slug exits 2 before any fetch; a test asserts no fetch was attempted.
- [ ] T022 Create `src/Discovery/ProjectDiscoveryInterface.php`,
  `DiscoveredRepository.php`, and `ManifestDiscovery.php`. Acceptance: manifest mode produces the
  fixture project set with zero network calls, proven by a test that replaces the HTTP transport with
  one that fails on use.
- [ ] T023 Create `src/Discovery/GitHubPropertyDiscovery.php` using `ext-curl` against the repository
  search and custom property values endpoints, following pagination, reading the token from the
  configured environment variable, and mapping only the configured onboard value to onboarded.
  Acceptance: tests against a local HTTP fixture serve a two-page response and produce the same set
  as a one-page response; the token never appears in an exception message, log line, or report.
- [ ] T024 Wire `discover` to print one line per repository with name, slug, and onboarded state, and
  to write nothing else. Acceptance: `discover --manifest tests/fixtures/manifest.json` exits 0 and
  prints the fixture set in slug order; `discover` with an invalid token exits 3 and names the
  organization and property.
- [ ] T025 Create `src/Source/RepositoryFetcher.php` performing `git init`, `git remote add`,
  `git fetch --depth 1 origin <branch>`, and `git checkout FETCH_HEAD` into a per-repository cache
  directory, with a configurable retry count. Acceptance: a fetch failure is retried the configured
  number of times and the attempt count is recorded; a successful fetch records the resolved revision;
  a second fetch of an unchanged revision does not re-checkout.
- [ ] T026 Create `src/Source/FetchedRepository.php` exposing the resolved revision, the
  documentation directory path, and a sorted file list relative to the repository root. Acceptance:
  a fetched fixture reports the revision and exactly the expected relative paths; a repository with no
  documentation directory is reported with reason `no-asciidoc-sources` rather than throwing.
- [ ] T027 Create `src/Source/VersionDetector.php` selecting subdirectories matching the configured
  version pattern, treating the documentation directory itself as `latest` when no subdirectory
  matches, and ignoring loose files when version directories exist (recording a warning). Acceptance:
  tests cover `v1`/`v2`/`v10` ordering, `v0`/`v01`/`V2` being treated as content directories, the
  unversioned case, the mixed case with its warning, and a documentation directory containing neither.
- [ ] T028 Create `src/Source/PageEnumerator.php` resolving the entry page from the configured
  candidates and enumerating renderable pages with extensions filtered, underscore-prefixed files
  excluded, exclude patterns applied, and nested directories preserved. Acceptance: the versioned
  fixture yields the exact expected page list; a version with no entry page fails with
  `missing-entry` and names the candidates; a Markdown-only fixture fails with `no-asciidoc-sources`.
- [ ] T029 Create `src/Source/SourcePreparer.php` and `src/Source/SourceFormat.php` as the single
  source-preparation seam: read the file, detect format, reject non-UTF-8 with `invalid-encoding`,
  normalise line endings to `\n`, and return source text plus origin metadata. Acceptance: a CRLF
  fixture renders identically to its LF twin; an invalid UTF-8 fixture fails with the file path; a
  Markdown file is classified `Markdown` and rejected with `no-asciidoc-sources` at the project level,
  never passed to the renderer.
- [ ] T030 Create `src/Source/SourceError.php` and `src/Report/ReasonCode.php` as the closed reason-code
  set (`no-asciidoc-sources`, `missing-entry`, `fetch-failed`, `archived`,
  `include-outside-root`, `toc-target-missing`, `invalid-encoding`, `render-failed`,
  `config-error`, `discovery-failed`). Acceptance: a test asserts the enum's case list exactly matches
  the list in `contracts/report.schema.json`, so adding a code requires updating the schema.
- [ ] T031 Wire `fetch` to fetch every onboarded repository and write a cache manifest of repository,
  revision, and documentation path. Acceptance: `fetch --manifest ...` exits 0 against the fixtures
  and produces the manifest; `fetch --project message-bus` fetches only that repository.

**Checkpoint Group 2**: sources for every fixture are fetched, versions detected, pages enumerated,
and every failure mode is reported with a reason code. Rendering is still absent.

## Phase 4: Rendering, page shell, and theme (Group 3, Story 1 and Story 3)

- [ ] T032 Create `src/Render/SiteConverter.php` extending `Html5Converter`, overriding
  `convertDocument()` to return article markup only and `renderStylesheet()` to return an empty
  string. Acceptance: a rendered document contains no `<html>`, `<head>`, `asciidoctor.css` link, or
  inline stylesheet; section markup and heading ids are unchanged from the parent converter, proven by
  a golden comparison.
- [ ] T033 Create `src/Render/AttributePolicy.php` supplying `stylesheet!`, `noembed`, `linkcss!`,
  `toc!`, `docinfo!`, `source-highlighter!` unless highlighting is enabled, and fixed empty values for
  the six clock attributes. Acceptance: rendering the same page twice with the clock changed in
  between produces identical bytes; the output contains no `docdate`, `doctime`, or `localdate` value.
- [ ] T034 Create `src/Render/TocExtractor.php` reading the document's sections at configured levels
  in document order and returning level, id, and title. Acceptance: a page with h2 and h3 sections
  yields them in order; an h1-only page yields an empty TOC; a section whose id is empty causes
  `toc-target-missing` rather than an empty anchor.
- [ ] T035 Create `src/Render/PageShell.php` and the templates under `resources/templates/` for the
  document shell described in the plan. Acceptance: a rendered page contains doctype, charset,
  viewport, title composed as page, project, site, canonical link, exactly two local stylesheets, skip
  link, header, sidebar, `main#main`, and footer with a source link; every interpolated value is
  escaped, proven by a fixture title containing `<` and `&`.
- [ ] T036 Create `src/Theme/ThemeResolver.php`, `src/Theme/AssetHasher.php`, and
  `resources/css/site.css`, `resources/css/print.css`. Acceptance: `site.css` contains the token
  blocks from the plan and no `@import` and no absolute URL; changing one byte of `site.css` changes
  its hashed name; the light-mode link and muted-text tokens clear 4.5:1 against their backgrounds in
  an automated contrast test.
- [ ] T037 Create `resources/js/theme.js` implementing the light/dark toggle with `localStorage`
  persistence, defaulting to the operating system preference and applying the stored choice before
  first paint via a small inline bootstrap. Acceptance: with no stored value the rendering follows
  `prefers-color-scheme`; a stored value overrides it; the toggle is operable by keyboard and exposes
  its state through `aria-pressed`.
- [ ] T038 Create `src/Render/LinkRewriter.php` rewriting internal `.adoc` links to their `.html`
  targets, prefixing every internal URL with the configured base path, and leaving external links
  untouched but marking them with `rel="noopener"`. Acceptance: a fixture page linking to
  `../v2/usage-examples.adoc` yields the expected published URL under base path `/` and under
  `/docs/`; no generated link starts with a hard-coded root slash when a base path is set.
- [ ] T039 Create `src/Render/PageRenderer.php` composing the above: prepare source, build attributes,
  call `Asciidoc::convertFile` with the converter option, wrap in the shell, and return the final HTML
  plus extracted TOC and search metadata. Acceptance: the versioned fixture's entry page renders with
  the expected title, TOC entries, and no clock or absolute path; a page with a broken include fails
  with `render-failed` and its source path.
- [ ] T040 Wire `build` to render every page of every version into the output directory using
  `ContentWriter` without incrementality yet, and print a per-project page count. Acceptance:
  `build --manifest ... --out build/site` produces the expected file tree for the fixture
  organization and exits 1 because the broken and Markdown-only fixtures fail.

**Checkpoint Group 3**: pages render with the site shell and shared theme, with deterministic
attributes and a working sidebar TOC. Navigation between projects is still absent.

## Phase 5: Navigation, redirects, and landing page (Group 4, Story 3)

- [ ] T041 Create `src/Site/UrlBuilder.php` producing project, version, and page URLs from the base
  path, and `src/Site/RedirectWriter.php` producing the redirect document from the plan. Acceptance:
  a redirect for `message-bus` with versions v1, v2, v10 targets v10; an unversioned project targets
  `latest`; the document contains meta refresh, canonical, `noindex`, and a plain link, and works with
  scripting disabled in a browser check.
- [ ] T042 Create `src/Site/NavigationBuilder.php` producing the project switcher, marking the current
  project and ordering by configured order then slug, and `VersionSelectorBuilder.php` producing the
  version list newest first with the current and latest versions marked. Acceptance: the switcher
  contains exactly the projects with status `ok` or `partial`; keyboard traversal reaches every item
  and the current item carries `aria-current="page"`.
- [ ] T043 Create `src/Site/OutdatedBannerBuilder.php` emitting a banner on non-latest versions that
  links to the same page in the latest version when it exists and to the latest entry page otherwise.
  Acceptance: a v1 page whose twin exists in v2 links to that twin; a v1 page absent from v2 links to
  the v2 entry page; a latest page has no banner.
- [ ] T044 Create `src/Site/LandingPageBuilder.php` emitting one card per publishable project with
  title, description, latest version, and link, falling back to the first paragraph of the latest
  entry page for the description. Acceptance: the fixture landing page has one card per healthy
  project in the documented order; a configured description wins over the extracted one; extracted
  descriptions are truncated on a word boundary.
- [ ] T045 Create `src/Site/StaticAssetWriter.php` writing `.nojekyll`, `CNAME` when a hostname is
  configured, `404.html` with the landing page link and the project list, and the hashed theme assets.
  Acceptance: the output root contains `.nojekyll`; `CNAME` contains exactly the configured hostname
  and a trailing newline; `404.html` links to the landing page and lists every publishable project.
- [ ] T046 Create `src/Site/SiteAssembler.php` orchestrating redirects, landing page, aggregate pages,
  and static assets after per-project rendering, rebuilding them whenever the publishable set or any
  project's metadata changes. Acceptance: adding a project to the manifest adds exactly one card, one
  switcher entry, and one redirect; removing it removes all three.
- [ ] T047 Add `resources/js/nav.js` for the project switcher and version selector behaviour, keeping
  both fully usable without it. Acceptance: with JavaScript disabled every switcher and selector link
  still navigates; with it enabled the control opens on click and closes on Escape.

**Checkpoint Group 4**: the site is browsable end to end: landing page, cards, switcher, version
selector, sidebar, outdated banner, redirects, and 404. Story 3 is complete.

## Phase 6: Search (Group 5, Story 4)

- [ ] T048 Create `src/Search/TextExtractor.php` stripping markup, collapsing whitespace, and
  truncating at a word boundary at the configured limit. Acceptance: a long fixture truncates without
  splitting a word; a page under the limit is unchanged; non-breaking spaces and tabs collapse to
  single spaces.
- [ ] T049 Create `src/Search/SearchIndexBuilder.php` emitting the per-project index exactly as
  specified in the plan and `contracts/search-index.schema.json`, with versions newest first, pages
  sorted by URL, and headings in document order. Acceptance: the emitted index validates against the
  schema; two builds produce identical bytes; every `url` in the index resolves to a file in the
  output tree.
- [ ] T050 Create `src/Search/SiteIndexBuilder.php` emitting the site-level index of project index
  locations. Acceptance: the site index lists exactly the publishable projects in the documented
  order and every referenced index file exists.
- [ ] T051 Create `resources/js/search.js` implementing loading of the site index plus the current
  project index, field-weighted scoring (title 3, heading 2, body 1), stable tie-break by URL,
  rendering of project, version, page title, and matching section, and scope controls for current
  version, all versions, and all projects. Acceptance: a manual test finds a term unique to one
  project's second version only after widening the scope; a term in a heading ranks above the same
  term in body text; equal scores order by URL.
- [ ] T052 Add query-state handling to `resources/js/search.js` reflecting the query in the URL and
  restoring results on load. Acceptance: reloading a results URL restores the query and the same
  ordered results; the back button restores the previous query.
- [ ] T053 Hide the search control when scripting is unavailable, using a `no-js`/`js` class
  strategy rather than a disabled input. Acceptance: with JavaScript disabled the control is absent
  from the accessibility tree, verified by a DOM assertion on the rendered HTML.
- [ ] T054 Add `tests/Search/SearchIndexSchemaTest.php` and a search-behaviour test that loads the
  built index through the same scoring code path used by the browser script, ported as a small
  JavaScript test harness invoked from PHPUnit. Acceptance: the harness reports zero failures and
  fails the suite when a fixture index is edited to break ranking.

**Checkpoint Group 5**: search works over the built site with no external service and no library.

## Phase 7: Failure isolation, incrementality, determinism, reporting (Group 6, Stories 2 and 5)

- [ ] T055 Create `src/Report/ProjectResult.php`, `BuildReport.php`, and `ReportWriter.php` with the
  schema version, one entry per onboarded repository, statuses `ok`/`partial`/`failed`/`skipped`,
  per-error slug, version, path, and reason code, plus totals. Acceptance: the report validates
  against `contracts/report.schema.json`; a build with one broken fixture produces `partial` for it,
  `ok` for healthy fixtures, `skipped` for the archived fixture, and exit code 1.
- [ ] T056 Isolate failures in `src/Build/BuildRunner.php` so a project or page failure is captured,
  recorded, and does not abort the run, while discovery and configuration failures still short
  circuit. Acceptance: the broken-include fixture publishes its other pages and is `partial`; the
  missing-entry fixture publishes nothing and is `failed`; healthy projects are unaffected in both
  cases; the archived fixture is `skipped` and does not change the exit code.
- [ ] T057 Add retry accounting to the fetch path and surface it in the report. Acceptance: a fixture
  server failing twice then succeeding yields status `ok` with an attempt count of 3; a server failing
  every time yields `fetch-failed` with the configured attempt count.
- [ ] T058 Create `src/Build/IncrementalCache.php` computing the documented cache key and
  `src/Build/BuildPlan.php` deciding which projects to render. Acceptance: an unchanged second build
  renders zero pages; changing one page in one project re-renders only that project; changing
  `site.css` re-renders every project; changing an unrelated project's override re-renders only that
  project and the aggregate pages.
- [ ] T059 Create `src/Build/ContentWriter.php` writing only changed bytes and removing stale output
  for a full build and for projects removed from discovery. Acceptance: an unchanged rebuild reports
  zero written files; removing a project from the manifest removes its directory on a full build and
  on an incremental build; no unrelated file is deleted, proven by a sentinel file placed in the
  output root.
- [ ] T060 Create `src/Build/DeterminismGuard.php` scanning output for clock-shaped strings, absolute
  paths, machine names, and non-deterministic identifiers, and failing the build with exit 4 when
  found. Acceptance: injecting a date into a template makes the guard fail the build; the clean build
  passes.
- [ ] T061 Add `tests/Determinism/BuildDeterminismTest.php` comparing recursive SHA-256 manifests of
  two consecutive full builds and of an incremental build against a fresh full build. Acceptance: all
  three comparisons report zero differences; the test fails if a template gains a timestamp.
- [ ] T062 Wire `validate` to report per repository whether it would build and, when not, the reason
  code and corrective action, writing no site output. Acceptance: against the fixtures it reports the
  healthy set as buildable and each broken fixture with its reason code and an actionable message; the
  output directory is unchanged, proven by a hash comparison.

**Checkpoint Group 6**: Stories 2 and 5 are complete. Builds are deterministic, incremental, and
report per-project outcomes with documented exit codes.

## Phase 8: Container stack, contract polish, onboarding docs (Group 7, Stories 6 and 7)

- [ ] T063 Create `docker/Dockerfile` from `php:8.4-cli` with `git`, `curl`, `mbstring`, and
  `json` extensions, a Composer stage installing `--no-dev`, and a non-root runtime user. Acceptance:
  the image builds; `docker run --rm <image> --version` prints the package version and exits 0.
- [ ] T064 Create `docker/entrypoint.sh` dispatching `render`, `watch`, and `serve` to the binary.
  Acceptance: each subcommand maps to the documented invocation; an unknown first argument exits 2
  with usage.
- [ ] T065 Create `docker-compose.yml` with `render`, `watch`, and `preview` services mounting the
  site directory and the output directory, with ports published only for `watch` and `preview`.
  Acceptance: `docker compose config` validates; `docker compose run --rm render` produces the output
  tree on the host and propagates a non-zero exit code when a fixture fails.
- [ ] T066 Implement `watch` with a filesystem poll (no extra binary) that debounces changes, rebuilds
  only affected projects, and serves the output on the configured port. Acceptance: editing one page
  updates the served page within five seconds; editing a theme asset rebuilds all projects; the
  process handles an interrupted build without exiting.
- [ ] T067 Implement `serve` as a static server that resolves `/slug/` to the redirect `index.html`,
  clean paths to `index.html`, and unknown paths to `404.html`. Acceptance: `/slug/` returns the
  redirect document; `/slug/v2/` returns the version entry page; an unknown path returns `404.html`
  with status 404.
- [ ] T068 Implement `clean` removing the cache and, with `--output`, the output directory, refusing
  to delete a path outside the config directory unless `--force` is given. Acceptance: a cache is
  removed; `--output` without `--force` on a path outside the project is refused with exit 2.
- [ ] T069 Add `tests/Container/ComposeSmokeTest.php` guarded by an environment flag so it is skipped
  when Docker is absent. Acceptance: with Docker available the test runs `render` and asserts the
  output tree and exit code; without it the suite still passes.
- [ ] T070 Write `quickstart.md` covering install, config, first build, watch, preview, and
  onboarding a new repository, with the exact commands and expected output. Acceptance: following the
  document from a clean checkout produces a built site; a second reader reproduces it without asking
  questions.
- [ ] T071 Write the onboarding section of `README.md`: the `doc-target` property, the required
  `docs/` layout with `docs/vN` or unversioned `docs/`, the entry page names, and the reason codes a
  maintainer may see. Acceptance: the document states every requirement in FR-010 to FR-014 and
  FR-074 to FR-076 with a concrete example tree.
- [ ] T072 Add `tests/Onboarding/OnboardingTest.php` asserting that adding a fixture repository to the
  manifest and setting the onboard value is sufficient to publish it, with no configuration change.
  Acceptance: the test starts from a config without the project, adds the manifest entry, rebuilds, and
  finds the project's pages, card, switcher entry, and search index.
- [ ] T073 Record the follow-on work explicitly in `README.md` and `plan.md`: Markdown ingestion behind
  the `SourcePreparer` seam, the standalone action repository, and the site repository's workflows.
  Acceptance: each item names what is deferred and where it will live.

**Checkpoint Group 7**: the container stack works, the CLI contract is documented and tested, and a
new repository can be onboarded by following the README.

## Phase 9: Documentation (Group 8)

- [ ] T074 Write the author guide at `docs/v1/authoring.adoc`, covering the `docs/vN` layout, the entry
  page contract (`index.adoc` then `README.adoc`), where images and assets belong, `include::` usage
  and its safe-mode limits, cross-project links, and what drives the outdated-version banner.
  Acceptance: a new author can add a page to an existing project by following the guide alone, and the
  guide references no source file, class, or function.
- [ ] T075 Write the operator guide at `docs/v1/operating.adoc`, covering the `doc-target` organization
  property with its actual configuration (boolean value, organization scope, `required` true,
  `default_value` `"false"`, `require_explicit_values` true, `values_editable_by` `org_actors`), the
  two fine-grained PATs with their exact permissions as recorded in `plan.md`, the site repository
  secrets, and the nightly, manual dispatch, and `repository_dispatch` triggers. Acceptance: an
  operator holding organization admin can onboard a repository end to end using only this document;
  the document states that onboarding requires an organization owner rather than a repository admin,
  that new repositories arrive with `doc-target` set to `false`, and every permission it names matches
  the credentials section of `plan.md` word for word.
- [ ] T076 Write the theme guide at `docs/v1/theming.adoc`, covering the token set, the per-project
  accent override, light and dark behaviour including the system preference and the persisted choice,
  and where to override without forking the package. Acceptance: changing a token or an accent in
  `site.json` changes the rendered site with no package edit, verified by a before-and-after build
  comparison.
- [ ] T077 Dogfood the pipeline: author this package's own documentation in AsciiDoc under its own
  `docs/v1` tree, and add a CI step that renders it through this package. Acceptance: the CI step
  fails the build when rendering fails, and a test asserts the rendered output contains the package
  name and at least one link to another rendered page.
- [ ] T078 Write the troubleshooting and reason code reference at `docs/v1/troubleshooting.adoc`: every
  reason code the report can emit, what causes it, and how to fix it. Acceptance: a test compares the
  reason codes in `contracts/report.schema.json` against the codes documented in the table and fails
  when one is missing.
- [ ] T079 Add a README pointer section linking the author, operator, theme, and troubleshooting guides
  plus `quickstart.md`. Acceptance: every guide is reachable from the README in one hop, verified by a
  link check over the README.

**Checkpoint Group 8**: the package documents itself, the four guides are reachable from the README,
and this repository's own documentation is built by its own pipeline in CI.

## Dependencies and ordering

- Group 1 must land first: every later group uses `Path`, `ProcessRunner`, `Filesystem`, `Json`,
  `SiteConfig`, and `ExitCode`.
- Group 2 depends on Group 1's config and support layer. `SourcePreparer` (T029) must exist before
  T039 so the future Markdown stage has one insertion point.
- Group 3 depends on T027 and T028 for its inputs and on T032 to T034 before T039 can compose a page.
- Group 4 depends on Group 3's page shell and on the publishable-project set from Group 6's statuses,
  which is why T042 reads status from `ProjectResult`; until T055 lands, the runner passes `ok` for
  every successfully rendered project.
- Group 5 depends on Group 4 only for the header search control's placement, not for its logic.
- Group 6 depends on Groups 2 to 5 being able to produce a complete site, because determinism and
  incrementality are measured over the whole output.
- Group 7 depends on Group 6 for exit codes and reports that the container and watch loop surface.
- Group 8 depends on Group 7: the guides document the CLI contract and onboarding behaviour that
  Group 7 finalises, and the dogfooding task needs the render pipeline and container stack in place.

## Parallel opportunities

- Within Group 1: T002, T003, T006, T007, and T011 are independent.
- Within Group 2: T020, T022, T025, and T030 are independent once T004 and T005 exist.
- Within Group 3: T032, T033, T034, T036, and T037 are independent; T035 depends on T036 for asset
  names; T039 depends on all of them.
- Within Group 4: T041, T042, T043, T044, and T045 are independent; T046 depends on all of them.
- Within Group 5: T048 and T051 are independent; T049 depends on T048.
- Within Group 6: T055 and T058 are independent; T059 depends on T058.
- Within Group 7: T063, T066, T067, and T068 are independent; T069 depends on T063 and T065.
- Within Group 8: T074, T075, T076, and T078 are independent; T077 depends on T074 for its source
  tree; T079 depends on T074, T075, T076, and T078 existing.

## Out of scope for this task list

- Markdown ingestion and any Markdown to AsciiDoc conversion.
- The standalone action repository (`webinertia/asciidoc-action`).
- The site repository's workflow YAML, including the nightly schedule, manual dispatch, and
  `repository_dispatch` handling.
- Migrating `message-bus` and `messagebus-event` Markdown documentation to AsciiDoc. Those are
  separate pull requests in those repositories and are prerequisites for their onboarding, not work in
  this repository.
- Creating the `doc-target` organization property, which is an operator setup step documented in
  T071.

## Counts

Tasks: 79 (T001 to T079). Groups: 8. User stories covered: 7 of 7.
