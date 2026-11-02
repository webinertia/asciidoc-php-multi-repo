# Tasks: Docs Site Publish Workflows

**Feature**: `specs/003-docs-site-publish/spec.md`

**Plan**: `specs/003-docs-site-publish/plan.md`

**Contract**: `specs/003-docs-site-publish/contracts/dispatch.md`

**Repository split**: phases 1 to 4 are changes in `webinertia/docs.webinertia.net`. Phase 5 and 6
add documentation in both repositories. Phase 6 also contains operator actions that are not code.
Phase 7 is verification and produces no files.

## Execution Strategy

| Phase | Delivers | Repository | Delivery |
|-------|----------|------------|----------|
| 1 | Site repository scaffolding | `docs.webinertia.net` | PR 1 |
| 2 | Workflow skeleton, triggers, concurrency, credentials | `docs.webinertia.net` | PR 2 |
| 3 | Build, guards, wholesale publish, change detection | `docs.webinertia.net` | PR 2 |
| 4 | Failure surfacing and run summary | `docs.webinertia.net` | PR 2 |
| 5 | Consumer notification | both | PR 3 |
| 6 | Operator setup and documentation | both | PR 3 |
| 7 | Verification | none | no PR |

Phases 2, 3, and 4 all edit one workflow file, so they land as one pull request with three checkpoint
reviews rather than three pull requests that each rewrite the same file.

Nothing in this task list is pushed or opened as a pull request by the agent that authored the
specification. Releases and tags belong to the repository owner.

## Phase 1: Site Repository Scaffolding

Goal: the site repository can install the builder from a lock file and run it locally, and nothing
about Pages changes.

- [ ] **T001** Add `site.json` at the root of `webinertia/docs.webinertia.net`.
  - **Acceptance**: it validates against `specs/001-multi-repo-docs-site/contracts/site.schema.json`;
    `organization.name` is `webinertia`; `site.hostname` is `docs.webinertia.net`;
    `organization.tokenEnvVar` is `DOC_DISCOVERY_TOKEN`; project selection is driven by the
    `doc-target` custom property and by nothing else.
- [ ] **T002** Add `.gitignore` covering the builder's cache directory and its output directory.
  - **Acceptance**: after a local build, `git status --porcelain` reports nothing.
- [ ] **T003** Add `composer.json` requiring `webware/asciidoc-php-multi-repo` with a pinned
  constraint, and declaring only `ext-json` and `ext-curl` as platform requirements.
  - **Acceptance**: `composer validate` passes; the constraint is neither a wildcard nor a dev branch.
- [ ] **T004** Commit `composer.lock`.
  - **Acceptance**: a clean clone runs `composer install --no-dev --no-interaction` successfully and
    the resulting lock file is byte-identical to the committed one.
- [ ] **T005** Add a `README.md` stating what the repository is, that `gh-pages` is generated and must
  never be edited by hand, and where the operator guide lives.
  - **Acceptance**: the generated-branch rule and the operator guide location are both stated in the
    first screen of the file.
- [ ] **T006** Confirm Pages configuration is unchanged and record it in the pull request description.
  - **Acceptance**: `gh api repos/webinertia/docs.webinertia.net/pages` still reports
    `build_type: legacy`, source `gh-pages` at `/`, `cname: docs.webinertia.net`,
    `https_enforced: true`.

**Checkpoint**: a clean clone of `master` installs the builder from the lock file and runs `build`
locally against the real organization with `DOC_DISCOVERY_TOKEN` set, producing an output tree that
contains `CNAME` and `.nojekyll`.

## Phase 2: Workflow Skeleton, Triggers, Concurrency, Credentials

Goal: one workflow file, three triggers, one concurrency group, and a preflight that refuses to build
without the discovery credential.

- [ ] **T007** Create `.github/workflows/publish-docs.yml` with the three triggers and a checkout step
  only.
  - **Acceptance**: the schedule is `17 3 * * *`; `workflow_dispatch` declares exactly `note` (string,
    optional) and `force_republish` (boolean, default false); `repository_dispatch` declares exactly
    `[docs-publish]`; the file parses.
