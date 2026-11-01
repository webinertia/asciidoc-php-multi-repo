# {{PACKAGE_NAME}}

[![PHP Version](https://img.shields.io/packagist/php-v/{{PACKAGE_NAME}})](https://packagist.org/packages/{{PACKAGE_NAME}})
[![Latest Version](https://img.shields.io/packagist/v/{{PACKAGE_NAME}})](https://packagist.org/packages/{{PACKAGE_NAME}})
[![License](https://img.shields.io/github/license/{{ORG}}/{{REPO}})](LICENSE)
[![Required CI](https://github.com/{{ORG}}/{{REPO}}/actions/workflows/required/webinertia/.github/.github/workflows/org-required-ci.yml/badge.svg)](https://github.com/{{ORG}}/{{REPO}}/actions/workflows/required/webinertia/.github/.github/workflows/org-required-ci.yml)
[![codecov](https://codecov.io/gh/{{ORG}}/{{REPO}}/graph/badge.svg)](https://codecov.io/gh/{{ORG}}/{{REPO}})
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2F{{ORG}}%2F{{REPO}}%2F{{DEFAULT_BRANCH}})](https://dashboard.stryker-mutator.io/reports/github.com/{{ORG}}/{{REPO}}/{{DEFAULT_BRANCH}})

Placeholders: `{{PACKAGE_NAME}}` = composer name (owner/package), `{{ORG}}` =
GitHub org, `{{REPO}}` = GitHub repo, `{{DEFAULT_BRANCH}}` = default branch
(e.g. `0.1.x`). The CI and codecov badge URLs carry no `?branch=` parameter so
they always point at the default branch. The Stryker badge URL embeds the branch
segment - update it in both the badge URL and the dashboard link whenever the
default branch changes.

The CI badge uses GitHub's synthetic route for the required workflow. Its file
lives in the config repository, so the route spells out those coordinates as
`required/<owner>/<config-repo>/<path>`:

```text
https://github.com/{{ORG}}/{{REPO}}/actions/workflows/required/webinertia/.github/.github/workflows/org-required-ci.yml/badge.svg
```

Do not point the badge at `continuous-integration.yml` - a consumer repository
has no such file, so that URL 404s. The required-route URL is the one GitHub's
own "Create status badge" dialog produces for that workflow.
