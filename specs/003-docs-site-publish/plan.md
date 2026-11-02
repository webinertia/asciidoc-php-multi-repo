# Implementation Plan: Docs Site Publish Workflows

**Branch**: `docs/003-docs-site-publish` | **Date**: 2026-11-01 | **Spec**:
[spec.md](spec.md)

**Input**: Feature specification from `specs/003-docs-site-publish/spec.md`

**Repository split**: the specification artifacts live in `webinertia/asciidoc-php-multi-repo`. The
implementation artifacts live in `webinertia/docs.webinertia.net`, except the operator guide and the
consumer notification documentation, which live in the builder repository because spec 001 task T075
already owns them.

## Summary

Add the three publish workflows to `webinertia/docs.webinertia.net`, converging on one job that
builds the site with the pinned builder, verifies the two load-bearing files, replaces the `gh-pages`
tree wholesale with the built output, and commits only when the content actually changed. The
discovery credential stays a site repository secret. The trigger credential becomes a GitHub App
installed on the site repository only, so consumer repositories mint short-lived installation tokens
instead of holding a long-lived dispatch secret.

Three decisions in this plan were not made by the owner personally. They are marked as such below,
with the reasoning that supports them, so the owner can override any of them without re-deriving the
context.

## Technical Context

**Runtime**: GitHub Actions on `ubuntu-latest`. PHP and Composer are the only installed toolchain; the
builder supplies everything else. No Node.js, no Ruby, no additional runtime binary.

**Builder dependency**: `webware/asciidoc-php-multi-repo`, pinned by `composer.json` constraint plus a
committed `composer.lock`. The workflow installs from the lock file and never from a floating
constraint.

**Builder invocation**: one call to the builder's `build` command with `--config`, `--output`, and
`--report`. The workflow reads the exit code, the report JSON, and the output tree. It does not parse
the builder's stdout beyond passing it into the run summary.

**Publish transport**: `git` against the `gh-pages` branch, authenticated by the site repository's own
`GITHUB_TOKEN`. No PAT, no App token, no force push.

**Configuration**: `site.json` at the site repository root, conforming to spec 001's
`contracts/site.schema.json`.

**Secrets and variables**:

| Name | Kind | Scope | Purpose |
|------|------|-------|---------|
| `DOC_DISCOVERY_TOKEN` | repository secret, site repository | `webinertia/docs.webinertia.net` | Discovery, Organization Custom properties Read only |
| `DOCS_PUBLISH_APP_ID` | organization variable | all repositories | Public identifier of the publish App |
| `DOCS_PUBLISH_APP_PRIVATE_KEY` | organization secret | repositories where `doc-target` is true | Mints installation tokens in consumer runs |
| `GITHUB_TOKEN` | automatic | site repository workflow | Pushes `gh-pages` with `contents: write` |

`DOC_DISCOVERY_TOKEN` already exists in the site repository. The other three are operator setup.

**Pages configuration**: unchanged. `build_type: legacy`, source `gh-pages` at `/`,
`https_enforced: true`, `custom_404: false`, `cname: docs.webinertia.net`.

## Constitution Check

Against `asciidoc-php-multi-repo` constitution version 1.1.0.

| Principle | Assessment |
|-----------|------------|
| I. The converter is a fixed dependency | Not engaged. No converter change is needed. |
| II. Configuration over hard-coded convention | Satisfied. Every organization, host, and repository value in this plan lives in the site repository's `site.json` or in workflow configuration, which is exactly where the constitution says Webinertia's values belong. The builder source is not touched. |
| III. Deterministic output | Satisfied and extended. The workflow declines to commit when the tree is unchanged, which is what turns builder determinism into a reviewable published history. |
| IV. Portability without POSIX assumptions | Not engaged for the package. The workflow runs on Linux only, which the constitution names as the supported runtime. |
| V. No new runtime binaries | Satisfied for the package. The workflow adds no runtime dependency. The consumer snippet uses one GitHub-maintained action to mint an App token; that runs in the consumer's workflow, not in the package, and adds no dependency to the builder or to the Action image. |
| VI. Isolated failure domains | Satisfied. Exit 1 publishes the tree and then fails the run, so one broken project does not take the site down while the degradation stays visible. |
| VII. Test-first against a fixture organization | Not engaged. This plan adds no package code. Its verification is the branch dry run plus the forced exit codes, which are described in `tasks.md`. |
| VIII. Stable external contract | Satisfied. The dispatch payload becomes a documented contract in `contracts/dispatch.md`. The builder's CLI, exit codes, and report schema are consumed unchanged. |

