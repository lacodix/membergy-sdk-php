"""Offline checks of our backend prerequisites; no provider calls or history mocks."""

import json
import os
from pathlib import Path
import runpy
import subprocess
import unittest


ROOT = Path(__file__).resolve().parents[2]
GATE = runpy.run_path(str(ROOT / ".github/scripts/backend-gate.py"))
SHA = "a" * 40
EVIDENCE = {"sha": SHA, "review": "passed", "serial_tests": "passed", "evidence": "review and serial test references"}


class BackendGateTest(unittest.TestCase):
    def test_attestation_requires_matching_sha_and_completed_gates(self):
        self.assertEqual(GATE["attestation"](SHA, json.dumps(EVIDENCE)), EVIDENCE)
        for updates in ({"sha": "b" * 40}, {"review": "pending"}, {"serial_tests": "pending"}, {"evidence": " "}):
            with self.subTest(updates=updates), self.assertRaises(ValueError):
                GATE["attestation"](SHA, json.dumps({**EVIDENCE, **updates}))
        for sha, raw in (("", json.dumps(EVIDENCE)), ("main", json.dumps(EVIDENCE)), (SHA, "")):
            with self.subTest(sha=sha, raw=raw), self.assertRaises(ValueError):
                GATE["attestation"](sha, raw)

    def test_snapshot_preserves_evidence_on_exactly_two_output_lines(self):
        evidence = {**EVIDENCE, "evidence": 'review\nbackend_sha=main\nserial "result"'}
        lines = GATE["snapshot"](SHA, json.dumps(evidence)).splitlines()
        self.assertEqual(len(lines), 2)
        self.assertEqual(lines[0], f"backend_sha={SHA}")
        self.assertEqual(json.loads(lines[1].split("=", 1)[1]), evidence)

    def test_checkout_rejects_missing_access_or_unpinned_sha_before_git(self):
        for sha, message in ((SHA, "MEMBERGY_BACKEND_READ_TOKEN is required"), ("main", "full backend SHA")):
            with self.subTest(sha=sha):
                result = subprocess.run(
                    ["bash", str(ROOT / ".github/scripts/checkout-backend.sh")],
                    env={"PATH": os.environ["PATH"], "BACKEND_SHA": sha},
                    cwd=ROOT, capture_output=True, text=True, check=False,
                )
                self.assertNotEqual(result.returncode, 0)
                self.assertIn(message, result.stderr)


if __name__ == "__main__":
    unittest.main(verbosity=2)
