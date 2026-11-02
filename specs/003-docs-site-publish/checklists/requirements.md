# Specification Quality Checklist: Docs Site Publish Workflows

**Purpose**: validate that `spec.md`, `plan.md`, and `tasks.md` are complete, testable, and free of
implementation leakage before any workflow YAML is written.

**Created**: 2026-11-01

**Feature**: `specs/003-docs-site-publish/`

## Content Quality

- [x] No implementation code appears in `spec.md`. The specification states behaviour, not YAML,
      shell, or step names.
- [x] The specification is written for its readers: the operator who runs the site and the maintainer
      who onboards a repository. It does not assume the reader has read spec 001 line by line, but it
      names spec 001 wherever a decision is inherited.
- [x] Every mandatory section is present: scope, user scenarios with priorities and independent tests,
      edge cases, numbered requirements, key entities, success criteria, and assumptions.
- [x] The scope boundary is stated twice, once in `spec.md` and again in `plan.md` and `tasks.md`,
      because the deferral from spec 001 is the reason this document exists.
- [x] No em dash (U+2014) or en dash (U+2013) appears in any file. Verified against the diff before
      the commit.

## Requirement Completeness

- [x] Every requirement is numbered and testable: FR-001 to FR-051.
- [x] Every requirement is traceable to a user story, an edge case, or a decision in `plan.md`.
- [x] All three triggers are specified: nightly schedule (FR-002), manual dispatch (FR-003), and
      `repository_dispatch` (FR-004).
- [x] Concurrency and locking are specified, including the overlap case and the superseded-queued-run
      case (FR-013 to FR-016).
- [x] Tree replacement is specified in general form, whatever the branch previously held, including
      `CNAME` byte preservation and `.nojekyll` (FR-017 to FR-025).
- [x] Failure handling is specified for every exit code, with the resulting `gh-pages` state named in
      each case (FR-031 to FR-039).
- [x] Determinism is specified at the deployment layer, not only at the builder layer (FR-026 to
      FR-030).
- [x] The consumer snippet and its relationship to `doc-target` discovery are specified (FR-040 to
      FR-045).
- [x] Both credentials are specified, with where each is set and which component may hold each
      (FR-006 to FR-012).
- [x] The site repository's own scaffolding is specified (FR-046 to FR-049).
- [x] No requirement depends on a component that this specification does not name.

## Testability

- [x] Every success criterion is measurable and names how it is demonstrated (SC-001 to SC-009).
- [x] Every user story has an independent test that can be run without completing the other stories.
- [x] Every acceptance scenario is written as Given, When, Then.
- [x] The failure paths are testable: each exit code can be forced, and the expected `gh-pages` state
      is stated for each.
- [x] The determinism claim is testable by sequence: ten runs with one change, one commit expected.
- [x] The no-churn claim is testable by sequence: two consecutive runs, zero commits expected.
- [x] The overlap claim is testable by timing: a dispatch sent during a scheduled run.
- [x] Each of these has a task in `tasks.md` under Phase 7.

## Scope Discipline

- [x] The standalone reusable GitHub Action is explicitly out of scope.
- [x] The builder CLI and the site configuration format are explicitly out of scope and attributed to
      spec 001.
- [x] Markdown ingestion is explicitly out of scope.
- [x] No file under `specs/001-multi-repo-docs-site/` or `specs/002-webware-alignment/` is edited,
      including task T075. The operator guide is extended by new tasks that add sections to the
      document T075 creates.
- [x] The specification does not restate or reopen any decision already settled in spec 001. Where it
      builds on one, it names the source.
- [x] Pages configuration changes are out of scope, and the workflow is forbidden from making them
      (FR-024).
- [x] Operator actions are separated from code changes in `tasks.md`, and no workflow is permitted to
      create, rotate, or read a credential it does not need (FR-010).

## Ambiguity Resolved

- [x] "Publish" is defined as replacing the complete `gh-pages` tree with the built output, not as
      adding to it.