**Result**: no violations. Nothing is recorded in Complexity Tracking as a violation; the entries
there are accepted costs, not principle breaches.

## Verified State (Phase 0 research)

Every fact below was measured against `webinertia/docs.webinertia.net` on 2026-11-01. Where a fact
could not be measured it is marked unverified rather than assumed.

| Fact | Value | How it was established |
|------|-------|------------------------|
| Default branch | `master` | `gh api repos/webinertia/docs.webinertia.net` |
| Visibility, archived | public, not archived | same call |
| Branches | `gh-pages`, `master`, `renovate/configure` | `gh api .../branches` |
| `master` root content | `README.md` only | `gh api .../contents?ref=master` |
| Workflows | none. `.github/workflows` returns 404 | `gh api .../contents/.github/workflows?ref=master` |
| `gh-pages` root content | `CNAME`, `_config.yml`, `aurora/`, `index.md`, `modelmanager/` | `gh api .../contents?ref=gh-pages` |
| `CNAME` content | `docs.webinertia.net` plus a trailing newline, 19 bytes | `gh api` blob read, decoded |
| `_config.yml` content | `theme: jekyll-theme-midnight`, 28 bytes | same |
| `.nojekyll` | absent | branch root listing |
| `aurora/`, `modelmanager/` | each contains only `index.md` | directory listings |
| Pages `build_type` | `legacy` | `gh api .../pages` |
| Pages source | `gh-pages` at `/` | same |
| Pages `cname`, `https_enforced`, `custom_404` | `docs.webinertia.net`, `true`, `false` | same |
| Pages `https_certificate` | key absent from the response | same |
| Custom domain state | `protected_domain_state: verified` | same |
| HTTPS certificate served | `CN=*.github.io` with the GitHub wildcard SANs | TLS probe of `docs.webinertia.net` |
| Rulesets | none | `gh api .../rulesets` returns an empty array |
| Actions | enabled, `allowed_actions: all`, no SHA pinning required | `gh api .../actions/permissions` |
| Repository secrets | exactly one, `DOC_DISCOVERY_TOKEN`, created 2026-11-01T02:02:25Z | `gh api .../actions/secrets` |
| Repository variables | none | `gh api .../actions/variables` |
| Organization secrets and variables | not readable with the available scope, returns 403 | `gh api orgs/webinertia/actions/secrets` |
| Organization App installations | `renovate`, `codecov`, `codspeed`, `webinertia-label-sync`. No publish App exists | `gh api orgs/webinertia/installations` |
| `doc-target` property | `true_false`, organization scope, `required: true`, default `"false"`, `require_explicit_values: true`, `values_editable_by: org_actors` | `gh api orgs/webinertia/properties/schema` |

Two consequences follow directly. First, `gh-pages` has no ruleset, so a direct push from the
workflow is accepted today; if a ruleset is ever added it must grant the Actions bot a bypass or the
publish will fail. Second, no publish App exists yet, so creating it is an operator prerequisite, not
an implementation step.

## Credentials and Operator Setup

### Discovery credential

The fine-grained PAT with Organization, Custom properties, Read and no write permission of any kind.
It is already stored as the site repository secret `DOC_DISCOVERY_TOKEN`, which is the name
`site.json` must use for `organization.tokenEnvVar`. The workflow passes it to the build step as an
environment variable and does nothing else with it. It is never echoed, never written to the report,
and never written to an artifact.

### Trigger credential

A GitHub App named for the fleet's docs publishing, installed on `webinertia/docs.webinertia.net`
with `repository_selection: selected` and that single repository selected. Its only permission is
Repository permissions, Contents, Read and write. It carries no organization permission and no other
repository permission. `repository_dispatch` requires exactly that permission, which is why the App
carries it and nothing broader.

