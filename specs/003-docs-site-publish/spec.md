# Feature Specification: Docs Site Publish Workflows

**Feature Branch**: `docs/003-docs-site-publish`

**Created**: 2026-11-01

**Status**: Draft

**Input**: User description:

> Author a new SpecKit specification for the docs site's publish workflows. Planning only: produce
> the spec artifacts. Do not write any workflow YAML, do not implement anything. In scope: the
> workflow files that live in `webinertia/docs.webinertia.net` (the nightly schedule, the manual
> dispatch, and the `repository_dispatch` receiver), the secrets they consume and where those
> secrets are set, concurrency and locking, how a published tree replaces the existing `gh-pages`
> content, failure handling and operator visibility, the determinism requirement, and the
> consumer-side notification snippet and how it interacts with `doc-target` discovery.

**Deferral origin**: this specification is the site half of the deployment deferral recorded in
`specs/001-multi-repo-docs-site/`. That specification's scope boundary states that "The standalone
GitHub Action and the site repository's workflows are follow-on specifications", and its `plan.md`
decision 7 states that the site repository's nightly, dispatch, and `repository_dispatch` workflows
are specified in the follow-on action and site specifications. This is that specification for the
site repository.

**Settled inputs**: this specification builds on decisions already taken in
`specs/001-multi-repo-docs-site/` and does not reopen them. Discovery reads the organization custom
property `doc-target`; the two credentials are deliberately separate; `gh-pages` is pushed with the
site repository's own `GITHUB_TOKEN`; the build is deterministic; and the exit codes are 0 complete,
1 published but degraded, 2 config error with nothing published, 3 discovery error with nothing
published, and 4 internal error. Where this specification adds to those decisions it says so, and
the additions are recorded in `plan.md` under "Confirmed decisions".

## Scope

**In scope:**

- The workflow files that live in `webinertia/docs.webinertia.net`: the nightly schedule, the manual
  dispatch, and the `repository_dispatch` receiver.
- The credentials those workflows consume, where each one is set, and which component may hold it.
- Concurrency and locking: what happens when a nightly run and a dispatch overlap, and what happens
  when a consumer dispatches while a run is in progress.
- How a published tree replaces the existing `gh-pages` content: the branch ends up containing the
  builder's output plus `CNAME` and `.nojekyll` and nothing else, whatever it held before.
- Failure handling and operator visibility: how exit codes 0, 1, 2, 3, and 4 surface, and what a
  failed run leaves on `gh-pages`.
- The determinism requirement at the deployment layer: how the workflow avoids empty or churn
  commits.
- The consumer-side notification snippet and its relationship to `doc-target` discovery.
- The site repository's own scaffolding that the workflows depend on: the site configuration, the
  pinned builder dependency, and the repository ignore rules.

**Out of scope (explicit):**

- The standalone reusable GitHub Action for consumers, which lives in `webinertia/asciidoc-action`.
  That is a separate follow-on specification.
- The builder CLI and the site configuration format, which belong to
  `specs/001-multi-repo-docs-site/`. This specification consumes that contract; it does not change
  it.
- Markdown ingestion.
- Any rewrite of `specs/001-multi-repo-docs-site/` or `specs/002-webware-alignment/`. Merged spec
  plans are a record and are not reworded, renumbered, reordered, or reticked.
- Building, rendering, theming, search, navigation, and URL layout, all of which the builder owns.
- Creating organization properties, GitHub Apps, secrets, or variables as an automated step. Those
  are operator actions, documented here and performed by a human.

## Starting State

The site repository `webinertia/docs.webinertia.net` is public, defaults to `master`, and carries
only `README.md` there. It has no workflows. `gh-pages` holds exactly two blobs, `CNAME` (19 bytes,
`docs.webinertia.net`) and `.nojekyll` (0 bytes), and nothing else; Pages reports `status: built` for
that commit. Pages is configured `build_type: legacy`, source `gh-pages` at `/`,
`https_enforced: true`, `custom_404: false`, with `cname: docs.webinertia.net`. The repository already
holds a repository secret named `DOC_DISCOVERY_TOKEN`. The site repository has no ruleset protecting
`gh-pages`.