- [x] "Unchanged" is defined as byte-identical content across the complete tree, compared by content.
- [x] "Nothing published" is defined as `gh-pages` still pointing at its previous commit.
- [x] "Optional" for the notification snippet is defined as: failing to send it delays publication but
      never prevents it.
- [x] The reason exit 1 publishes and then fails is stated, rather than left as a table entry.
- [x] The reason `.nojekyll` is load-bearing is stated with the Pages setting that makes it so.
- [x] What happens to a removed path is stated explicitly: it returns 404, with no redirect and no
      compatibility page.
- [x] The requirement that a removed path is absent is stated so that an already-clean branch cannot
      satisfy it trivially, and verification seeds stale content rather than naming historical paths.

## Decisions Recorded With Provenance

- [x] Wholesale replacement of `gh-pages` is recorded as a decision with its reasoning, marked as a
      coordinator decision open to override by the owner, and each rejected alternative is named with
      the reason it drifts or fails to generalise.
- [x] The GitHub App trigger credential is recorded as the owner's own decision, with the two rejected
      alternatives named.
- [x] Direct push to `gh-pages` with `build_type` left on `legacy` is recorded as a decision with its
      reasoning, marked as a coordinator decision open to override by the owner, and the rejected
      `actions/deploy-pages` alternative is named with the reason it weakens the no-churn property.
- [x] No decision is presented as the owner's ruling when it was not.
- [x] Each decision states its rejected alternative, matching the format of spec 001's confirmed
      decisions.

## Facts Verified Rather Than Assumed

- [x] The site repository's default branch, visibility, branch list, and `master` content were read
      from the API, not assumed.
- [x] The `gh-pages` content was read from the API and re-read after commit `36dfe12`: exactly two
      blobs, `CNAME` at 19 bytes and `.nojekyll` at 0 bytes, with the retired paths already deleted.
- [x] The `36dfe12` cleanup, its out-of-band origin, and the resulting 404s at the root path and at
      `aurora/` are recorded as measured facts rather than as predictions.
- [x] The Pages configuration, including `build_type`, source, `https_enforced`, `custom_404`, and the
      absence of an `https_certificate` object, was read from the API.
- [x] The absence of workflows, the absence of rulesets, and the existing `DOC_DISCOVERY_TOKEN` secret
      were confirmed rather than assumed.
- [x] The `doc-target` property configuration was read from the organization property schema.
- [x] The absence of an existing publish App was confirmed against the organization's installations.
- [x] Facts that could not be measured, namely the organization secrets and variables, are marked
      unverified in `plan.md` rather than stated as known.

## Contract Surface

- [x] A contract exists only where another party genuinely depends on this specification:
      `contracts/dispatch.md`, consumed by every onboarding repository.
- [x] The contract states the event type, the endpoint, the payload fields, the credential, the
      snippet, the receiver's behaviour, the guarantees, the non-guarantees, and the stability rules.
- [x] No contract was invented for the manual dispatch inputs or the schedule, because no other party
      depends on them.
- [x] The dispatch payload is explicitly advisory, so no consumer can depend on it to select content.

## Notes

- The `gh-pages` branch has no ruleset today, so a direct push is accepted. Adding a pull-request
  ruleset to `gh-pages` later would break every publish; the operator guide records this.
- The custom-domain HTTPS certificate is not provisioning: the host serves a `CN=*.github.io`
  certificate and the Pages API returns no `https_certificate` object, while `https_enforced` is true.
  This is an operator item, recorded in `plan.md` and in the operator guide task, and deliberately not
  a behaviour of these workflows.
- The site repository contains no PHP source and no tests. The fleet conventions for `test/`,
  namespaces, and composer scripts therefore do not apply to it. They continue to apply to the builder
  repository, which this specification does not change.
- Three decisions in `plan.md` were not made by the owner personally. Two are coordinator decisions
  that the owner has confirmed stand and can still override; one is the owner's own decision. The
  provenance labels exist so a future reader does not mistake coordination for a ruling.
