"""F202: strict parity between PHP F201 and six-stage visual triage fixture."""
import json
import pathlib
import re
import shutil
import sys
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[2]
HTML = ROOT / "examples/f108-laboratorio-navegavel.html"
SMOKE = ROOT / "tests/Browser/f108_smoke.cjs"
sys.path.insert(0, str(ROOT / "tools"))
import verify_f201_triage


def embedded():
    page = HTML.read_text(encoding="utf-8")
    markers = re.findall(
        r'<script type="application/json" id="f202-triage-data">([^<]+)</script>',
        page,
    )
    if len(markers) != 1:
        raise AssertionError("exactly one F202 fixture required")
    return json.loads(markers[0])


class F202Tests(unittest.TestCase):
    def test_embedded_payload_is_closed_and_deterministic(self):
        self.assertIs(verify_f201_triage.verify(embedded()), True)
        self.assertEqual(list(embedded()["stages"]), list(verify_f201_triage.STATES))

    @unittest.skipUnless(shutil.which("php"), "PHP missing")
    def test_full_payload_matches_real_php_f201(self):
        self.assertEqual(embedded(), verify_f201_triage.run_fixture())

    def test_all_six_stages_have_accessible_controls(self):
        page = HTML.read_text(encoding="utf-8")
        self.assertIn('role="group" aria-label="Etapas da triagem fictícia"', page)
        for stage in verify_f201_triage.STATES:
            self.assertEqual(page.count('id="triage-stage-' + stage + '"'), 1)
        for value in ("state", "next", "pending", "reserved", "completed",
                      "unknown", "admission", "unresolved"):
            self.assertEqual(page.count('id="triage-view-' + value + '"'), 1)

    def test_inert_visualization_keeps_no_go(self):
        page = HTML.read_text(encoding="utf-8")
        self.assertIn("F202_TRIAGE_CONTRACT_DENIED", page)
        self.assertIn("F202_TRIAGE_STAGE_DENIED", page)
        self.assertIn("F202_TRIAGE_COUNTER_DENIED", page)
        self.assertIn("triageFlags(info)", page)
        self.assertIn("operational_decision", page)
        self.assertIn("Nenhuma etapa equivale", page)
        self.assertNotIn("fetch(", page)
        self.assertNotIn("XMLHttpRequest", page)

    def test_output_is_aggregate_and_redacted(self):
        data = embedded()
        body = json.dumps(data)
        for forbidden in ("run_ref", "claim_ref", "reviewer_ref", "request_ref",
                          "nonce", "SYNTHETIC_F201_RUN_", "SYNTHETIC_F201_REQ_"):
            self.assertNotIn(forbidden, body)
        self.assertEqual(data["stages"]["resumed"]["queue"]["indeterminate"], 1)
        self.assertEqual(data["stages"]["resumed"]["review"]["reviewed_unresolved"], 1)

    def test_existing_supervision_snapshots_are_unchanged(self):
        page = HTML.read_text(encoding="utf-8")
        for marker in ('id="f183-supervision-data"',
                       'id="f196-transition-data"', 'id="f202-triage-data"'):
            self.assertEqual(page.count(marker), 1)
        self.assertIn('get("supervision-decision").textContent="NO-GO"', page)

    def test_dom_smoke_exercises_full_sequence_and_isolation(self):
        smoke = SMOKE.read_text(encoding="utf-8")
        for stage in verify_f201_triage.STATES:
            if stage != "empty":
                self.assertIn('byId("triage-stage-' + stage + '").click()', smoke)
        self.assertIn('"F182 unchanged after triage"', smoke)
        self.assertIn('"triage cannot authorize"', smoke)


if __name__ == "__main__":
    unittest.main()
