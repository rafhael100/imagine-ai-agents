"""F196: strict parity between PHP F190 and the browser-only transition view."""
import json
from pathlib import Path
import re
import shutil
import subprocess
import unittest

ROOT = Path(__file__).resolve().parents[2]
HTML = ROOT / "examples/f108-laboratorio-navegavel.html"
SMOKE = ROOT / "tests/Browser/f108_smoke.cjs"
MARKER = r'<script type="application/json" id="f196-transition-data">([^<]+)</script>'


def embedded():
    page = HTML.read_text(encoding="utf-8")
    matches = re.findall(MARKER, page)
    if len(matches) != 1:
        raise AssertionError("one embedded F196 fixture required")
    return json.loads(matches[0])


class F196Tests(unittest.TestCase):
    def test_closed_contract_and_no_business_identifiers(self):
        data = embedded()
        self.assertEqual(set(data), {"contract", "paused", "resumed",
                         "operational_decision", "real_authorization",
                         "external_calls", "write_calls"})
        self.assertEqual(data["contract"], "SYNTHETIC_QUEUE_PAUSE_REHEARSAL_V1")
        self.assertEqual(data["operational_decision"], "NO_GO")
        self.assertIs(data["real_authorization"], False)
        self.assertEqual(data["external_calls"], 0)
        self.assertEqual(data["write_calls"], 0)
        for banned in ("run_ref", "claim_ref", "reviewer_ref", "decision_ref",
                       "tenant_id", "access_token", "request_ref"):
            self.assertNotIn(banned, json.dumps(data).lower())

    def test_transition_never_resolves_unknown_job(self):
        data = embedded()
        paused, resumed = data["paused"], data["resumed"]
        self.assertEqual((paused["admission"]["state"], resumed["admission"]["state"]),
                         ("PAUSED", "OPEN"))
        self.assertEqual((paused["queue"]["pending"], resumed["queue"]["pending"]), (1, 0))
        self.assertEqual((paused["queue"]["completed"], resumed["queue"]["completed"]), (0, 1))
        for stage in (paused, resumed):
            self.assertEqual(stage["queue"]["indeterminate"], 1)
            self.assertEqual(stage["review"]["reviewed_unresolved"], 1)
            self.assertEqual(stage["operational_decision"], "NO_GO")

    @unittest.skipUnless(shutil.which("php"), "PHP missing")
    def test_embedded_payload_exactly_matches_php_f190(self):
        out = subprocess.run(["php", "examples/f190-queue-admission-demo.php"],
                             cwd=ROOT, capture_output=True, text=True, timeout=15)
        self.assertEqual(out.returncode, 0, out.stderr)
        self.assertEqual(embedded(), json.loads(out.stdout))

    def test_visual_controls_are_local_and_accessible(self):
        page = HTML.read_text(encoding="utf-8")
        self.assertIn('id="queue-transition-panel"', page)
        self.assertIn('id="queue-transition-paused" aria-pressed="true"', page)
        self.assertIn('id="queue-transition-resumed" aria-pressed="false"', page)
        self.assertIn('aria-live="polite"', page)
        self.assertIn("F196_INVALID_SNAPSHOT_SCHEMA", page)
        self.assertIn("F196_UNSAFE_FLAGS", page)
        self.assertIn("NO-GO", page)

    def test_f182_and_f184_remain_independent(self):
        page = HTML.read_text(encoding="utf-8")
        self.assertEqual(page.count('id="f183-supervision-data"'), 1)
        self.assertEqual(page.count('id="f196-transition-data"'), 1)
        self.assertIn('get("supervision-decision").textContent="NO-GO"', page)
        self.assertIn("drawReviewSimulation()", page)
        self.assertNotIn("fetch(", page)
        self.assertNotIn("XMLHttpRequest", page)

    def test_browser_smoke_checks_state_switch(self):
        code = SMOKE.read_text(encoding="utf-8")
        for required in ("f196-transition-data", "queue-transition-resumed",
                         "queue-transition-paused", "F182 immutable"):
            self.assertIn(required, code)


if __name__ == "__main__":
    unittest.main()