The branch reached that state out of band, at the owner's request, in commit `36dfe12`, which deleted
the retired projects and the Jekyll-era configuration and added `.nojekyll`. Because the branch is now
clean, the site has no landing page: `http://docs.webinertia.net/` and
`http://docs.webinertia.net/aurora/` both return 404.

No workflow has ever written to `gh-pages`. This specification is the first thing that will.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The site refreshes itself every night (Priority: P1)

An organization owner sets `doc-target` to true on a repository and migrates its documentation to
AsciiDoc. Nobody edits the site repository, nobody adds a secret to the new repository, and nobody
runs a command. Overnight the site rebuilds, discovers the repository, and the new project is
published with its landing page, versions, and search index.

**Why this priority**: the nightly schedule is the only trigger that requires no participation from
a consumer repository. It is what makes onboarding the single property toggle that spec 001
promised. Without it the site is only as current as the last person who remembered to dispatch.

**Independent Test**: on a branch of the site repository, run the publish workflow by manual
dispatch with discovery pointed at a fixture manifest, and verify the published `gh-pages` tree
matches the built output, `CNAME` and `.nojekyll` are present, and the run summary names the
published commit.

**Acceptance Scenarios**:

1. **Given** a repository with `doc-target` set to true and a conforming `docs/` tree, **When** the
   nightly schedule fires, **Then** the repository's pages appear on the site without any change to
   the site repository or to the consumer repository's settings.
2. **Given** the nightly schedule fires and nothing in the organization changed since the last
   publish, **When** the run completes, **Then** no commit is created, nothing is pushed, and the run
   reports a successful no-op.
3. **Given** the nightly schedule fires and one project's documentation changed, **When** the run
   completes, **Then** exactly one commit is added to `gh-pages` and its message names the changed
   project slugs.
4. **Given** a repository whose `doc-target` is false, **When** the nightly schedule fires, **Then**
   that repository contributes nothing to the published tree and its slug does not appear in the
   site navigation or search index.

---

### User Story 2 - A documentation change is live without waiting for the schedule (Priority: P1)

A maintainer merges a documentation change and wants it on the public site now, not at 03:17 the
next morning. Their workflow notifies the site repository, and the site rebuilds within minutes. If
the notification fails, the site still catches up overnight, so the notification is an accelerator
and never a dependency.

**Why this priority**: a documentation site that lags by a day trains people to distrust it. This is
the difference between a site that is read and a site that is checked. It is separable from the
nightly schedule because the schedule alone satisfies story 1.

**Independent Test**: send a `repository_dispatch` request with the documented event type and a
payload naming one repository, then verify a publish run starts, the named repository is reported in
the run summary as present in the discovered set, and the resulting tree matches a full rebuild.

**Acceptance Scenarios**:

1. **Given** a repository that has merged a documentation change, **When** its workflow sends the
   documented dispatch event, **Then** a publish run starts and the change is published.
2. **Given** a dispatch whose payload names a repository that is not in the discovered set, **When**
   the run completes, **Then** the run summary records the dispatching repository as not discovered
   and the run publishes only the discovered set.
3. **Given** a dispatch payload that names a repository which *is* discovered, **When** the run
   completes, **Then** the published tree is identical to the tree a nightly run would produce from
   the same inputs.
4. **Given** the dispatch request fails, **When** the consumer's workflow finishes, **Then** the
   consumer's own workflow is not failed by the notification failure.
5. **Given** a maintainer wants a rebuild with no source change, **When** they start a manual
   dispatch, **Then** the run rebuilds the site and either publishes a commit or reports a no-op,
   according to whether the tree actually changed.

---

### User Story 3 - The published branch changes only when documentation changes (Priority: P1)

An operator reviews `gh-pages` history and sees one commit per real documentation change. There is
no commit for a run that found nothing new, no commit that rewrites the whole tree because a
timestamp moved, and no commit whose diff is a handful of files whose only difference is ordering.

