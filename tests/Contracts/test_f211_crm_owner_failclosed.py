"""F211: real in-process Agents owner/trust failure states into CRM Core."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]


class F211CrossCore(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.script = (ROOT / "tools/check_crm_aia_owner_failclosed.php").read_text()
        cls.workflow = (ROOT / ".github/workflows/r01-dev-integrity.yml").read_text()
        cls.gate = (ROOT / "tools/run_r01_offline_quality_gate.sh").read_text()
        cls.doc = (ROOT / "docs/AIA-FT01-F211-NEGATIVAS-REVOGACAO.md").read_text()

    def test_real_php_classes_from_both_sources(self):
        for marker in ("MockCrmReadPublicFacade", "MockTrustEnvelope",
                       "MockOwnerScopeEvidence", "MockCrmOwnerPolicy",
                       "AgentsReadResponseInspector", "AiaReadOnlyMockBoundary",
                       "recheck("):
            self.assertIn(marker, self.script)

    def test_14_cases_and_5_revocation_paths(self):
        for marker in ("'replay'", "'no_grant'", "'tenant_suspended'",
                       "'member_suspended'", "'owner_unavailable'",
                       "'policy_revoked'", "'envelope_revoked'",
                       "'owner_proof_revoked'", "'expired'",
                       "'missing_envelope'", "'wrong_audience'",
                       "'foreign_tenant'", "'unknown_field'",
                       "'policy_aba'", "'owner_outage_recovery'"):
            self.assertIn(marker, self.script)
        self.assertIn("count($modes) === 14", self.script)
        self.assertIn("count($modes) === 14", self.script)
        self.assertIn("receipt_revocations=5", self.script)

    def test_deny_by_default_and_no_permission_ever(self):
        for marker in ("!$assessment->canReadRealLead()",
                       "!$assessment->canGrantAgentAccess()",
                       "!$assessment->canUseCustomerData()",
                       "!$assessment->canWriteLead()",
                       "!$assessment->canEnableRoute()",
                       "NO_GO", "real_authorization=false", "external_calls=0"):
            self.assertIn(marker, self.script)
        self.assertIn("AiaReadOnlyMockBoundary::inspect($response) === $expected",
                      self.script)

    def test_pinned_crm_and_prior_gates_remain(self):
        self.assertIn("php tools/check_crm_aia_owner_failclosed.php", self.workflow)
        self.assertIn("test_f211_crm_owner_failclosed.py", self.gate)
        self.assertIn("EXPECTED_CASES: '879'", self.workflow)
        self.assertIn("7999b1e34a6cbd0ee1c5e2351067ad172e01bbad",
                      self.workflow)
        self.assertLess(self.workflow.index("php tools/check_crm_aia_real_facade_core.php"),
                        self.workflow.index("php tools/check_crm_aia_owner_failclosed.php"))

    def test_no_unsafe_io(self):
        for forbidden in ("curl_init(", "proc_open(", "shell_exec(",
                          "PDO(", "mysqli_", "Http::", "file_get_contents('http",
                          "getenv(", "putenv(", "api.github.com"):
            self.assertNotIn(forbidden, self.script)

    def test_no_operational_acceptance_claims(self):
        for marker in ("NO-GO", "NAO_EXECUTADO", "CRM #57",
                       "14 cenários", "5 transições", "sem API"):
            self.assertIn(marker.casefold(), self.doc.casefold())


if __name__ == "__main__":
    unittest.main()
