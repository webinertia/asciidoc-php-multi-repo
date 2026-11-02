# Contract: Publish Dispatch

**Version**: 1.0.0

**Consumers**: any repository in the `webinertia` organization that publishes documentation to the
docs site and wants the site to refresh before the next scheduled run.

**Provider**: the `repository_dispatch` receiver in
`webinertia/docs.webinertia.net/.github/workflows/publish-docs.yml`.

This contract is one-directional. A consumer sends a request; the receiver acknowledges it by starting
a publish run. No response body carries meaning, and no consumer may depend on the outcome of a
dispatch beyond "the request was accepted".

## Endpoint

```
POST /repos/webinertia/docs.webinertia.net/dispatches
```

## Event type

```
docs-publish
```

Exactly one event type is accepted. A dispatch with any other event type starts no run and is silently
ignored by the receiver, because no workflow listens for it.

## Payload

The payload is advisory. It is recorded in the publish run summary for correlation, and it is never
used to select, restrict, or extend the set of projects built. The set of projects is determined by
the organization custom property `doc-target` at the moment the run executes.

| Field | Type | Required | Meaning |
|-------|------|----------|---------|
| `repository` | string | no | The dispatching repository's full name, `owner/repo` |
| `ref` | string | no | The ref or commit the consumer wants reflected. Recorded only; the site builds the default branch of every discovered repository |
| `source` | string | no | Free-text name of the consumer workflow or job that sent the request |

Rules:

- All fields are optional. A dispatch with an empty payload is valid and starts a normal publish.
- An unknown field is ignored. This is what makes additive changes to this contract safe without a
  version field.
- A field with an unexpected type is ignored and recorded as unusable rather than failing the run.
- No field may carry documentation content. The payload is metadata, not input.

## Credential

The consumer must present a GitHub App installation token for an App installed on
`webinertia/docs.webinertia.net` with the repository permission Contents, Read and write. The token
must be narrowed to the site repository.

The consumer must not use:

- the discovery token, which carries Organization, Custom properties, Read and is deliberately unable
  to write anything;
- a long-lived personal access token stored in the consumer repository.

A token minted per run from an organization secret is the intended path:

```yaml
- id: docs-publish-token
  uses: actions/create-github-app-token@<pinned-sha>
  with:
    app-id: ${{ vars.DOCS_PUBLISH_APP_ID }}
    private-key: ${{ secrets.DOCS_PUBLISH_APP_PRIVATE_KEY }}
    owner: webinertia
    repositories: docs.webinertia.net
```

## Consumer snippet

Optional. A repository appears on the site because `doc-target` is true, not because it dispatches. The
snippet only reduces latency.

```yaml
- id: docs-publish-token
  uses: actions/create-github-app-token@<pinned-sha>
  with:
    app-id: ${{ vars.DOCS_PUBLISH_APP_ID }}
    private-key: ${{ secrets.DOCS_PUBLISH_APP_PRIVATE_KEY }}
    owner: webinertia
    repositories: docs.webinertia.net

- name: Request a docs site publish
  continue-on-error: true
  env:
    GH_TOKEN: ${{ steps.docs-publish-token.outputs.token }}
    REPOSITORY: ${{ github.repository }}
    REF: ${{ github.sha }}
    SOURCE: ${{ github.workflow }}
  run: |
    gh api --method POST \
      repos/webinertia/docs.webinertia.net/dispatches \
      -f event_type=docs-publish \
      -f client_payload[repository]="$REPOSITORY" \
      -f client_payload[ref]="$REF" \
      -f client_payload[source]="$SOURCE"
```

`continue-on-error: true` is part of the contract, not a convenience. A failed notification must not
fail the consumer's own workflow, because the nightly run will publish the change anyway.

## Receiver behaviour

1. The receiver starts a publish run for the complete discovered set, exactly as a scheduled run
   would. There is no reduced or targeted build.
2. The receiver records the payload in the run summary, including whether the named repository
   appeared in the discovered set.
3. When the named repository is not in the discovered set, the run proceeds and the summary states
   that the dispatch was a no-op for that repository, with `doc-target` named as the reason. This is
   the signal that catches the most likely onboarding mistake: a snippet added without the property.
4. When a publish is already running, the request waits rather than interrupting it. When more than
   one request is waiting, at most one remains waiting and the rest are marked superseded by the
   platform. No content is lost, because every run rebuilds everything.
5. The receiver publishes only when the built tree differs from the published tree. A dispatch that
   finds nothing new produces no commit.

## Guarantees to consumers

- A dispatch never causes content to be published that `doc-target` does not select.
- A dispatch never publishes content from a repository other than through the normal discovery path.
- A dispatch never fails the consumer's workflow.
- A dispatch never requires a long-lived secret in the consumer repository.
- A dispatch never causes a partial tree to be published.

## Non-guarantees

- A dispatch does not guarantee a commit. An unchanged organization produces a no-op.
- A dispatch does not guarantee immediate execution. It queues behind a running publish.
- A dispatch does not guarantee that the named repository is published.
- A dispatch does not carry a response. The consumer learns the outcome from the site repository's
  Actions tab.

## Stability

- The event type is stable. Adding a second event type is an additive change.
- Payload fields may be added. Consumers must tolerate fields they do not recognise, and the receiver
  ignores fields it does not recognise.
- Removing a payload field, changing a field's meaning, or changing the event type is a breaking change
  and requires a version bump of this document and a migration note in the operator guide.
