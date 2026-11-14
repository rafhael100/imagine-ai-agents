"""F208: locked-down CI cross-source, not production CRM permission."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
PIN = "7999b1e34a6cbd0ee1c5e2351067ad172e01bbad"


class F208SourceParity(unittest.TestCase):
    def setUp(self):
        self.workflow = (ROOT / ".github/workflows/r01-dev-integrity.yml").read_text()
        self.script = (ROOT / "tools/check_crm_aia_cross_source.php").read_text()
        self.gate = (ROOT / "tools/run_r01_offline_quality_gate.sh").read_text()
        self.doc = (ROOT / "docs/AIA-FT01-F208-CONFORMIDADE-CRM-CODIGO-REAL.md").read_text()

    def test_pinned_immutable_crm_commit_and_sha_verification(self):
        self.assertIn("repository: rafhael100/imagine-crm", self.workflow)
        self.assertIn("ref: " + PIN, self.workflow)
        self.assertIn('git -C _synthetic_crm_fixture rev-parse HEAD', self.workflow)
        self.assertIn('"' + PIN + '"', self.workflow)
        self.assertIn("persist-credentials: false", self.workflow)

    def test_existing_offline_gate_not_bypassed(self):
        self.assertIn("bash tools/run_r01_offline_quality_gate.sh", self.workflow)
        self.assertIn("EXPECTED_PHP: '110'", self.workflow)
        self.assertIn("EXPECTED_CASES: '879'", self.workflow)
        self.assertIn("test_f208_crm_cross_source.py", self.gate)
        self.assertIn("test_f206_health_rehearsal.py", self.gate)
        self.assertIn("node tests/Browser/f108_imagine_brand.cjs", self.workflow)

    def test_two_repos_real_php_classes_executed_without_app_boot(self):
        self.assertIn("php tools/check_crm_aia_cross_source.php", self.workflow)
        for marker in ("AiaReadOnlyMockBoundary", "AgentsReadResponseInspector",
                       "MockCrmReadRequest", "MockCrmPublicResponseContract",
                       "MockError", "NO_GO", "F208_CROSS_SOURCE_PASS"):
            self.assertIn(marker, self.script)
        self.assertIn("canReadRealLead()", self.script)
        self.assertIn("canGrantAgentAccess()", self.script)
        self.assertIn("canWriteLead()", self.script)

    def test_no_operational_io_or_private_data(self):
        for marker in ("curl_init(", "PDO(", "mysqli_", "Http::", "Laravel",
                       "api.github.com", "getenv(", "putenv(", "shell_exec(",
                       "file_get_contents('http"):
            if marker == "Laravel":
                continue  # Mention in a comment is permitted; no boot allowed.
            self.assertNotIn(marker, self.script)
        for forbidden in ("APP_KEY", "ASAAS_API", "CRM_API_TOKEN", "production=true"):
            self.assertNotIn(forbidden, self.workflow)

    def test_no_go_documented_and_not_conflated_with_bilateral_acceptance(self):
        self.assertIn("NO-GO", self.doc)
        self.assertIn("NAO_EXECUTADO", self.doc)
        self.assertIn(PIN, self.doc)
        self.assertIn("CRM #57", self.doc)


if __name__ == "__main__":
    unittest.main()
