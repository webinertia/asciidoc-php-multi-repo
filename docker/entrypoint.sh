#!/bin/sh
# ---------------------------------------------------------------------------
# Container entrypoint for the site generator.
#
# The image exposes three verbs rather than the CLI's whole command set,
# because those are the three things a container is asked to do: render the
# site once, rebuild it as the sources change, and serve the result.
#
#   render  the CLI's `build` command
#   watch   the CLI's `watch` command
#   serve   the CLI's `serve` command
#
# Anything after the verb is passed through to the CLI unchanged, so
# `render --project versioned` works. A leading option (`--version`,
# `--help`) is handed straight to the CLI, which is what makes
# `docker run --rm <image> --version` print the package version.
#
# Any other first argument is a usage error and exits 2, the CLI's
# configuration-error code, so a typo cannot look like a successful render.
#
# Two environment variables are honoured:
#   ASCIIDOC_MANIFEST     passed to `render` and `watch` as --manifest, which is
#                         how an offline or CI run avoids the discovery API.
#   ASCIIDOC_SERVE_HOST   bind address for `watch` and `serve`; defaults to
#                         0.0.0.0 because 127.0.0.1 is unreachable from outside
#                         the container.
# ---------------------------------------------------------------------------
set -eu

BIN="${ASCIIDOC_BIN:-/app/bin/asciidoc-php-multi-repo}"

usage() {
    cat >&2 <<'USAGE'
usage: <image> <command> [options]

commands:
  render   discover, fetch, render, and write the site (the CLI's `build`)
  watch    rebuild affected projects on change and serve the result
  serve    serve an already built output directory

options after the command are passed to the CLI unchanged.
run `<image> --help` for the CLI's own help, or `<image> --version` for the
package version.
USAGE
}

if [ "$#" -eq 0 ]; then
    usage
    exit 2
fi

# `render` and `watch` both discover repositories, and both accept a manifest in
# place of the hosted API. Passing it through the environment keeps the compose
# command free of a conditional argument, which compose interpolation cannot
# express.
dispatch() {
    verb="$1"
    shift

    if [ -n "${ASCIIDOC_MANIFEST:-}" ]; then
        exec "$BIN" "$verb" --manifest "$ASCIIDOC_MANIFEST" "$@"
    fi

    exec "$BIN" "$verb" "$@"
}

case "$1" in
    render)
        shift
        dispatch build "$@"
        ;;
    watch)
        shift
        export ASCIIDOC_SERVE_HOST="${ASCIIDOC_SERVE_HOST:-0.0.0.0}"
        dispatch watch "$@"
        ;;
    serve)
        shift
        export ASCIIDOC_SERVE_HOST="${ASCIIDOC_SERVE_HOST:-0.0.0.0}"
        exec "$BIN" serve "$@"
        ;;
    -*)
        exec "$BIN" "$@"
        ;;
    *)
        echo "entrypoint: unknown command \"$1\"" >&2
        usage
        exit 2
        ;;
esac