Consumer runs mint an installation token per run:

```
- id: token
  uses: actions/create-github-app-token@<pinned-sha>
  with:
    app-id: ${{ vars.DOCS_PUBLISH_APP_ID }}
    private-key: ${{ secrets.DOCS_PUBLISH_APP_PRIVATE_KEY }}
    owner: webinertia
    repositories: docs.webinertia.net
```

`repositories:` narrows the token to the site repository even if the App is installed more widely, so
the token cannot touch the consumer's own repository.

The App's private key is an organization secret whose repository access list is limited to the
repositories that carry `doc-target: true`. Keeping that list aligned with the property is manual and
is recorded as an accepted cost in Complexity Tracking.

The publish workflow itself never mints or holds an App token. It has no use for one.

### Publishing

`gh-pages` is pushed with the site repository's `GITHUB_TOKEN` at `contents: write`. This is the
decision spec 001 already recorded and it is unchanged.

### Organization property

Unchanged from spec 001: `doc-target`, `true_false`, organization scope, `required: true`,
`default_value: "false"`, `require_explicit_values: true`, `values_editable_by: org_actors`.

## Workflow Architecture

One workflow file, `.github/workflows/publish-docs.yml`, with three triggers and one job.

```
schedule: 17 3 * * *
workflow_dispatch: { note, force_republish }
repository_dispatch: types: [docs-publish]
        |                |                    |
        +----------------+--------------------+
                         |
             concurrency: group docs-publish, cancel-in-progress false
                         |
                    job: publish
```

**Why one job rather than three workflows**: three workflows that each build and push would need
three copies of the tree replacement logic, three concurrency groups that must agree, and three
places to change when the builder's CLI moves. One job with three triggers is one code path, which is
what FR-001 requires.

**Job steps**, in order:

1. **Checkout** the site repository at the triggering ref.
2. **Preflight**: confirm `site.json` exists and parses, confirm the environment variable named by
   `organization.tokenEnvVar` is non-empty, and confirm that name is `DOC_DISCOVERY_TOKEN`. A failure
   here ends the run before any build, with a message naming the secret and the property (FR-038).
3. **Install runtime**: the supported PHP version, Composer, and `git`, then `composer install
   --no-dev --no-interaction` from the committed lock file.
4. **Build**: run the builder with `--config site.json`, `--output $RUNNER_TEMP/site`, and `--report
   $RUNNER_TEMP/report.json`. Capture the exit code without failing the step.
5. **Guards**: require `$RUNNER_TEMP/site/CNAME` and `$RUNNER_TEMP/site/.nojekyll`, and require the
   `CNAME` value to equal the current `gh-pages` `CNAME`. Any guard failure stops the run before the
   publish step.
6. **Report**: upload `$RUNNER_TEMP/report.json` as an artifact when it exists, and write the run
   summary.
7. **Publish**: run only when the exit code is 0 or 1 and the ref is the default branch. Otherwise
   record the reason and skip.
8. **Conclusion**: fail the run when the exit code is non-zero.

**Publish step mechanics**:

1. Fetch `gh-pages` shallowly. If the branch does not exist, start from an empty tree.
2. Create a scratch worktree for `gh-pages` outside the checkout.
3. Delete every path in that worktree except `.git`. This is the wholesale rule: nothing from the
   previous tree survives on purpose, so nothing can survive by accident.
4. Copy the built output into the worktree.
5. `git add -A`, then inspect the staged diff.
6. If nothing is staged and the run is not a forced republish, stop. No commit, no push, no-op
   success (FR-027).
7. Otherwise commit once and push to `gh-pages` without force (FR-022, FR-023).

The commit message names the trigger and the top-level slugs whose output changed, derived from the
staged diff. It carries no run id, run number, timestamp, or runner name (FR-029).

**Why the CNAME guard compares instead of trusting the builder**: the builder emits `CNAME` from
`site.hostname`, so a change to that field would silently move the custom domain on the next routine
publish. Comparing against the live value turns that into a deliberate two-step action and keeps the
domain out of the failure modes that a mistyped host name would otherwise create.