**Why this priority**: determinism is already a builder requirement, but it only produces a readable
published history if the deployment layer declines to commit when nothing changed. A workflow that
commits unconditionally throws away the determinism the builder guarantees.

**Independent Test**: run the publish workflow twice in a row with no source change between them,
and verify the second run creates no commit and leaves `gh-pages` at the same SHA. Then change one
page, run again, and verify the commit diff touches only that project's output plus the aggregate
pages the builder rewrites.

**Acceptance Scenarios**:

1. **Given** an unchanged organization, **When** two publishes run back to back, **Then** the second
   creates no commit and `gh-pages` still points at the first run's commit.
2. **Given** one changed page, **When** a publish runs, **Then** the commit diff contains no file
   whose content is unchanged.
3. **Given** any publish, **When** the committed tree is inspected, **Then** it contains no build
   date, build time, duration, run id, run number, or runner name.
4. **Given** an operator forces a republish with no source change, **When** the run completes,
   **Then** a commit is created and the run summary records that the commit came from a forced
   republish rather than from a content change.

---

### User Story 4 - Overlapping runs queue instead of racing (Priority: P2)

A consumer dispatches while the nightly run is halfway through a build. The dispatch waits. When the
nightly run finishes and pushes, the dispatch run starts, sees the pushed tree, and either publishes
the newer content or correctly reports that nothing changed. No run ever fails because another run
was pushing to the same branch at the same time.

**Why this priority**: a race on `gh-pages` produces rejected pushes, half-published trees, and
confusing red runs. It is separable because a single nightly run with no dispatches never overlaps
with anything.

**Independent Test**: start a publish and, while it is running, send a dispatch. Verify the dispatch
run is queued rather than started, that the first run completes and pushes, and that the second run
then completes without a rejected push.

**Acceptance Scenarios**:

1. **Given** a publish run in progress, **When** another trigger fires, **Then** the second run waits
   and does not start until the first finishes.
2. **Given** a run is waiting, **When** a third trigger fires, **Then** at most one run remains
   waiting and the superseded one is recorded as such by the platform.
3. **Given** a waiting run is superseded, **When** the surviving run completes, **Then** the
   superseded run's source change is still published, because every run rebuilds the complete
   discovered set.
4. **Given** two runs execute one after another, **When** the second finishes, **Then** the second
   run's push was accepted on its first attempt with no force and no rebase.

---

### User Story 5 - A failed run is visible and leaves a known tree (Priority: P2)

A discovery credential expires, or the site configuration is malformed, or one project's
documentation is broken. The operator opens the Actions tab, reads one summary that states what
happened and which exit code the builder returned, and knows exactly what is live on the site right
now: either the previous tree, untouched, or a newly published tree with named gaps.

**Why this priority**: an unattended nightly job that fails silently is worse than no job at all.
It is separable because a happy-path publish never exercises any of it.

**Independent Test**: force each exit code in turn, and verify for each one whether `gh-pages` moved,
what the run summary says, and whether the report artifact exists.

**Acceptance Scenarios**:

1. **Given** a build that exits 0, **When** the run completes, **Then** the tree is published if it
   changed and the run succeeds.
2. **Given** a build that exits 1, **When** the run completes, **Then** the built tree is published,
   the run is marked failed, and the summary names every project that was not `ok` with its reason
   code.
3. **Given** a build that exits 2 or 3, **When** the run completes, **Then** `gh-pages` still points
   at its previous commit, the run is marked failed, and the summary names the exit code and the
   first error.
4. **Given** a build that exits 4, **When** the run completes, **Then** `gh-pages` still points at
   its previous commit and the run is marked failed.
5. **Given** any run that produced a report, **When** the run completes, **Then** the report is
   attached to the run as an artifact and the summary states the published commit SHA or states that
   nothing was published.
6. **Given** the discovery credential is missing, **When** a publish runs, **Then** the run fails
   before any build starts and names the missing secret and the organization property.
7. **Given** a manual dispatch started from a branch other than the default branch, **When** the run
   completes, **Then** the build, the guards, and the report all run, `gh-pages` is untouched, and the
   summary states that the run was a dry run.

