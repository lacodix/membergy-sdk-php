#!/usr/bin/env bash
set -euo pipefail

fail() { printf '%s\n' "$1" >&2; exit 1; }

[[ ${GITHUB_EVENT_NAME:-} == push ]] || fail 'Release requires a push.'
[[ ${GITHUB_REF:-} == refs/heads/main ]] || fail 'Release requires a push to main.'
[[ ${SDK_SHA:-} =~ ^[0-9a-f]{40}$ ]] || fail 'SDK SHA must be a full commit SHA.'
[[ ${BACKEND_SHA:-} =~ ^[0-9a-f]{40}$ ]] || fail 'Backend SHA must be a full commit SHA.'
python3 .github/scripts/backend-gate.py
[[ ${GITHUB_REPOSITORY:-} =~ ^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$ ]] || fail 'Invalid SDK repository.'

[[ $(git rev-parse HEAD) == "$SDK_SHA" ]] || fail 'Checkout does not match the pushed SDK SHA.'
main_sha=$(gh api "repos/$GITHUB_REPOSITORY/git/ref/heads/main" --jq '.object.sha')
[[ $main_sha == "$SDK_SHA" ]] || fail 'Stale SDK ref: only current main can release.'

printf 'Release guards passed at SDK %s and backend %s.\n' "$SDK_SHA" "$BACKEND_SHA"
