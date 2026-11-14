"""F224: a genuine, cross-repository reciprocal source proof is mandatory."""
from pathlib import Path
import unittest
import sys

ROOT=Path(__file__).resolve().parents[2]
sys.path.insert(0,str(ROOT/"tools"))
import check_f224_reciprocal_pins as checks


class F224Reciprocity(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.workflow=(ROOT/".github/workflows/r01-dev-integrity.yml").read_text()
        cls.gate=(ROOT/"tools/run_r01_offline_quality_gate.sh").read_text()
        cls.source=(ROOT/"tools/check_f224_reciprocal_pins.py").read_text()
        cls.doc=(ROOT/"docs/AIA-FT01-F224-PINS-BILATERAIS.md").read_text()

    def test_workflow_pins_new_crm_and_executes_after_checkout(self):
        self.assertIn(checks.PINNED_CRM,self.workflow)
        self.assertIn("python3 tools/check_f224_reciprocal_pins.py",self.workflow)
        self.assertLess(
            self.workflow.index("git -C _synthetic_crm_fixture rev-parse HEAD"),
            self.workflow.index("python3 tools/check_f224_reciprocal_pins.py"))
        self.assertIn("test_f224_reciprocal_pins.py",self.gate)

    def test_script_does_not_pass_when_fixture_is_absent(self):
        with self.assertRaises(checks.ReciprocalPinError):
            checks.check()
        self.assertIn(checks.REVIEWED_AGENTS_BASE,self.source)
        self.assertIn("CRM_RECIPROCAL_SCRIPT_DRIFT",self.source)
        self.assertIn("CRM_OWNER_BOUNDARY_DRIFT",self.source)

    def test_rejects_fake_source_and_missing_pins(self):
        from unittest.mock import patch
        with patch.object(checks,"CRM_WORKFLOW",
                          ROOT/"tests/Contracts/not-a-real-crm.yml"):
            with self.assertRaises(checks.ReciprocalPinError):
                checks.check()
        self.assertIn("AGENTS_CRM_PIN_MISMATCH",self.source)
        self.assertIn("CRM_AGENTS_PIN_MISMATCH",self.source)

    def test_governance_keeps_sources_read_only_and_no_go(self):
        for token in ("CRM #57","NO-GO","14 gates","13 cenários",
                      "879","110","main","SHA"):
            self.assertIn(token,self.doc)
        for banned in ("curl_init(", "urllib.request", "requests.get(",
                       "subprocess.run(", "os.system(", "getenv(", "secrets."):
            self.assertNotIn(banned,self.source)


if __name__=="__main__":
    unittest.main()
