# Feature Specification: Multi-Repo Documentation Site Builder

**Feature Branch**: `tyrsson-speckit-multi-repo-docs-site`

**Created**: 2026-10-31

**Status**: Draft

**Input**: User description:

> We need to clone an additional repo to ~/github.com/tyrsson and the repo can be found in my
> personal account on github tyrsson/asciidoc-php. The highlevel goals here is to build multi-site
> support into our port of asciidoc-php so that we can rewrite our docs into asciidoc and pull them
> from the all repo's into a common github pages documentation site. We will also need to build a
> github action backed by our asciidoc-php for building each webware/* projects documentation.
> Basically, this project is going to be central place for planning and implementing the entire
> infrastructure for the webware fleets documentation site. We will be using message-bus and
> messagebus-event as the first two onboarded to this new documentation site. Onboarding a new site
> should be as simple as adding doc-target as a repo custom property just like we have done for
> targeting the CI workflow. asciidoc-php already works we just need to add multi-repo support and
> the ability to custom style the aggregate github pages site.

**Scope boundary**: this specification covers the multi-repository builder, the site configuration,
the shared theme, search, and the local container workflow. The standalone GitHub Action and the
site repository's workflows are follow-on specifications. The converter package
(`webware/asciidoc-php`) is out of scope and is not modified.

**Planned follow-on**: input format detection and a Markdown to AsciiDoc conversion stage are future
work, so an onboarded repository can eventually keep its existing Markdown and have it converted
during the build. Version 1 accepts AsciiDoc only and the behaviour in this specification stands
unchanged; the builder reserves a single source-preparation seam so the conversion stage can be added
without reworking discovery, version detection, URL layout, theming, search, or output writing.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Onboard a repository by property and publish its versioned docs (Priority: P1)

A maintainer finishes migrating one project's documentation to AsciiDoc and sets the organization
custom property `doc-target` to true on that repository. Nothing else changes: no edit to the site
repository, no entry added to a list, no code change. The next scheduled build discovers the
repository, reads its `docs/v1` and `docs/v2` trees from the default branch, renders every page with
the shared theme, and publishes them under the project's slug. The project root URL redirects to the
highest published version, and the older version stays reachable at its own URL.

**Why this priority**: this is the entire value proposition. Without discovery, version resolution,
rendering, and redirects there is no product, and every other story decorates this pipeline.

**Independent Test**: run a build against the checked-in fixture organization containing one
versioned repository (`docs/v1`, `docs/v2`, AsciiDoc entry page in each). Verify exit code 0, the
exact set of output paths, the redirect target of the project root, and the rendered text of one
page. No other story is required for this test to pass.

**Acceptance Scenarios**:

1. **Given** a repository named `message-bus` with `doc-target` set to true and `docs/v1` and
   `docs/v2` on its default branch, **When** a build runs, **Then** the output contains
   `message-bus/index.html`, `message-bus/v1/index.html`, `message-bus/v2/index.html`, and one HTML
   file per source page mirroring the source tree, and the build exits 0.
2. **Given** the same repository, **When** a reader requests `/message-bus/`, **Then** the served
   page resolves to `/message-bus/v2/` (the highest `vN`), and `/message-bus/v1/` still serves the
   older documentation.
3. **Given** a repository with `doc-target` unset, null, or false, **When** a build runs, **Then** no
   output path is produced for that repository and it does not appear in the site navigation.
4. **Given** a repository whose `docs/` directory has no `vN` subdirectory, **When** a build runs,
   **Then** its pages are published under `/slug/latest/...` and `/slug/` resolves to
   `/slug/latest/`.
5. **Given** a repository with version directories `v1`, `v2`, and `v10`, **When** a build runs,
   **Then** the project root resolves to `/slug/v10/` and the version list is ordered v10, v2, v1.
6. **Given** a source page at `docs/v2/api-reference/message-bus.adoc`, **When** it is rendered,
   **Then** the output path is `slug/v2/api-reference/message-bus.html`.

---

### User Story 2 - One broken repository does not break the site (Priority: P1)

The site aggregates many repositories owned by many people. A repository can be mid-migration,
contain a broken include, or be archived without warning. A build must publish every healthy project,
name the unhealthy ones, and exit with a code that tells CI the site is incomplete without discarding
the work that succeeded.

**Why this priority**: without isolation the site's availability depends on the least careful
repository in the fleet. This is the difference between a usable aggregation and a fragile one.

**Independent Test**: run a build against a fixture organization containing one healthy project, one
project whose entry page is missing, and one Markdown-only project. Verify that the healthy project's
pages exist, the other two are absent from navigation, both failures are named in the report with
reason codes, and the exit code is 1.

**Acceptance Scenarios**:

1. **Given** three onboarded repositories where one has a page whose `include::` target does not
   exist, **When** a build runs, **Then** the other two projects are fully published, the failed page
   is reported with its repository slug, version, and path, and the process exits 1.
2. **Given** an onboarded repository whose `docs/` tree contains only Markdown files, **When** a
   build runs, **Then** the project is reported as failed with reason `no-asciidoc-sources`, is
   excluded from navigation, and the process exits 1.
3. **Given** an onboarded repository that has been archived, **When** a build runs, **Then** it is
   reported as `skipped` with reason `archived` and does not by itself change the exit code.
4. **Given** a project that fails on one page but renders its other pages, **When** a build runs,
   **Then** its status is `partial`, its successfully rendered pages are published, and the exit code
   is 1.
5. **Given** discovery cannot authenticate or the configured property does not exist, **When** a
   build runs, **Then** the build publishes nothing, exits 3, and the message names the property and
   the organization.
6. **Given** a machine-readable report was requested, **When** the build finishes with failures,
   **Then** the report contains one entry per onboarded repository with status, versions, page count,
   and a non-empty error list for each non-`ok` entry.

---

### User Story 3 - Read the aggregate site as one coherent documentation site (Priority: P2)

A reader lands on the site root, sees every documented project as a card, opens one, switches to
another project from the header without returning to the landing page, sees the page's sections in a
left sidebar, and immediately notices when the version they are reading is not the current one. The
chrome and typography are consistent across all projects, with an optional per-project accent colour.

**Why this priority**: discovery and isolation make the site correct; navigation and theme make it
usable. It is separable: the pipeline of stories 1 and 2 can ship with unstyled pages.

**Independent Test**: build the fixture organization, open the landing page, follow a card, use the
project switcher, and confirm the sidebar links match the page's sections, the outdated banner
appears only on non-latest versions, and both light and dark renderings use the shared tokens.

**Acceptance Scenarios**:

1. **Given** three healthy projects, **When** the landing page is rendered, **Then** it contains one
   card per project in the configured order, each card showing the project title, a one-line
   description, the latest version, and a link to `/slug/`.
2. **Given** any documentation page, **When** it is rendered, **Then** the header contains the site
   name, a project switcher listing every healthy project with the current one marked, a version
   selector for the current project, and a light/dark toggle.
3. **Given** a page with sections at levels 2 and 3, **When** it is rendered, **Then** the left
   sidebar lists those sections in document order and every sidebar link resolves to an element id
   that exists in the page body.
4. **Given** a page from a version that is not the project's latest, **When** it is rendered, **Then**
   a banner states that a newer version exists and links to the same page in the latest version when
   that page exists there.
5. **Given** a project with a configured accent colour, **When** its pages are rendered, **Then** the
   accent is applied through a style token and the shared chrome, typography, and layout are
   unchanged.
6. **Given** any generated page, **When** its asset references are inspected, **Then** no stylesheet,
   script, or font is loaded from an external origin.
7. **Given** a reader with no JavaScript, **When** they browse the site, **Then** all navigation,
   version links, redirects, and content remain usable.

---

### User Story 4 - Search the documentation without external services (Priority: P2)

A reader types a term into the search box and gets ranked results from the current project and
version, with the option to widen the search to every version or every project. Results open the
matching page and anchor. The index is produced by the build and shipped with the site; nothing is
indexed at request time and no third-party service is contacted.

**Why this priority**: with many projects, browsing is not enough, but search is additive to a
working site and can ship after navigation.

**Independent Test**: build the fixture organization, open a page in one project, search for a term
that appears only in that project's second version, and confirm the current-version search does not
return it while the widened search does, with the correct page URL.

**Acceptance Scenarios**:

1. **Given** a successful build, **When** the output is inspected, **Then** each project has a JSON
   index covering all of its versions, and the site root has an index that lists each project and the
   location of its index.
2. **Given** a search for a unique term, **When** the query is entered, **Then** results are ranked
   with page titles ahead of heading matches ahead of body matches, and each result shows project,
   version, page title, and matching section.
3. **Given** a search from a page in project `message-bus` version `v2`, **When** the query is
   entered with the default scope, **Then** results come from `message-bus` `v2` only.
4. **Given** the same query with scope widened to all projects, **When** it is entered, **Then**
   results include matches from other projects and are labelled with their project and version.
5. **Given** a query is entered, **When** the reader reloads the page, **Then** the query is restored
   from the URL and the same results are shown.
6. **Given** a reader with no JavaScript, **When** the page is loaded, **Then** the search control is
   not presented as an interactive affordance.

---

### User Story 5 - Reproducible and incremental builds for CI (Priority: P2)

The site is published by CI on a schedule and on demand. Running the build twice without changing any
input must produce the same bytes, so the published branch only changes when documentation changes.
Running the build after one page changed must re-render only the affected project and still produce
output identical to a full build.

**Why this priority**: without determinism the published git history becomes noise; without
incrementality the nightly job becomes slow enough that people disable it. It is independent of
navigation and search because it constrains the writer, not the content.

**Independent Test**: run a full build twice into two directories and compare a recursive hash
manifest (zero differences). Then change one page, run an incremental build into the first
directory, and compare it against a fresh full build of the changed input (zero differences).

**Acceptance Scenarios**:

1. **Given** unchanged inputs, **When** a build runs twice, **Then** the two output trees have
   identical file lists and identical content hashes for every file.
2. **Given** generated HTML, **When** it is scanned, **Then** it contains no build date, build time,
   duration, machine name, or absolute local path.
3. **Given** a completed build, **When** one source page changes and an incremental build runs, **Then**
   only that project is re-rendered and the resulting tree is byte-identical to a full rebuild.
4. **Given** an incremental build where nothing changed, **When** it runs, **Then** no output file's
   bytes change and the run reports every project as `ok` with zero rendered pages.
5. **Given** a full rebuild is requested explicitly, **When** it runs, **Then** every project is
   re-rendered regardless of cache state and the output is identical to the incremental result.
6. **Given** a new project appears in discovery, **When** an incremental build runs, **Then** only the
   new project and the aggregate pages (landing page, site index) are rewritten.

---

### User Story 6 - Run the whole pipeline locally with containers (Priority: P3)

A contributor runs the build, watches their changes, and previews the result on their own machine
without installing PHP, Composer, or git locally. The container stack belongs to this repository, so
work here can never break the converter repository's own development stack.

**Why this priority**: it is the developer experience wrapper around stories 1 to 5. It is valuable
but it is not required for the site to publish in CI.

**Independent Test**: from a clean checkout with Docker available, start the one-shot render service
and confirm the output tree appears on the host; start the watch service, edit one page, and confirm
the served page reflects the change; start the preview service and confirm a built page and a
redirect both resolve.

**Acceptance Scenarios**:

1. **Given** a clean checkout and a site configuration, **When** the render service runs, **Then** the
   complete site is written to the configured output directory on the host and the exit code reflects
   the build result.
2. **Given** the watch service is running, **When** a page in one project changes, **Then** that
   project is rebuilt within five seconds and the change is visible at the documented local URL.
3. **Given** the preview service is running against a built site, **When** `/slug/` is requested,
   **Then** the response is the redirect for that project and the browser reaches the latest version.
4. **Given** no site configuration is present, **When** the render service runs, **Then** it exits
   with the configuration-error code and prints the expected configuration path.

---

### User Story 7 - Consume the builder from a standalone container action (Priority: P3)

The maintainer of a separate action repository needs a stable, documented contract: one command, a
small set of inputs, a machine-readable report, meaningful exit codes, and no interactive prompts or
host-specific assumptions. The same contract must let a third party use the published action against
their own organization.

**Why this priority**: it unlocks CI automation but depends on the pipeline existing and being
stable. The action's own specification is follow-on work; this story fixes only what the action
calls.

**Independent Test**: invoke the documented command in a clean container with only the configuration
path, output directory, report path, and token environment variable set, and verify the report is
written and the exit code matches the documented semantics.

**Acceptance Scenarios**:

1. **Given** only a configuration file, an output directory, a report path, and a token environment
   variable, **When** the build command runs, **Then** it completes without reading from standard
   input and without requiring any other environment variable.
2. **Given** the documented exit codes, **When** a build is fully successful, partially successful,
   misconfigured, or unable to authenticate, **Then** the exit code is 0, 1, 2, or 3 respectively.
3. **Given** a project filter is supplied, **When** the build runs, **Then** only the named projects
   are fetched and rendered while aggregate pages reflect the complete discovered set.
4. **Given** the token is absent, **When** the build runs in offline manifest mode, **Then** discovery
   reads the manifest instead of the organization and no network call is made.
5. **Given** a report was requested, **When** any build outcome occurs, **Then** the report is written
   with a declared schema version and contains no secret values.

---

### Edge Cases

- A repository name contains uppercase letters, dots, or underscores: the slug is lowercased and
  every run of non-alphanumeric characters becomes a single hyphen; two repositories mapping to the
  same slug is a configuration error (exit 2) naming both repositories.
- A configuration override sets a slug that collides with another project: configuration error,
  exit 2, before any rendering starts.
- A version directory is named `v0`, `v01`, or `V2`: only lowercase `v` followed by digits without a
  leading zero is a version directory; other directory names are ordinary content directories.
- Both version directories and loose `.adoc` files exist directly under `docs/`: the version
  directories win and the loose files are ignored, with a warning recorded in the report.
- A version directory has no entry page but has other pages: the project fails with reason
  `missing-entry` and names the expected file names.
- An entry page exists but a referenced image or include is outside the version directory: the page
  fails with the offending reference and the safe-mode rule that rejected it.
- A page contains a heading with no generated id: the page fails with reason `toc-target-missing`
  rather than publishing a sidebar link that goes nowhere.
- A source file is not valid UTF-8: the page fails with reason `invalid-encoding` and the file path.
- Two projects publish a page whose relative path differs only by letter case: both are published as
  distinct paths, and a warning is recorded because case-insensitive hosting would merge them.
- The site is hosted under a sub-path rather than a domain root: all generated links use the
  configured base path and no link starts with a hard-coded slash-rooted path.
- The organization has more repositories than one API page returns: discovery follows pagination and
  produces the same project set as a single-page response.
- A repository is deleted between discovery and fetch: that project fails with reason `fetch-failed`
  and the rest of the build continues.
- The same project is discovered twice through an override and through discovery: the override wins
  and one entry is produced.
- A page's plain text exceeds the search text limit: the index stores a truncated prefix at a word
  boundary and the page is still searchable by title and headings.
- The output directory already contains files from a previous project that is no longer onboarded:
  a full build removes stale output; an incremental build removes output belonging to projects it
  knows were removed.

## Requirements *(mandatory)*

### Discovery

- **FR-001**: The builder MUST obtain the project list by reading the organization custom property
  named in configuration (default name `doc-target`) and MUST treat only repositories whose value is
  the configured onboard value (default `true`) as onboarded.
- **FR-002**: Discovery MUST follow API pagination until exhausted and MUST produce the same project
  set regardless of page size.
- **FR-003**: Discovery MUST exclude repositories named in the configuration exclude list, and MUST
  exclude repositories reported as archived with status `skipped` and reason `archived`.
- **FR-004**: The URL slug MUST default to the lowercased repository name with every run of
  characters outside `[a-z0-9]` replaced by a single hyphen, and MUST be overridable per repository
  in configuration.
- **FR-005**: Two projects resolving to the same slug MUST be rejected as a configuration error before
  any fetch or render, naming both repositories.
- **FR-006**: Discovery MUST support an offline mode that reads the project list from a local manifest
  file instead of the organization API, for tests and air-gapped builds.
- **FR-007**: When discovery cannot authenticate, the property does not exist, or the API is
  unreachable, the build MUST publish nothing, MUST exit with code 3, and MUST name the organization
  and property in the error.
- **FR-008**: The discovery token MUST be read from the environment variable named in configuration
  and MUST never be written to disk, printed, or included in the report.

### Source Layout and Versions

- **FR-009**: Documentation MUST be read from the configured documentation directory (default `docs`)
  of each repository's default branch only.
- **FR-010**: Subdirectories of the documentation directory matching `^v[1-9][0-9]*$` MUST be treated
  as published versions; the version label MUST be the directory name.
- **FR-011**: When no version subdirectory exists, the documentation directory itself MUST be treated
  as a single version labelled `latest`.
- **FR-012**: When both version subdirectories and loose AsciiDoc files exist, the version
  subdirectories MUST win and the loose files MUST be ignored with a warning recorded in the report.
- **FR-013**: The entry page of each version MUST be resolved in order `index.adoc`, `README.adoc`;
  when neither exists the project MUST fail with reason `missing-entry` and MUST name the expected
  names.
- **FR-014**: Only files with the `.adoc` or `.asciidoc` extension MUST be rendered; when a version
  contains no renderable source the project MUST fail with reason `no-asciidoc-sources`.
- **FR-015**: Source files whose name begins with an underscore, and paths matching the configured
  exclude patterns, MUST NOT be published.
- **FR-016**: Nested directories MUST become URL path segments and the file extension MUST be replaced
  with `.html`.
- **FR-017**: The version labelled `latest` MUST NOT be treated as a numeric version for ordering or
  for the outdated-version banner.

### Rendering and Theme

- **FR-018**: Every page MUST be rendered by the pinned converter package through its existing public
  interface; this package MUST NOT modify, patch, or vendor the converter.
- **FR-019**: This package MUST own the complete page document (doctype, head, header, sidebar, main
  content, footer) and MUST NOT use the converter's own document shell or its embedded default
  stylesheet.
- **FR-020**: The converter MUST be invoked with a site-owned converter instance supplied through the
  converter option, and with document attributes that disable the converter's table of contents and
  any clock-derived attributes.
- **FR-021**: Every page MUST include a skip link, a header with site name, project switcher, version
  selector and theme toggle, a left sidebar table of contents, the main content, and a footer linking
  to the source file in its repository.
- **FR-022**: The sidebar table of contents MUST be built from the page's own sections at configured
  levels (default levels 2 and 3) in document order, and every entry MUST target an id present in the
  page body.
- **FR-023**: The shared theme MUST be a single stylesheet with no external font, script, or
  stylesheet references, and MUST support light and dark rendering with the operating system
  preference as the default and a persisted manual choice.
- **FR-024**: A per-project accent colour MUST be applied through a style token so that layout,
  typography, and component styling remain shared.
- **FR-025**: Text, links, and interactive controls MUST meet WCAG AA contrast (at least 4.5:1 for
  normal text) in both light and dark rendering.
- **FR-026**: Every page MUST declare a canonical URL derived from the configured site base URL and
  the page's published path.
- **FR-027**: The theme and its scripts MUST be copied into the output with a content-derived version
  suffix so a changed theme invalidates cached copies.

### URL Layout and Redirects

- **FR-028**: Each project MUST be published at `/slug/`; each version at `/slug/<version>/`; each
  page at its source-relative path below the version directory with an `.html` extension.
- **FR-029**: The version entry page MUST be published as `index.html` inside the version directory so
  that `/slug/<version>/` resolves without a file name.
- **FR-030**: `/slug/` MUST serve a redirect document that resolves to the project's highest version,
  where `vN` versions compare numerically and the `latest` label is used when the project has no
  numeric versions.
- **FR-031**: The redirect document MUST work without JavaScript and without server configuration: it
  MUST contain a meta refresh, a canonical link, a `noindex` directive, and a plain link to the
  target.
- **FR-032**: Every non-latest version MUST remain reachable at `/slug/<version>/` for as long as the
  version directory exists in the repository.
- **FR-033**: The site root MUST serve the landing page, and the output MUST contain a `.nojekyll`
  marker and, when configured, a `CNAME` file containing the configured host name.
- **FR-034**: All internal links MUST be built from the configured base path and MUST NOT assume the
  site is served from the domain root.

### Navigation

- **FR-035**: The landing page MUST list every project with status `ok` or `partial` as a card, in
  configured order then slug order, showing title, one-line description, latest version, and a link
  to `/slug/`.
- **FR-036**: The project description MUST come from configuration when supplied, otherwise from the
  first paragraph of the project's latest version entry page.
- **FR-037**: The project switcher MUST list every project with status `ok` or `partial`, mark the
  current project, and be usable with keyboard and without JavaScript.
- **FR-038**: The version selector MUST list the current project's versions newest first, mark the
  current version, and mark which version is the latest.
- **FR-039**: A page whose version is not the project's latest MUST show an outdated-version banner
  linking to the equivalent page in the latest version when that page exists, and to the latest
  version entry page otherwise.
- **FR-040**: The site MUST publish a `404.html` that offers the landing page and the project list,
  and MUST NOT rely on server-side 404 handling.

### Search

- **FR-041**: The build MUST emit one JSON search index per project covering all of that project's
  versions, and one site-level index listing every project with the location of its index.
- **FR-042**: Each indexed page entry MUST contain the published URL, page title, project, version,
  heading titles, anchor list, and a whitespace-normalised plain-text extract truncated at a
  configured limit on a word boundary.
- **FR-043**: The search interface MUST be implemented with no third-party library and MUST load index
  data only from the same origin.
- **FR-044**: Search results MUST be ranked with title matches ahead of heading matches ahead of body
  matches, and MUST display project, version, page title, and matching section.
- **FR-045**: The default search scope MUST be the current project and version, with an explicit
  control to widen the scope to all versions of the project and to all projects.
- **FR-046**: The current query MUST be reflected in the page URL and MUST restore the same results
  after a reload.
- **FR-047**: When scripting is unavailable, the search control MUST be hidden rather than presented
  as a non-functional input.
- **FR-048**: The index and the interface MUST be deterministic: identical sources MUST produce
  identical index bytes and stable result ordering for equal scores.

### Build Determinism and Incrementality

- **FR-049**: Two builds from identical inputs MUST produce identical output trees, file for file and
  byte for byte.
- **FR-050**: Generated output MUST NOT contain build dates, build times, durations, machine names,
  absolute local paths, or randomly generated identifiers.
- **FR-051**: Projects, versions, pages, table-of-contents entries, search entries, and report entries
  MUST be emitted in documented, stable orders.
- **FR-052**: An incremental build MUST re-render only projects whose inputs changed, where inputs are
  the configuration affecting that project, the repository revision, the content of every source file
  in the project's published versions, and the theme and script assets.
- **FR-053**: An incremental build MUST produce output identical to a full build of the same inputs.
- **FR-054**: A full rebuild MUST be requestable explicitly and MUST ignore all cached state.
- **FR-055**: A file MUST be written only when its bytes differ from the existing file.
- **FR-056**: A full build MUST remove output that no longer corresponds to any onboarded project or
  page, and an incremental build MUST remove output for projects that disappeared from discovery.

### Failure Handling and Reporting

- **FR-057**: Failures MUST be isolated per project and per page; a failure MUST NOT abort the build
  or prevent other projects from being published.
- **FR-058**: Each project MUST be assigned exactly one status: `ok`, `partial`, `failed`, or
  `skipped`.
- **FR-059**: Each failure MUST record the project slug, the version when applicable, the source path
  when applicable, and a machine-readable reason code from a documented closed set.
- **FR-060**: The build MUST exit 0 when every project is `ok` or `skipped`, 1 when any project is
  `partial` or `failed`, 2 on a configuration error, 3 when discovery cannot complete, and 4 on an
  unexpected internal error.
- **FR-061**: The build MUST support writing a machine-readable JSON report to a configured path
  outside the published output by default, containing a schema version, one entry per onboarded
  repository, and totals.
- **FR-062**: Human-readable progress and warnings MUST go to standard error and the final summary to
  standard output; a quiet mode MUST suppress everything except errors.
- **FR-063**: A fetch MUST be retried a configured number of times before the project is marked
  `fetch-failed`, and the report MUST record the attempt count.
- **FR-064**: A `validate` operation MUST report, for every onboarded repository, whether it would
  build and, when not, the reason code and the corrective action, without writing site output.

### Command-Line Contract

- **FR-065**: The builder MUST expose the operations `discover`, `fetch`, `build`, `watch`, `serve`,
  `validate`, and `clean`, with `build` performing discovery, fetch, render, aggregation, and report
  writing in one invocation.
- **FR-066**: The builder MUST accept a configuration path, an output directory, a cache directory, a
  concurrency limit, one or more project filters, a revision override, an offline manifest path, a
  report path, a full-rebuild switch, and quiet, verbose, timings, and colour controls.
- **FR-067**: All relative paths MUST be resolved against the directory containing the configuration
  file, so the command behaves identically from any working directory.
- **FR-068**: The command MUST NOT prompt for input, MUST NOT require a terminal, and MUST behave
  identically when standard input is closed.
- **FR-069**: The command-line surface, exit codes, and report schema MUST be documented as an
  external contract and versioned; breaking changes MUST require a major version bump.

### Local Container Runtime

- **FR-070**: The repository MUST provide a container stack with a one-shot render service, a watch
  service that rebuilds on change and serves the result, and a static preview service, and that stack
  MUST be independent of the converter repository's stack.
- **FR-071**: The container image MUST install the converter and this package through Composer and
  MUST include only the runtime prerequisites of PHP with the required extensions and `git`.
- **FR-072**: The watch service MUST rebuild only the projects whose sources changed and MUST serve
  the site at a documented local URL.
- **FR-073**: The preview service MUST serve the built tree such that `/slug/` reaches the project
  redirect and clean URLs resolve to their `index.html`.

### Onboarding

- **FR-074**: Onboarding a repository MUST require only setting the organization custom property to
  the onboard value and providing a documentation tree that satisfies FR-010 to FR-014; no change to
  this package or to the site configuration MAY be required.
- **FR-075**: The organization custom property MUST be created as a boolean-valued property editable
  by organization and repository actors, mirroring the existing CI targeting property, and the
  builder MUST NOT create or modify organization properties.
- **FR-076**: The site configuration MUST allow per-project overrides for slug, title, description,
  accent colour, and display order, and MUST require no override for a project to appear.

### Key Entities

- **Site Configuration**: the single configuration document that supplies organization, property
  name, onboard value, slug overrides, theme, base path, output and cache locations, build limits,
  search limits, and host name. It is the only place where site-specific values live.
- **Project**: one onboarded repository. Attributes: repository name, slug, title, description,
  accent colour, display order, source revision, status, versions, page count, errors.
- **Doc Version**: one published version of a project. Attributes: label (`vN` or `latest`), source
  directory, entry page, ordered page list, latest flag.
- **Doc Page**: one rendered source file. Attributes: source path, published path, URL, title,
  section list, status, error.
- **Section**: one heading in a page. Attributes: level, id, title, anchor URL.
- **Theme**: the shared stylesheet, scripts, and token set. Attributes: asset names, content hashes,
  default accent, light and dark token values.
- **Search Index**: per-project document of versions, pages, headings, anchors, and text extracts,
  plus the site-level index that points to each project index.
- **Build Report**: the machine-readable outcome. Attributes: schema version, project entries with
  status and errors, totals, exit code.
- **Onboarding Property**: the organization custom property that selects repositories, with its name,
  value type, and onboard value supplied by configuration.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A repository becomes part of the published site after only setting the custom property
  and adding a conforming documentation tree; verified by a fixture run with zero configuration edits
  between the two builds.
- **SC-002**: A full build of 10 projects with 2 versions and 12 pages each completes in under 120
  seconds on CI-class hardware, and an incremental build after a one-page change completes in under 5
  seconds.
- **SC-003**: Two consecutive builds of unchanged inputs produce zero differing files, and an
  incremental build produces zero differences against a full build of the same inputs.
- **SC-004**: With 1 failing project among 10, the published site contains all 9 healthy projects, the
  failure is named in the report with a reason code, and the exit code is 1.
- **SC-005**: Every published page is reachable from the landing page in at most 2 navigation steps,
  and every `/slug/` redirect resolves to the highest published version.
- **SC-006**: A reader can find a unique term through search in under 1 second on a 2,000-page index,
  with the correct page and section in the first three results.
- **SC-007**: Generated pages make zero external network requests, verified by scanning all output for
  absolute external asset references.
- **SC-008**: All text and links in both light and dark rendering meet at least 4.5:1 contrast, verified
  by an automated contrast check over the theme tokens.
- **SC-009**: A page that fails to render never appears in navigation and never leaves a broken link;
  every navigation link in a built site resolves to an existing file.
- **SC-010**: Onboarding documentation for a new project requires no more than the property toggle and
  the documented directory layout; a maintainer following the documentation succeeds on the first
  attempt, verified by a walkthrough in the quickstart.

## Assumptions

- Documentation in onboarded repositories is AsciiDoc after migration. Markdown is not rendered; a
  Markdown-only project is reported as failed, excluded from the site, and makes the build exit 1.
- Repositories are public, so fetching documentation requires no credentials; only property
  enumeration requires a token.
- The documentation tree is read from each repository's default branch; version history is expressed
  as `docs/vN` directories rather than branches.
- The site is served by GitHub Pages from a `gh-pages` branch with a custom domain at the domain root;
  a sub-path deployment is supported by configuration but is not the primary target.
- `git` is available wherever the builder runs, including the action image and the local container
  stack.
- The existing organization CI property (`ci-target`) establishes the pattern for the new
  documentation property: organization scope, boolean value, editable by organization and repository
  actors.
- The first two onboarded projects are `message-bus` and `messagebus-event`, both with `docs/v1` and
  `docs/v2` Markdown trees that are migrated to AsciiDoc before or during onboarding.
- Search runs entirely in the browser over a pre-built index; no server-side search, no external
  service, and no analytics.
- The converter package keeps its current public interface (facade, converter option, attributes,
  public node classes) for the lifetime of this feature.