**Why a branch run is a dry run**: `workflow_dispatch` can be started from any ref, and a branch run
that pushed would publish unmerged workflow changes to the live site. Making non-default refs build,
guard, and report but not push lets the workflow be tested on a branch against the real organization
without touching `gh-pages` (FR-050, FR-051).

## Published Tree Mechanics

The published tree is exactly the builder's output directory. There is no carry-over step. That is
not a simplification; it is the requirement, and it produces these specific consequences:

| Path | Outcome after the first publish | Why |
|------|-------------------------------|-----|
| `CNAME` | present, content unchanged | The builder emits it from `site.hostname`, and the guard requires it to match the live value |
| `.nojekyll` | present | The builder emits it, and the guard requires it |
| `_config.yml` | removed | It belongs to the retired Jekyll-era content, and nothing preserves it |
| `index.md` | removed | Not in the builder's output |
| `aurora/` | removed | Not in the builder's output |
| `modelmanager/` | removed | Not in the builder's output |

**The URLs break permanently.** `https://docs.webinertia.net/aurora/`,
`https://docs.webinertia.net/modelmanager/`, and `https://docs.webinertia.net/index.md` return 404
after the first successful publish. No redirect, alias, or compatibility page is provided for them,
and none is planned. Pages has `custom_404: false`, and the builder's own `404.html` serves the miss
once it exists, but a `404.html` does not redirect. If any of those three URLs is referenced from
outside the organization, it must be repointed before the first publish; that is an operator action
and it is recorded in the operator guide.

**Why `.nojekyll` is load-bearing rather than tidy**: Pages is configured `build_type: legacy`, so
GitHub runs Jekyll over the branch on every build regardless of what the branch contains. Without
`.nojekyll`, Jekyll drops every path beginning with an underscore, which would silently delete the
builder's theme asset directory if its name starts with one, and reprocesses every HTML file it does
serve. With it, the branch is served as written.

**Why the tree is not built inside the checkout**: `--output` points into `$RUNNER_TEMP`, so the
checkout stays clean and the publish step is the only step that writes to a git tree. A build that
wrote into the checkout would make "what is being published" ambiguous.

## Concurrency

One concurrency group for all three triggers, with cancellation disabled:

```
concurrency:
  group: docs-publish
  cancel-in-progress: false
```

The platform keeps at most one run waiting per group and marks a superseded waiting run as such. That
is exactly the behaviour this spec wants, and it needs no lock file, no cache, and no artifact,
because every run rebuilds the complete discovered set. A superseded run's content is therefore never
lost; the surviving run picks it up.

`cancel-in-progress: true` is rejected. Cancelling a publish mid-build would either leave a cancelled
run holding the group while its cleanup ran, or, worse, cancel between the push and the summary, which
turns a correct site into an unexplained red run.

## Failure Handling

| Exit code | Meaning | `gh-pages` after the run | Job conclusion | Summary states |
|-----------|---------|--------------------------|----------------|----------------|
| 0 | complete | new tree if changed, unchanged otherwise | success | commit SHA, or no-op |
| 1 | published but degraded | new tree, with named gaps | failure | failed and partial projects with reason codes |
| 2 | config error | previous commit, untouched | failure | exit code, first error |
| 3 | discovery error | previous commit, untouched | failure | exit code, first error, credential and property named |
| 4 | internal error | previous commit, untouched | failure | exit code, first error |
| guard failure | `CNAME` or `.nojekyll` missing or `CNAME` changed | previous commit, untouched | failure | which guard failed and both values where relevant |

Exit 1 publishing and then failing is the deliberate reading of spec 001's contract: that contract
says a partial site exits non-zero so CI surfaces the problem while still publishing everything that
succeeded. Reporting the run as a success would hide the degradation; publishing nothing would let one
broken repository take the site offline, which principle VI forbids.

The report artifact is uploaded for every run that produced one, with a retention period of 30 days,
and it is never inside the published tree because the report path is outside the output directory.

## Determinism and Change Detection

The builder guarantees identical inputs produce identical bytes and writes a file only when its bytes
change. The workflow adds the deployment half of that guarantee:

