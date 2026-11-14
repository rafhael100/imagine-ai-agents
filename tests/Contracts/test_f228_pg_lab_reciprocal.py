"""F228: SHA-pinned CRM now includes a CI-only PG signed nonce candidate."""
from pathlib import Path
import unittest

ROOT=Path(__file__).resolve().parents[2]


class F228PgLabReciprocal(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.wf=(ROOT/".github/workflows/r01-dev-integrity.yml").read_text()
        cls.check=(ROOT/"tools/check_f224_reciprocal_pins.py").read_text()
        cls.gate=(ROOT/"tools/run_r01_offline_quality_gate.sh").read_text()
        cls.doc=(ROOT/"docs/AIA-FT01-F228-CI-FONTE-PG-CANONICA.md").read_text()

    def test_original_crm_sha_is_checked_not_mutable_branch(self):
        sha="d7c7aacc766c7ade4c65dcd211ed4569442144a9"
        self.assertIn("ref: "+sha,self.wf)
        self.assertIn('"'+sha+'"',self.wf)
        self.assertIn('PINNED_CRM = "'+sha+'"',self.check)
        self.assertIn(
            'REVIEWED_AGENTS_BASE = "5a2c8af18448aeebfe8eda0d7570f34ffbe24611"',
            self.check
        )
        self.assertNotIn("ref: f44fe65745037d39f57e894ef32a25a9efa07bc4",self.wf)

    def test_checker_requires_fresh_pg_lab_sources_after_checkout(self):
        for token in ("CRM_PG_NONCE","CRM_PG_BRIDGE","CRM_PG_TEST",
                      "CRM_PG_LAB_ONLY_SOURCE_DRIFT",
                      "GITHUB_ACTIONS",
                      "ON CONFLICT (issuer,audience,nonce) DO NOTHING",
                      "test_signed_lab_proof_fails_closed_when_pg_backend_table_absent"):
            self.assertIn(token,self.check)
        for command in (
            "php -l _synthetic_crm_fixture/app/Support/Synthetic/AiaPgNonceCandidateLedger.php",
            "php -l _synthetic_crm_fixture/app/Support/Synthetic/AiaPgHmacLabBridge.php",
            "python3 tools/check_f224_reciprocal_pins.py",
        ):
            self.assertIn(command,self.wf)
        self.assertLess(self.wf.index("git -C _synthetic_crm_fixture rev-parse HEAD"),
                        self.wf.index("php -l _synthetic_crm_fixture/app/Support/Synthetic/AiaPgNonceCandidateLedger.php"))

    def test_governance_and_offline_gate(self):
        self.assertIn("test_f228_pg_lab_reciprocal.py",self.gate)
        for token in ("NO-GO","14 gates","13 cenários","CRM #57",
                      "879","110","main","SHA"):
            self.assertIn(token,self.doc)
        for forbidden in ("curl_init(", "requests.get(",
                          "subprocess.run(", "getenv(", "secrets."):
            self.assertNotIn(forbidden,self.check)
        self.assertIn("contents: read",self.wf)
        self.assertEqual(self.wf.count("persist-credentials: false"),2)


if __name__=="__main__":
    unittest.main()
