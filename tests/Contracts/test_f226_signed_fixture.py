"""F226: SHA pinned CRM HMAC fixture verifies Agents bytes, never real IAM."""
from pathlib import Path
import unittest

ROOT=Path(__file__).resolve().parents[2]


class F226HmacFixture(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.wf=(ROOT/".github/workflows/r01-dev-integrity.yml").read_text()
        cls.check=(ROOT/"tools/check_f224_reciprocal_pins.py").read_text()
        cls.php=(ROOT/"tools/check_f226_signed_fixture.php").read_text()
        cls.gate=(ROOT/"tools/run_r01_offline_quality_gate.sh").read_text()
        cls.doc=(ROOT/"docs/AIA-FT01-F226-PROVA-HMAC-BILATERAL.md").read_text()

    def test_immutable_crm_ref_and_workflow_executes_actual_php(self):
        sha="d7c7aacc766c7ade4c65dcd211ed4569442144a9"
        self.assertIn("ref: "+sha,self.wf)
        self.assertIn('"'+sha+'"',self.wf)
        self.assertIn("PINNED_CRM = \""+sha+"\"",self.check)
        self.assertIn("php -l tools/check_f226_signed_fixture.php",self.wf)
        self.assertIn("php tools/check_f226_signed_fixture.php",self.wf)
        self.assertIn("test_f226_signed_fixture.py",self.gate)

    def test_real_crm_php_and_actual_agents_codec_are_linked(self):
        for s in ("AiaHmacFixturePreflight.php",
                  "AiaFixtureNonceLedger.php",
                  "AiaReadOnlyMockBoundary.php",
                  "MockCrmReadRequest::encodeCanonicalJson(",
                  "MockCrmReadRequest::decodeCanonicalJson(",
                  "AiaHmacFixturePreflight::matchesFixture(",
                  "AiaHmacFixturePreflight::inspectFixture(",
                  "F226_SIGNED_FIXTURE_CROSS_SOURCE_PASS"):
            self.assertIn(s,self.php)

    def test_tamper_matrix_binds_body_tenant_nonce_expiry(self):
        for s in ("BAD_BODY_DIGEST","DUPLICATE_JSON","WRONG_ISSUER",
                  "WRONG_AUDIENCE","WRONG_TENANT","WRONG_KEY",
                  "NOT_YET_VALID","EXPIRED","LONG_LIFETIME",
                  "TRAILING_JSON","EXTRA_CLAIM","FORGED_SIGNATURE",
                  "f226Check($negative===12)",
                  "$lab->countConsumed()===0"):
            self.assertIn(s,self.php)

    def test_no_secret_or_go(self):
        for s in ("NO-GO","14 gates","13 cenários","879","110",
                  "CRM #57","main","sintética"):
            self.assertIn(s.lower(),self.doc.lower())
        for banned in ("curl_init(","PDO(","Bearer ","Authorization:",
                       "file_get_contents('http","getenv(","shell_exec("):
            self.assertNotIn(banned,self.php)
        self.assertIn("contents: read",self.wf)


if __name__=="__main__":
    unittest.main()