---

### User Story 6 - Notification is optional and discovery is mandatory (Priority: P3)

A maintainer reads the onboarding documentation and learns that setting `doc-target` is the whole
job. The notification snippet is presented as an optional latency improvement with its own
prerequisites, not as a step they must complete. A maintainer who adds the snippet without setting
the property gets a clear signal that their repository is not onboarded, rather than silence.

**Why this priority**: it is documentation and diagnostics around stories 1 and 2. It changes no
publishing behaviour but it prevents the most likely onboarding mistake.

**Independent Test**: read the onboarding section as a maintainer with no prior context, and verify
that following only the property step produces a published project, and that the snippet section
states its optional status and its prerequisites.

**Acceptance Scenarios**:

1. **Given** the onboarding documentation, **When** a maintainer follows only the property step,
   **Then** their repository is published at the next scheduled run.
2. **Given** a repository that dispatches but whose `doc-target` is false, **When** the publish run
   completes, **Then** the run summary records the dispatch and states that the repository was not
   discovered.
3. **Given** the notification snippet, **When** it is read in isolation, **Then** it states the
   event type, the payload fields, the credential it needs, and that failing to send it does not
   prevent publishing.

---

### Edge Cases

- The built tree differs from the published tree only by `CNAME` because an operator changed
  `site.hostname`: the run fails before pushing, naming both values, so a custom-domain change is a
  deliberate two-step action rather than a side effect of a routine publish.
- The built output contains no `CNAME` because `site.hostname` is unset: the run fails before
  pushing, because deleting `CNAME` from `gh-pages` clears the custom domain in Pages settings.
- The built output contains no `.nojekyll`: the run fails before pushing, because with
  `build_type: legacy` Jekyll runs unconditionally on the branch and would drop paths beginning with
  an underscore.
- `gh-pages` does not exist, or exists empty: the run creates it from the built tree and does not
  treat the absence as an error.
- A dispatch arrives with an empty payload, or with a payload that names a repository not in the
  organization: the run proceeds over the discovered set and records the payload as unusable.
- A dispatch arrives with a payload field the receiver does not recognise: the field is ignored and
  the run proceeds.
- The discovery secret is present but expired: the builder exits 3, the run fails, and `gh-pages` is
  untouched. The summary names the credential and the organization property so the operator knows
  which of the two credentials to rotate.
- A run is cancelled by an operator mid-build: nothing is pushed, because the push is the last step
  and it is not reachable from a cancelled job.
- A run is cancelled after the push and before the summary is written: the site is already correct
  and only the reporting is lost.
- Two runs are queued and the older is superseded: the surviving run rebuilds everything, so no
  content is lost.
- The published tree is identical but the run is a forced republish: a commit is created whose tree
  is unchanged, and the summary says so.
- A consumer's notification step fails because the App installation was removed: the consumer's
  workflow is not failed, and the nightly run still publishes their documentation.
- A consumer repository is renamed: discovery publishes it under its new slug, the old slug's output
  is removed by the wholesale replacement, and the old URL returns 404.
- The schedule is disabled by GitHub after a long quiet period: a manual dispatch publishes
  correctly, and the operator documentation names the condition.

## Requirements *(mandatory)*

### Triggers and Schedule

- **FR-001**: The site repository MUST host the publish workflows, and all three triggers (nightly
  schedule, manual dispatch, and `repository_dispatch`) MUST converge on one publish job definition,
  so exactly one code path builds and publishes.
- **FR-002**: The schedule MUST run nightly at a fixed UTC cron expression recorded in the workflow,
  and that expression MUST NOT be derived from any per-run input or from the repository contents.
- **FR-003**: Manual dispatch MUST accept only a free-text run note and a boolean forced-republish
  flag. It MUST NOT accept an input that selects, restricts, or extends the set of projects built.
- **FR-004**: The `repository_dispatch` trigger MUST listen on exactly one event type, and that event
  type MUST be recorded in the dispatch contract under `contracts/`.
- **FR-005**: No trigger MAY narrow the set of projects built. Every run MUST build the complete
  discovered set, and a trigger MAY contribute only advisory metadata.