- [ ] **T008** Add the workflow-level concurrency group.
  - **Acceptance**: `group: docs-publish` with `cancel-in-progress: false`, declared at workflow level
    so all three triggers share it.
- [ ] **T009** Add permissions.
  - **Acceptance**: the workflow default is `contents: read`; the publish job requests `contents: write`
    and nothing else; no `write-all` appears anywhere.
- [ ] **T010** Add the preflight step.
  - **Acceptance**: the run fails before any build, with a message naming both `DOC_DISCOVERY_TOKEN`
    and `doc-target`, when `site.json` is missing or unparsable, when the environment variable named by
    `organization.tokenEnvVar` is empty, or when that name is not `DOC_DISCOVERY_TOKEN`.
- [ ] **T011** Add runtime installation and `composer install`.
  - **Acceptance**: the PHP version matches the builder's declared support; install uses
    `--no-dev --no-interaction` from the committed lock file; no floating constraint is installed.
- [ ] **T012** Add the dry-run guard for non-default refs.
  - **Acceptance**: a run whose ref is not `master` completes the build and the guards and writes the
    summary, never pushes, and states in the summary that it was a dry run.

**Checkpoint**: a manual dispatch started from a feature branch runs green through preflight and
install, produces a report, and states that it was a dry run.

## Phase 3: Build, Guards, Wholesale Publish, Change Detection

Goal: the published tree is exactly the built output, and a run that finds nothing new commits
nothing.

- [ ] **T013** Add the build step, capturing the exit code without failing the step.
  - **Acceptance**: `--output` resolves to `$RUNNER_TEMP/site`, `--report` resolves to
    `$RUNNER_TEMP/report.json`, `--config` resolves to `site.json`; the exit code is recorded as a step
    output and the step itself does not fail the job.
- [ ] **T014** Add the `CNAME` guard.
  - **Acceptance**: the run fails before any push when `CNAME` is absent from the output; it fails and
    names both values when the output value differs from the live `gh-pages` value; it passes when
    they are equal.
- [ ] **T015** Add the `.nojekyll` guard.
  - **Acceptance**: the run fails before any push when `.nojekyll` is absent from the output.
- [ ] **T016** Add the publish step with wholesale replacement.
  - **Acceptance**: it fetches `gh-pages`, deletes every path in the publish worktree except `.git`,
    copies the output in, and stages with `git add -A`; a missing `gh-pages` branch starts from an
    empty tree instead of failing.
- [ ] **T017** Add the no-change short circuit.
  - **Acceptance**: an empty staged diff produces no commit, no push, and a successful no-op summary;
    `force_republish` overrides it and the summary records that the commit came from a forced
    republish.
- [ ] **T018** Add the commit and push.
  - **Acceptance**: exactly one commit per publishing run; the message names the trigger and the
    changed top-level slugs; it contains no run id, run number, timestamp, or runner name; the push is
    not forced and does not rewrite history.
- [ ] **T019** Suppress the push on a dry run.
  - **Acceptance**: with a non-default ref, the publish step performs the replacement and the
    comparison, records what it would have committed, and pushes nothing.
- [ ] **T020** Confirm the first publish removes the stale content.
  - **Acceptance**: after the first successful publish, `gh-pages` contains no `aurora/`,
    `modelmanager/`, `index.md`, or `_config.yml`, and does contain `CNAME` and `.nojekyll`.

**Checkpoint**: two consecutive dispatches over an unchanged organization produce exactly one commit
in total; a single changed page produces one commit whose diff touches only that project's output plus
the aggregate pages the builder rewrites.

## Phase 4: Failure Surfacing and Run Summary

Goal: one summary tells the operator what is live, and every exit code leaves a known tree.

- [ ] **T021** Add report artifact upload.
  - **Acceptance**: the report is uploaded when it exists, retained for 30 days, and the step is
    skipped cleanly when no report was produced.
- [ ] **T022** Add the run summary writer.
  - **Acceptance**: every run's summary states the trigger, the builder exit code, the totals by
    project status, every project that was not `ok` with its status and reason codes, and either the
    published commit SHA or an explicit statement that nothing was published.
