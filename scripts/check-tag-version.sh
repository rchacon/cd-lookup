#!/usr/bin/env bash
# Usage: check-tag-version.sh <tag-name> <tag-prefix> <plugin-header-path> [git-ref]
#
# Fails if the version implied by <tag-name> (after stripping <tag-prefix>)
# doesn't match the `Version:` field in <plugin-header-path>'s plugin
# header docblock as of [git-ref] (defaults to HEAD). Shared by the
# wp-release workflow and the local pre-push hook so the check logic
# lives in one place. When run as a GitHub Actions step, also emits
# `version=<tag-derived version>` to $GITHUB_OUTPUT so callers don't need
# to re-derive it themselves.
set -euo pipefail

tag_name="$1"
tag_prefix="$2"
plugin_header_path="$3"
git_ref="${4:-HEAD}"

tag_version="${tag_name#"$tag_prefix"}"
plugin_version=$(git show "${git_ref}:${plugin_header_path}" | sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([^[:space:]]*\).*/\1/p' | head -n1)

if [ -z "$plugin_version" ]; then
  echo "error: could not find a 'Version:' header line in ${plugin_header_path} (at ${git_ref})" >&2
  exit 1
fi

if [ "$tag_version" != "$plugin_version" ]; then
  echo "error: tag '${tag_name}' implies version '${tag_version}', but ${plugin_header_path} (at ${git_ref}) has version '${plugin_version}'" >&2
  exit 1
fi

echo "OK: tag '${tag_name}' matches ${plugin_header_path}'s version '${plugin_version}'"

if [ -n "${GITHUB_OUTPUT:-}" ]; then
  echo "version=${tag_version}" >> "$GITHUB_OUTPUT"
fi