1. The built tree is compared with the fetched `gh-pages` tree by content, not by timestamp, mode, or
   path alone. `git add -A` followed by `git diff --cached --quiet` performs that comparison, and git
   compares content hashes, so it is exact.
2. An empty staged diff means no commit and no push, reported as a successful no-op.
3. A non-empty staged diff produces exactly one commit whose message is derived from the trigger and
   the changed slugs.
4. Nothing in the committed tree is derived from the run: no build date, duration, run id, run
   number, or runner name. The builder already forbids those; the workflow must not reintroduce them
   in a generated index, sitemap, or summary file, so it generates none.

The only path that creates a commit without a content change is an explicit forced republish from
manual dispatch, and it is labelled as such in the summary.

## Consumer Notification

Documented in `contracts/dispatch.md`. Summary:

- **Event type**: `docs-publish`. Exactly one.
- **Transport**: `POST /repos/webinertia/docs.webinertia.net/dispatches`.
- **Credential**: a GitHub App installation token minted in the consumer's run, narrowed to the site
  repository. Never a long-lived token.
- **Payload**: advisory only. `repository` (the dispatching repository's full name), `ref` (the ref
  or commit the consumer wants reflected), and `source` (free text naming the consumer workflow).
  Unknown fields are ignored, which makes additive changes safe without a version field.
- **Effect**: a rebuild request. It never selects, restricts, or extends the built project set. A
  repository is published because `doc-target` is true, not because it dispatched.
- **Failure tolerance**: the notification step is `continue-on-error`, so a notification failure
  cannot fail the consumer's own workflow. The nightly run is the safety net.

The receiver records the payload in the run summary and states whether the named repository appeared
in the discovered set. That single line is what tells a maintainer who added the snippet but never set
`doc-target` why nothing changed.

## Project Structure

### Documentation (this repository)

```
specs/003-docs-site-publish/
|-- spec.md
|-- plan.md
|-- tasks.md
|-- checklists/
|   `-- requirements.md
`-- contracts/
    `-- dispatch.md
```

The operator guide and the consumer notification documentation are owned by spec 001 task T075 at
`docs/v1/operating.adoc`. This specification does not duplicate them; it adds the content T075 must
carry, and `tasks.md` records that dependency.

### Source (site repository)

```
webinertia/docs.webinertia.net (default branch master)
|-- .github/
|   `-- workflows/
|       `-- publish-docs.yml
|-- .gitignore
|-- composer.json
|-- composer.lock
|-- site.json
`-- README.md
```

No `src/`, no `test/`, and no PHP source. The site repository configures the builder and runs it; it
does not contain builder code. The fleet conventions for `test/`, namespaces, and composer scripts
therefore do not apply here, because there is nothing to test in the site repository beyond the
workflow itself, which is verified by the branch dry run and the forced exit codes in `tasks.md`.

## Complexity Tracking

Accepted costs. None is a constitution violation.

| Cost | Why it is accepted | Simpler alternative rejected |
|------|-------------------|------------------------------|
| The App private key's repository access list must be kept aligned with `doc-target` by hand | An over-broad access list only lets a repository that is already in the organization request a rebuild. It grants no discovery, no read of the organization, and no write to any content. | A per-repository secret, which is one private key copy per repository and one rotation event per copy. |
| Two credentials rather than one | Spec 001 requires it: a leak in a component repository must not enumerate the organization. | One PAT with both permissions, which couples the two blast radii. |
| A dry-run branch path in the workflow | Testing the workflow against the real organization without publishing needs it. | Publishing only from `master` with no branch path, which makes the workflow untestable except in production. |
| The commit message body lists changed slugs, which costs one diff inspection per run | It is what makes the published history readable at a glance. | A fixed commit message, which loses the only signal about what changed. |

## Confirmed Decisions

Each decision records the choice, the reasoning, the rejected alternative, and who made it. Two of
these were not made by the owner personally; they are marked accordingly and are open to override.

### 1. The `gh-pages` tree is replaced wholesale

**Decision**: the published tree is exactly the built output plus nothing. Every path in the previous
`gh-pages` tree that the output does not contain is deleted. `CNAME` and `.nojekyll` survive only
because the builder emits them, and `_config.yml` is deleted along with the rest of the stale content.