### Credentials and Secrets

- **FR-006**: The discovery credential MUST be the site repository secret named by
  `organization.tokenEnvVar` in the site configuration. The workflow MUST expose it to the build
  step under that exact name and MUST fail before building when it is absent.
- **FR-007**: The trigger credential MUST be a GitHub App installed on `webinertia/docs.webinertia.net`
  only, carrying the repository permission Contents, Read and write, and no organization
  permissions of any kind.
- **FR-008**: The trigger credential MUST NOT be the discovery credential. A component repository
  that can dispatch MUST NOT thereby be able to enumerate the organization.
- **FR-009**: Consumer repositories MUST obtain short-lived installation tokens from the App at run
  time. A long-lived dispatch credential MUST NOT be stored in a consumer repository, and MUST NOT
  be duplicated per repository.
- **FR-010**: The App's private key MUST NOT be available to the publish workflow. The publish
  workflow MUST NOT use the trigger credential for any step.
- **FR-011**: The `gh-pages` push MUST use the site repository's own `GITHUB_TOKEN` with the
  `contents: write` permission, and that permission MUST be requested at the narrowest scope the
  workflow can express.
- **FR-012**: No credential value MAY be printed, written to the report, written to an artifact, or
  included in a commit message.

### Concurrency and Locking

- **FR-013**: All three triggers MUST share one concurrency group, and the workflow MUST NOT cancel
  an in-progress publish.
- **FR-014**: A trigger that fires while a publish is running MUST queue rather than start, and MUST
  NOT interrupt the running publish.
- **FR-015**: When more than one run is queued, at most one MAY remain queued. A superseded queued
  run MUST be safe to drop, because every run rebuilds the complete discovered set.
- **FR-016**: The workflow MUST NOT depend on any cross-run state (a cache, a lock file, an artifact,
  or a commit) for correctness. Two runs that execute in either order MUST produce the same
  published tree.

### Published Tree

- **FR-017**: The published tree MUST be exactly the builder's output directory and nothing else.
  Every path present in `gh-pages` and absent from that output MUST be removed.
- **FR-018**: `CNAME` MUST be present in every published tree and MUST contain the configured host
  name. Its absence from the built output MUST fail the run before anything is pushed. A value that
  differs from the current `gh-pages` `CNAME` MUST also fail the run before anything is pushed, so a
  custom-domain change is never an incidental effect of a routine publish.
- **FR-019**: `.nojekyll` MUST be present in every published tree. Its absence from the built output
  MUST fail the run before anything is pushed.
- **FR-020**: `_config.yml` MUST NOT be preserved. It belongs to the retired Jekyll-era content and
  is removed by the wholesale replacement.
- **FR-021**: Every path in the previous `gh-pages` tree that the builder's output does not contain
  MUST be absent from the published tree and MUST return 404 afterwards. No redirect, alias, or
  compatibility page is provided for a removed path, and the builder's `404.html` serves the miss.
  This MUST hold whatever the branch previously held. It MUST NOT be verified against a fixed list of
  historical paths, because a branch that is already clean cannot distinguish a working replacement
  from a publish that changes nothing.
- **FR-022**: The publish MUST be atomic at the commit level: exactly one commit is created per
  publishing run, and a tree that was only partially written MUST never be pushed.
- **FR-023**: Publishing MUST NOT force-push and MUST NOT rewrite, truncate, or delete the `gh-pages`
  branch or its history.
- **FR-024**: The workflow MUST NOT change Pages configuration. `gh-pages` MUST remain the Pages
  source with `build_type: legacy`.
- **FR-025**: The published tree MUST NOT contain the build report, the builder cache, the
  repository checkout, or any path outside the builder's declared output directory.

### Determinism and Change Detection

- **FR-026**: Two runs over identical inputs MUST produce identical output bytes. The workflow MUST
  compare the built tree with the current published tree by content before deciding to commit.
- **FR-027**: When the built tree is identical to the published tree, the run MUST NOT create a
  commit and MUST NOT push, and it MUST report a successful no-op.