- [ ] **T023** Map exit codes to job conclusion.
  - **Acceptance**: 0 succeeds; 1 publishes and then fails; 2, 3, and 4 publish nothing and fail; the
    mapping matches the table in `plan.md` under "Failure Handling".
- [ ] **T024** Add the dispatch correlation line.
  - **Acceptance**: when a `repository_dispatch` payload names a repository, the summary states whether
    that repository was discovered and, when it was not, names `doc-target` as the reason.
- [ ] **T025** Force each exit code once and record the result.
  - **Acceptance**: for each of 0, 1, 2, 3, and 4, the resulting `gh-pages` state and job conclusion
    match the plan's table; exit 1 publishes; exit 2, 3, and 4 leave `gh-pages` at its previous commit.
- [ ] **T026** Scan a full run for credential leakage.
  - **Acceptance**: searching the run log, the artifacts, the report, and the commit message for the
    values of `DOC_DISCOVERY_TOKEN` and the App private key returns nothing.

**Checkpoint**: an operator who reads one run summary knows exactly what is live on the site, and can
name which credential to rotate when the run failed.

## Phase 5: Consumer Notification

Goal: the dispatch path is documented as optional, works end to end, and reports honestly when a
repository is not onboarded.

- [ ] **T027** Land `contracts/dispatch.md` in this specification directory.
  - **Acceptance**: event type, endpoint, payload fields, credential, snippet, receiver behaviour,
    guarantees, non-guarantees, and stability rules are all stated.
- [ ] **T028** Add the consumer snippet to the onboarding documentation owned by spec 001 task T075.
  - **Acceptance**: the section is titled as optional; it states that setting `doc-target` is the whole
    onboarding requirement; it states the prerequisites, the event type, and that the step must be
    `continue-on-error`.
- [ ] **T029** Handle the `ref` and `source` payload fields alongside `repository`.
  - **Acceptance**: unknown fields are ignored; a field with an unexpected type is ignored and recorded
    as unusable; no payload field influences the set of projects built.
- [ ] **T030** Verify the dispatch path from a repository with `doc-target: true`.
  - **Acceptance**: the run starts, the summary names the repository as discovered, and the published
    tree is identical to what a scheduled run produces from the same inputs.
- [ ] **T031** Verify a dispatch from a repository with `doc-target: false`.
  - **Acceptance**: the run completes normally, the summary records the dispatch as a no-op naming
    `doc-target`, and the published tree is unchanged.

**Checkpoint**: a maintainer who follows only the property step is published at the next scheduled run;
a maintainer who adds the snippet without the property learns why nothing changed.

## Phase 6: Operator Setup and Documentation

Goal: an operator can set up the App, the secret, and the variable from the guide alone, and knows what
to do when each kind of run fails.

- [ ] **T032** Operator action: create the publish GitHub App.
  - **Acceptance**: installed on `webinertia/docs.webinertia.net` only with
    `repository_selection: selected`; Repository permissions Contents, Read and write; no organization
    permission; no other repository permission. No code change.
- [ ] **T033** Operator action: set the organization variable and secret.
  - **Acceptance**: `DOCS_PUBLISH_APP_ID` exists as an organization variable visible to all
    repositories; `DOCS_PUBLISH_APP_PRIVATE_KEY` exists as an organization secret whose access is
    limited to repositories where `doc-target` is true.
- [ ] **T034** Extend the operator guide, owned by spec 001 task T075, with the trigger credential.
  - **Acceptance**: the guide names both credentials, states where each is set, states which one to
    rotate for exit 3 versus a notification failure, and states the rule for keeping the App secret's
    access list aligned with `doc-target`.
- [ ] **T035** Extend the operator guide with the operational items.
  - **Acceptance**: the guide records the custom-domain HTTPS certificate not provisioning with the
    measured evidence and the remedy, the 60 day scheduled-workflow disable, the `gh-pages` ruleset
    constraint, the three stale URLs that return 404 after the first publish, and the discovery PAT's
    expiry and rotation procedure.
