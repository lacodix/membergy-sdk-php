"""Validate human backend gate evidence and snapshot it for all CI lanes."""

import json
import os
import re
import sys


def attestation(sha, raw):
    if not re.fullmatch(r"[0-9a-f]{40}", sha):
        raise ValueError("Backend SHA must be a full commit SHA.")
    value = json.loads(raw)
    if not isinstance(value, dict) or value.get("sha") != sha:
        raise ValueError("Backend attestation SHA must match MEMBERGY_BACKEND_SHA.")
    if value.get("review") != "passed" or value.get("serial_tests") != "passed":
        raise ValueError("Backend independent review and exclusive serial tests must be attested as passed.")
    evidence = value.get("evidence")
    if not isinstance(evidence, str) or not evidence.strip():
        raise ValueError("Provide non-secret backend review/serial-test evidence.")
    return value


def snapshot(sha, raw):
    value = attestation(sha, raw)
    # JSON escapes evidence newlines so they cannot create extra Actions outputs.
    return f"backend_sha={sha}\nbackend_attestation={json.dumps(value, separators=(',', ':'))}\n"


def main():
    try:
        sha = os.environ.get("BACKEND_SHA", "")
        raw = os.environ.get("BACKEND_GATE_ATTESTATION", "")
        if sys.argv[1:] == ["snapshot"]:
            with open(os.environ["GITHUB_OUTPUT"], "a") as output:
                output.write(snapshot(sha, raw))
        elif not sys.argv[1:]:
            attestation(sha, raw)
        else:
            raise ValueError("Unknown backend gate mode.")
    except (ValueError, KeyError, OSError) as error:
        print(f"Backend gate rejected: {error}", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