- **FR-028**: The comparison MUST cover the complete tree, including `CNAME` and `.nojekyll`, and
  MUST compare content rather than timestamps, file modes, or paths alone.
- **FR-029**: The commit message MUST be derived from the trigger and from the project slugs whose
  content changed. It MUST NOT embed a run id, run number, timestamp, runner name, or any value that
  varies between runs that produce the same tree.
- **FR-030**: A forced republish requested by manual dispatch MAY create a commit when the tree is
  unchanged, and when it does the run summary MUST state that the commit came from a forced
  republish.

### Failure Handling and Operator Visibility

- **FR-031**: Exit code 0 MUST publish the tree when it changed and MUST leave the run successful.
- **FR-032**: Exit code 1 MUST publish the tree, and the run MUST then be marked failed so the
  degradation is visible. The run summary MUST name every project that was not `ok`, with its status
  and its reason codes.
- **FR-033**: Exit codes 2 and 3 MUST publish nothing, MUST leave `gh-pages` at its previous commit,
  and MUST fail the run. The summary MUST name the exit code and the first error reported by the
  builder.
- **FR-034**: Exit code 4 MUST publish nothing, MUST leave `gh-pages` at its previous commit, and
  MUST fail the run.
- **FR-035**: Any non-zero exit MUST leave `gh-pages` exactly as it was before the run. A partially
  built or partially copied tree MUST NOT be pushed under any exit code.
- **FR-036**: A run that produced a report MUST attach that report to the run as an artifact, and
  the artifact MUST be retained for a documented period.
- **FR-037**: The run summary MUST state, for every run, the trigger, the builder exit code, the
  totals by project status, the projects that were not `ok`, and either the published commit SHA or
  an explicit statement that nothing was published.
- **FR-038**: A missing or unusable discovery credential MUST fail the run before any build starts,
  and the summary MUST name the secret and the organization property.
- **FR-039**: The report path MUST resolve outside the builder's output directory, so the report is
  never published, and the output directory MUST resolve outside the repository checkout, so the
  checkout stays clean and the publish step alone controls the tree.

### Consumer Notification

- **FR-040**: Onboarding MUST remain the `doc-target` property alone. The notification snippet MUST
  NOT be a prerequisite for a repository to appear on the site.
- **FR-041**: The notification snippet MUST be documented as optional, and its only effect MUST be
  reduced latency between a documentation change and its publication.
- **FR-042**: The dispatch payload MUST NOT select, restrict, or extend the set of projects built.
  It is advisory metadata used for correlation and reporting only.
- **FR-043**: The receiver MUST record in the run summary the dispatching repository named in the
  payload, when present, and MUST state whether that repository appeared in the discovered set. A
  dispatch from a repository whose `doc-target` is false MUST be recorded as a no-op with that
  reason.
- **FR-044**: The snippet MUST tolerate a failed notification. A notification failure MUST NOT fail
  the consumer's own workflow.
- **FR-045**: The snippet MUST NOT require a long-lived credential in the consumer repository, and
  MUST obtain its credential as specified in FR-009.

### Site Repository Scaffolding

- **FR-046**: The site repository MUST pin the builder in a committed dependency manifest and lock
  file, and the workflow MUST install from that lock file. The workflow MUST NOT install a floating
  version constraint.
- **FR-047**: The workflow MUST provide the builder's declared runtime: the supported PHP versions,
  the required PHP extensions, and `git`.
- **FR-048**: The site configuration MUST live at the root of the site repository, and the discovery
  credential's environment variable name in that configuration MUST be `DOC_DISCOVERY_TOKEN`,
  matching the secret already present in the repository.
- **FR-049**: The site repository MUST ignore the builder's cache directory and its output directory,
  so a local run never pollutes the repository that the workflow publishes from.

### Run Safety

- **FR-050**: A run MUST push only when its ref is the repository default branch. A run started from
  any other ref MUST build, run every guard, and produce the report, and MUST NOT push, and its
  summary MUST state that it was a dry run.
