# Specification Quality Checklist: Multi-Repo Documentation Site Builder

**Purpose**: validate that `spec.md` is complete, testable, and free of implementation leakage before
planning proceeds.

**Feature**: `specs/001-multi-repo-docs-site`

## Content quality

- [x] No implementation code, class names, or file paths from `src/` appear in `spec.md`. The
  specification names behaviour and contracts, not modules.
- [x] The specification focuses on what a maintainer and a reader experience, not on how the builder
  is structured internally.
- [x] Every section is written for a reader who has not seen the converter's source.
- [x] All mandatory sections are present: user scenarios, requirements, key entities, success
  criteria, assumptions.

## Requirement completeness

- [x] Every user story has an independent test that names a fixture and a concrete assertion.
- [x] Every user story has acceptance scenarios in Given/When/Then form.
- [x] Every acceptance scenario is observable from output, exit code, or the built site. No scenario
  says "works correctly" or "is fast" without a bound.
- [x] Functional requirements are numbered and grouped, and every one is referenced by at least one
  acceptance scenario or success criterion.
- [x] Edge cases cover slug normalisation and collisions, version directory naming, mixed layouts,
  missing entry pages, escaping includes, missing heading ids, invalid encoding, case-only path
  collisions, sub-path hosting, pagination, mid-build deletion, duplicate discovery, search
  truncation, and stale output.
- [x] Failure behaviour is specified per project and per page, with a closed status set and a closed
  reason-code set.
- [x] Exit codes are specified and mapped to outcomes.
- [x] Determinism is specified as a testable property (identical bytes across runs) rather than as an
  aspiration.
- [x] The future Markdown stage is recorded as planned follow-on work without changing the version 1
  behaviour.

## Testability

- [x] Every success criterion is measurable and names its verification method.
- [x] Ordering rules are documented, so "deterministic" is checkable rather than subjective.
- [x] Contrast is bounded numerically (at least 4.5:1) rather than described as "accessible".
- [x] Timing criteria state the hardware class and the workload.
- [x] The CLI contract criteria can be checked by invoking the command in a clean container.

## Scope discipline

- [x] The converter package is explicitly out of scope and is not modified.
- [x] The standalone action repository and the site repository's workflows are explicitly follow-on.
- [x] The specification does not prescribe file layout, class names, or algorithms.
- [x] No requirement depends on a new runtime binary, a third-party service, or an external origin.

## Ambiguity resolved

- [x] Site configuration format is `site.json` with a published JSON Schema, with no new Composer
  dependency.
- [x] Unversioned documentation publishes at `/slug/latest/` with `/slug/` redirecting there.
- [x] A documentation tree containing only Markdown fails the project, excludes it from the site, and
  makes the build exit 1.

## Remaining open questions

These are recorded in `plan.md` under "Assumptions pending confirmation" and were reported to the
user as a single numbered list. None blocks writing the plan or the task list.

- [ ] Whether creating the `doc-target` organization property is a task in this repository or an
  operator setup step.
- [ ] Search extract limit and index granularity.
- [ ] Default concurrency.
- [ ] Whether the site repository's workflow YAML belongs in this specification.
- [ ] Whether Markdown migration of `message-bus` and `messagebus-event` is tracked here.

## Notes

The specification deliberately stops short of naming the converter extension point. That decision
belongs to `plan.md`, where it is justified against the converter's public surface, and it keeps the
specification stable if the extension point changes.
