#!/usr/bin/env bash
set -euo pipefail
set +x

[[ ${BACKEND_SHA:-} =~ ^[0-9a-f]{40}$ ]] || { printf 'A full backend SHA is required.\n' >&2; exit 1; }
[[ -n ${MEMBERGY_BACKEND_READ_TOKEN:-} ]] || { printf 'MEMBERGY_BACKEND_READ_TOKEN is required.\n' >&2; exit 1; }
: "${RUNNER_TEMP:?}"

umask 077
askpass=$(mktemp "$RUNNER_TEMP/membergy-askpass.XXXXXX")
trap 'rm -f "$askpass"' EXIT
cat > "$askpass" <<'ASKPASS'
#!/usr/bin/env bash
set +x
case "$1" in
  *Username*) printf '%s\n' 'oauth2' ;;
  *Password*) printf '%s\n' "$MEMBERGY_BACKEND_READ_TOKEN" ;;
  *) exit 1 ;;
esac
ASKPASS
chmod 700 "$askpass"
export GIT_ASKPASS="$askpass" GIT_TERMINAL_PROMPT=0
export GIT_CONFIG_NOSYSTEM=1 GIT_CONFIG_GLOBAL=/dev/null
unset GIT_TRACE GIT_TRACE_PACKET GIT_TRACE_CURL GIT_TRACE_CURL_NO_DATA GIT_CURL_VERBOSE

backend_root="$RUNNER_TEMP/membergy-backend"
# Fail rather than reusing any pre-existing tree; never substitute SDK fixtures.
mkdir "$backend_root"
git -C "$backend_root" init --quiet
git -C "$backend_root" -c credential.helper= -c core.hooksPath=/dev/null \
  -c protocol.allow=never -c protocol.https.allow=always \
  fetch --quiet --depth=1 https://gitlab.lacodix.de/lacodix/membergy.git "$BACKEND_SHA"
unset MEMBERGY_BACKEND_READ_TOKEN GIT_ASKPASS
git -C "$backend_root" -c core.hooksPath=/dev/null checkout --quiet --detach FETCH_HEAD
[[ $(git -C "$backend_root" rev-parse HEAD) == "$BACKEND_SHA" ]] || { printf 'Backend SHA mismatch.\n' >&2; exit 1; }