- **FR-051**: A dry run MUST exercise the same guards and the same failure handling as a publishing
  run, so that testing the workflow from a branch proves what a publish would do rather than proving
  a weaker path.

## Key Entities

- **Publish Run**: one execution of the publish workflow. Attributes: trigger, run note, forced
  republish flag, builder exit code, project totals, published commit SHA or the fact that nothing
  was published.
- **Trigger**: the event that started a run. One of nightly schedule, manual dispatch, or
  `repository_dispatch`. Carries advisory metadata only.
- **Discovery Credential**: the fine-grained personal access token with Organization, Custom
  properties, Read, and no write permission of any kind. Held as the site repository secret named by
  the site configuration.
- **Trigger Credential**: the GitHub App installation token, scoped to the site repository only, with
  Contents, Read and write, and no organization permissions. Minted per consumer run and never held
  by the publish workflow.
- **Published Tree**: the complete content of the `gh-pages` branch after a publish. Equal to the
  builder's output directory, including `CNAME` and `.nojekyll`, and nothing else.
- **Run Report**: the builder's machine-readable report, attached to the run as an artifact and
  never published.
- **Notification Snippet**: the optional workflow fragment a consumer repository adds to dispatch a
  publish. Attributes: event type, payload fields, credential source, failure tolerance.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A repository becomes part of the published site with no change to the site repository
  and no secret added to the consumer repository, verified by setting only `doc-target` and waiting
  for one scheduled run.
- **SC-002**: Two consecutive publishes over an unchanged organization produce zero commits, and
  `gh-pages` points at the same SHA after the second run.
- **SC-003**: A single changed page produces a commit whose diff contains no file with unchanged
  content, verified by comparing the diff against a fresh full build of both inputs.
- **SC-004**: A publish triggered while another is running never causes a rejected push, verified by
  overlapping a dispatch with a scheduled run and inspecting both runs' logs.
- **SC-005**: For each of exit codes 0, 1, 2, 3, and 4, the resulting `gh-pages` state and run
  conclusion match FR-031 to FR-035, verified by forcing each code once.
- **SC-006**: Every publish leaves `gh-pages` containing exactly the built output, verified by
  comparing a recursive file listing of the branch with a recursive file listing of the output
  directory.
- **SC-007**: No published run produces a commit that differs from the previous commit only in
  ordering, whitespace, or generated identifiers, verified over a sequence of ten runs with one
  documentation change among them.
- **SC-008**: A maintainer who reads only the onboarding section publishes a repository using the
  property alone, and a maintainer who adds the snippet learns from the run summary when their
  repository is not discovered.
- **SC-009**: No credential value appears in any workflow log, artifact, report, or commit message,
  verified by scanning a run's full log and artifacts for the secret values.

## Assumptions

- The site repository stays public and defaults to `master`. The publish workflows live on `master`,
  because a scheduled workflow only runs from the default branch.
- Pages remains configured with `build_type: legacy` from `gh-pages` at `/`, so Jekyll runs on every
  publish and `.nojekyll` is load-bearing.
- The builder's output already includes `CNAME` when `site.hostname` is configured and `.nojekyll`
  unconditionally, as required by spec 001 FR-033. The workflow verifies both rather than
  synthesizing them.
- The builder remains a pure function of its inputs and its output tree is complete on a successful
  run, so the wholesale replacement needs no carry-over from the previous `gh-pages` tree other than
  `CNAME`, which the builder emits itself.
- `repository_dispatch` requires the Contents, Read and write repository permission, which is why the
  App carries exactly that and nothing broader.
- A GitHub App installation token is short-lived, so consumer runs mint one per run rather than
  storing one.
- The organization can restrict which repositories may read an organization secret, and keeping that
  access list aligned with `doc-target` is an operator responsibility, not a workflow behaviour.
- The builder's report schema, exit codes, and CLI surface are fixed by spec 001 and are not changed
  by this specification.
- The custom-domain HTTPS certificate is currently not provisioning: the host serves a
  `CN=*.github.io` certificate and the Pages API returns no `https_certificate` object. That is an
  operational item for the operator documentation and not a behaviour of these workflows.