- [ ] **T036** Confirm `gh-pages` is not protected in a way that blocks the push.
  - **Acceptance**: `gh api repos/webinertia/docs.webinertia.net/rulesets` returns an empty array, or
    any ruleset present grants the Actions bot a bypass.

**Checkpoint**: an operator who has never seen either repository can complete the App, secret, and
variable setup from the guide alone, and can map a failed run to the credential to rotate.

## Phase 7: Verification

Goal: every success criterion in `spec.md` is demonstrated once, and the results are recorded.

- [ ] **T037** Run the determinism sequence (SC-002, SC-003, SC-007).
  - **Acceptance**: ten runs with one documentation change among them produce exactly one commit, and
    that commit's diff contains no file whose content is unchanged.
- [ ] **T038** Run the overlap sequence (SC-004).
  - **Acceptance**: a dispatch sent during a scheduled run waits, the second run's push is accepted on
    its first attempt, and neither run fails for a reason related to the overlap.
- [ ] **T039** Verify the published tree equals the output (SC-006).
  - **Acceptance**: a recursive listing of `gh-pages` equals a recursive listing of the output
    directory, with no extra and no missing path.
- [ ] **T040** Verify the site serves correctly after the first publish (SC-001, SC-008).
  - **Acceptance**: the root URL serves the built index over HTTPS, one project page serves, theme
    assets load (which proves `.nojekyll` is doing its job), and `aurora/` returns 404.
- [ ] **T041** Verify no committed content varies with the run (SC-007, SC-009).
  - **Acceptance**: no committed file contains a build date, build time, duration, run id, run number,
    or runner name, and no credential value appears in the log or the artifacts.

**Checkpoint**: every SC in `spec.md` has a recorded demonstration, and the results are posted to the
pull request that delivered phases 1 to 4.

## Dependencies and Ordering

- T001, T003, and T004 block T011. The workflow cannot install a lock file that does not exist.
- T007 blocks every other workflow task. There is no file to edit until the skeleton exists.
- T008 blocks T038. The overlap test is meaningless without the concurrency group.
- T013 blocks T014, T015, T016, and T021. The guards and the publish step operate on the build output.
- T016 blocks T017, T018, and T019. There is no staged diff to inspect until the tree is replaced.
- T023 blocks T025. The exit code mapping must exist before each code can be forced.
- T025 blocks T037, T038, and T041. The failure paths must be known good before the sequences run.
- T032 and T033 block T030. The dispatch test needs an App to mint a token from.
- T032 and T033 block T034 and T035. The guide documents what exists.
- T027 blocks T028. The snippet in the guide must match the contract.
- T028 blocks T030 and T031. The dispatch tests use the documented snippet.

## Parallel Opportunities

- T001, T002, T003, T005, and T006 are independent of each other and can be prepared together.
- T014 and T015 are independent guards over the same output and can be written together.
- T021 and T022 are independent of the publish mechanics and can be written while T016 is in review.
- T034 and T035 are separate sections of the operator guide and can be written in either order.
- T037 and T039 are independent verification sequences and can run on separate days.
- T040 depends only on a successful publish, so it can be checked immediately after T020 rather than
  at the end.

## Out of Scope

- The standalone reusable GitHub Action in `webinertia/asciidoc-action`.
- The builder CLI, the site configuration format, and the report schema, all owned by spec 001.
- Markdown ingestion.
- Any change to spec 001 or spec 002, including reticking T075. T034 and T035 add sections to the
  document T075 creates; they do not modify T075's text or status.
- Creating or rotating organization properties, Apps, secrets, or variables from a workflow.
- Changing Pages configuration, including switching `build_type`.
- Any package source change, and therefore any change to `test/`, namespaces, or composer scripts in
  the builder repository.

## Counts

| Phase | Tasks | Count |
|-------|-------|-------|
| 1 | T001 to T006 | 6 |
| 2 | T007 to T012 | 6 |
| 3 | T013 to T020 | 8 |
| 4 | T021 to T026 | 6 |
| 5 | T027 to T031 | 5 |
| 6 | T032 to T036 | 5 |
| 7 | T037 to T041 | 5 |
| **Total** | | **41** |

Operator actions that are not code changes: T032, T033, and the confirmation in T036.