**Provenance**: coordinator decision, open to override by the owner. The owner has confirmed the
choice stands but has not personally ruled on it.

**Reasoning**: wholesale replacement is the only option consistent with spec 001's determinism
requirement, which states that identical inputs must produce identical bytes so that `gh-pages` only
changes when documentation changes. A tree that keeps unmanaged leftovers is not a function of the
inputs. Deleting a fixed list of known stale paths drifts: any file that an operator, a previous
generator, or a future accidental commit leaves behind persists forever, so the published tree stops
being reproducible and two builds from identical inputs can differ. Clearing the branch by hand
before the first run fixes only the first publish and leaves the second one non-reproducible.

**Rejected alternatives**:

- *Delete known stale paths by name* (`aurora/`, `modelmanager/`, `index.md`, `_config.yml`). Rejected
  because the list is only correct for the first run; the mechanism does not generalise and cannot be
  proven correct later.
- *Clear `gh-pages` manually before the first run, then let the workflow manage it*. Rejected because
  it moves the correctness requirement onto a human action that is performed once, and provides no
  answer for a tree that later drifts.
- *Publish into a subdirectory and keep a hand-written landing page at the root*. Rejected because it
  keeps a second source of truth for the site root, which the builder already owns.

### 2. The trigger credential is a GitHub App installation token

**Decision**: a single GitHub App, installed on `webinertia/docs.webinertia.net` only, with Contents
Read and write and no organization permissions. Consumer repositories mint a short-lived installation
token at run time from an organization secret and an organization variable.

**Provenance**: owner decision. Settled.

**Reasoning**: it removes the long-lived shared secret from every consumer repository, which is the
whole reason the trigger credential was separated from the discovery credential in the first place. A
leaked installation token expires within the hour, is scoped to one repository, and can do one thing:
request a rebuild. Rotation is one private key, not one per repository. It also keeps the trigger
permission at exactly what `repository_dispatch` requires, with no organization surface at all.

**Rejected alternatives**:

- *A fine-grained PAT duplicated as a per-repository secret in every onboarded repository*. Rejected
  because it is one long-lived secret per repository, one rotation event per copy, and a leak in any
  single repository yields a credential with no expiry that can dispatch on demand.
- *Rely on the nightly schedule only and defer `repository_dispatch`*. Rejected because it makes the
  site up to a day stale and removes the ability to publish on merge, which is the difference between
  a site people trust and one they check.

### 3. Deployment is a direct push to `gh-pages`, keeping `build_type: legacy`

**Decision**: the workflow commits to `gh-pages` and pushes with the site repository's `GITHUB_TOKEN`.
Pages configuration is not touched.

**Provenance**: coordinator decision, open to override by the owner. The owner has confirmed the
choice stands but has not personally ruled on it.

**Reasoning**: a direct push to `gh-pages` with `build_type` left on `legacy` requires no Pages
settings change from the operator, so the workflow works against the repository exactly as it is
configured today. `actions/deploy-pages` would require switching `build_type` to `workflow`, which is
a settings change the operator must make and a different deployment model to reason about, and it
creates a deployment record on every run whether or not the content changed. That weakens the
no-churn and determinism properties spec 001 establishes, because the deployment history would then
show a deployment per run while the content history shows a commit only when content changed. It also
makes `.nojekyll` moot, which removes one of the two guards this specification relies on, while
adding a deployment artifact retention surface.

**Rejected alternative**:

- *`actions/deploy-pages` with `build_type: workflow`*. Rejected for the reasons above: an operator
  settings change, a deployment record per run, and a weaker change-detection story.

### 4. All three triggers converge on one job

**Decision**: one workflow file, one job, three triggers, one concurrency group.

**Provenance**: derived from FR-001 and not open to override without changing the spec.

**Rejected alternative**: three workflows. Rejected because it triples the tree replacement logic and
creates three concurrency groups that must be kept in agreement by hand.

### 5. The nightly schedule is `17 3 * * *`

**Decision**: 03:17 UTC daily.

**Provenance**: derived from FR-002. The exact minute is a plan detail.

