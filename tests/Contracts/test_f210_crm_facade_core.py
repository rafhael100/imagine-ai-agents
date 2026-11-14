"""F210: same-process actual Agents PHP facade and pinned CRM Core witness."""
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]
WORKFLOW = ROOT / ".github/workflows/r01-dev-integrity.yml"
SCRIPT = ROOT / "tools/check_crm_aia_real_facade_core.php"


class F210CrossCore(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.workflow = WORKFLOW.read_text(encoding="utf-8")
        cls.script = SCRIPT.read_text(encoding="utf-8")
        cls.gate = (ROOT / "tools/run_r01_offline_quality_gate.sh").read_text()
        cls.doc = (ROOT / "docs/AIA-FT01-F210-FACHADA-CORE-CRM.md").read_text()

    def test_real_agent_facade_probe_and_actual_crm_core_are_used(self):
        for name in ("op01_php_public_response_probe.php",
                     "AgentsReadResponseInspector",
                     "AiaReadOnlyMockBoundary",
                     "MockCrmReadPublicFacade",
                     "SYNTHETIC_OP01_PUBLIC_RESPONSE_PROBE_V1"):
            if name == "MockCrmReadPublicFacade":
                self.assertIn(name, (ROOT / "tools/op01_php_public_response_probe.php").read_text())
            else:
                self.assertIn(name, self.script)

    def test_cannot_bypass_pinned_crm_sha_or_gate(self):
        self.assertIn("php tools/check_crm_aia_real_facade_core.php", self.workflow)
        self.assertIn("test_f210_crm_facade_core.py", self.gate)
        self.assertIn("EXPECTED_CASES: '879'", self.workflow)
        self.assertIn("7999b1e34a6cbd0ee1c5e2351067ad172e01bbad", self.workflow)
        self.assertLess(self.workflow.index("bash tools/run_r01_offline_quality_gate.sh"),
                        self.workflow.index("php tools/check_crm_aia_real_facade_core.php"))
        self.assertLess(self.workflow.index("git -C _synthetic_crm_fixture rev-parse HEAD"),
                        self.workflow.index("php tools/check_crm_aia_real_facade_core.php"))

    def test_closed_six_real_cases_and_explicit_adversarial_payloads(self):
        for name in ("single_ref", "replay", "ordered_two_fields",
                     "ungranted", "cross_tenant", "unknown_field"):
            self.assertIn(name, self.script)
        self.assertIn("count($expected)", self.script)
        self.assertIn("mutations=10", self.script)
        self.assertIn("!$assessment->isSyntheticFormatConsistent()", self.script)
        self.assertIn("array_keys((array) $assessment) === ['reasonCode']", self.script)

    def test_no_operational_permission_or_secret(self):
        for name in ("canReadRealLead()", "canUseCustomerData()",
                     "canGrantAgentAccess()", "canWriteLead()",
                     "canExportLead()", "canEnableRoute()", "NO_GO"):
            self.assertIn(name, self.script)
        for unsafe in ("proc_open(", "shell_exec(", "curl_init(", "PDO(",
                       "mysqli_", "Http::", "file_get_contents('http",
                       "getenv(", "putenv(", "api.github.com"):
            self.assertNotIn(unsafe, self.script)

    def test_distinguishes_synthetic_success_from_pilot(self):
        for marker in ("NO-GO", "NAO_EXECUTADO", "CRM #57",
                       "SHA", "sem API", "F210"):
            self.assertIn(marker.casefold(), self.doc.casefold())


if __name__ == "__main__":
    unittest.main()