**Reasoning**: off the hour, so the run does not compete with the fleet's other scheduled work, and
early enough in UTC that the result is available at the start of the European working day. The
expression is fixed in the workflow and is not derived from anything.

### 6. Publishing commits onto the existing `gh-pages` history

**Decision**: one commit per publishing run, appended to `gh-pages`, never forced.

**Provenance**: derived from FR-022 and FR-023.

**Rejected alternative**: an orphan commit per publish, force-pushed. Rejected because it destroys the
published history, which is the evidence that determinism is working, and because a force push makes
a concurrent run's push fail destructively rather than benignly.

### 7. Exit 1 publishes and then fails the run

**Decision**: exit 1 publishes the built tree and marks the run failed.

**Provenance**: derived from spec 001's exit code contract and constitution principle VI.

**Rejected alternatives**: treating exit 1 as a success, which hides a degraded site; treating exit 1
as a failure with no publish, which lets one broken repository take the whole site offline.

### 8. The `CNAME` guard is a hard failure, including on mismatch

**Decision**: a missing `CNAME` fails the run, and a `CNAME` value that differs from the live one also
fails the run, both before anything is pushed.

**Provenance**: derived from FR-018 and the verified Pages configuration.

**Reasoning**: deleting `CNAME` clears the custom domain in Pages settings, so its loss is not a
cosmetic regression; it takes the site off its host name. A mismatch is a silent domain move, which is
worse than a red run.

### 9. The dispatch payload is advisory and unversioned

**Decision**: the payload carries `repository`, `ref`, and `source`, all optional. Unknown fields are
ignored. There is no version field.

**Provenance**: derived from FR-042 and FR-043.

**Rejected alternative**: a versioned payload schema with a `schemaVersion` field. Rejected because
unknown-field tolerance already provides forward compatibility for additive changes, and the payload
selects nothing, so there is no behaviour for a version to gate.

### 10. Branch runs are dry runs

**Decision**: a run whose ref is not the default branch builds, guards, and reports, and does not
push.

**Provenance**: derived from FR-050 and FR-051.

**Rejected alternative**: allowing branch runs to publish, which makes `workflow_dispatch` a way to
publish unmerged workflow changes to the live site.

## Operational Items for the Operator Guide

These are documented, not implemented, and they extend spec 001 task T075's operator guide rather than
creating a second one.

1. **The custom-domain HTTPS certificate is not provisioning.** On 2026-11-01 the Pages API returned no
   `https_certificate` object and `https://docs.webinertia.net` served a `CN=*.github.io` certificate,
   while `https_enforced` is true and `protected_domain_state` is `verified`. The site therefore serves
   a certificate that does not match its host name. The operator remedy is to re-save the custom domain
   in Pages settings, or to remove and re-add it, and then wait for provisioning. This is a Pages
   setting, not a workflow behaviour, and the workflow must not attempt to fix it.
2. **Scheduled workflows are disabled after 60 days without repository activity.** If the site
   repository is quiet for long enough, the nightly run stops. A manual dispatch restores it, and the
   guide must say so.
3. **`gh-pages` must stay unprotected, or any ruleset must grant the Actions bot a bypass.** There is
   no ruleset today. Adding one that requires pull requests would make every publish fail.
4. **The three stale URLs break at first publish.** `aurora/`, `modelmanager/`, and `index.md` return
   404 with no redirect. Any external reference must be repointed first.
5. **The App's organization secret access list must track `doc-target`.** A repository that gains
   `doc-target: true` should gain secret access; one that loses it should lose secret access. The
   consequence of a stale list is limited to an unnecessary dispatch capability.
6. **The discovery PAT expires.** Its expiry date and the rotation procedure must be recorded, and the
   expiry must be far enough out that a scheduled run is not the first thing to discover it.
7. **Which credential to rotate when a run fails.** Exit 3 means the discovery credential; a
   notification failure in a consumer means the App. The guide must state the mapping, because the two
   credentials are deliberately separate and are set in different places.

## Out of Scope

Stated again because the boundary matters more than the detail: the standalone reusable GitHub Action
in `webinertia/asciidoc-action`, the builder CLI and the site configuration format, Markdown
ingestion, and any rewrite of spec 001 or spec 002.
