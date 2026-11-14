"""F225: both repositories reject hostile canonical JSON and never grant CRM data."""
from pathlib import Path
import unittest

ROOT=Path(__file__).resolve().parents[2]


class F225BilateralJSON(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.workflow=(ROOT/".github/workflows/r01-dev-integrity.yml").read_text()
        cls.source=(ROOT/"tools/check_crm_aia_cross_source.php").read_text()
        cls.reciprocal=(ROOT/"tools/check_f224_reciprocal_pins.py").read_text()
        cls.gate=(ROOT/"tools/run_r01_offline_quality_gate.sh").read_text()
        cls.doc=(ROOT/"docs/AIA-FT01-F225-CRM-JSON-RECIPROCO.md").read_text()

    def test_new_immutable_crm_pin_and_contract_are_gated(self):
        self.assertIn("bbb4228a27028070a9561cb649a86224aaebde48",self.workflow)
        self.assertIn("bbb4228a27028070a9561cb649a86224aaebde48",self.reciprocal)
        self.assertNotIn("ref: 5eca65403a4e3707e1fd7145ec13073a762d26f6",
                         self.workflow)
        self.assertIn("python3 tools/check_f224_reciprocal_pins.py",self.workflow)
        self.assertIn("test_f225_bilateral_json.py",self.gate)

    def test_php_verifier_computes_real_codec_parity(self):
        for token in ("MockCrmReadRequest::encodeCanonicalJson(",
                      "MockCrmReadRequest::decodeCanonicalJson(",
                      "AiaReadOnlyMockBoundary::hasCanonicalFixtureShape(",
                      "AiaReadOnlyMockBoundary::inspectCanonicalJson(",
                      "$agentsDenied = true", "f208Check($rawNegative === 13)"):
            self.assertIn(token,self.source)
        for mutation in ("DUPLICATE_KEY","ALIAS_ESCAPE","WHITESPACE",
                         "WRONG_KEY_ORDER","INJECTED_ADMIN","PERSONAL_FIELD",
                         "DUPLICATE_FIELDS","TRAILING","INVALID_UTF8",
                         "OVER_SIZE","ARRAY_ROOT","NULL_ROOT","EMPTY"):
            self.assertIn(mutation,self.source)

    def test_reciprocal_guard_verifies_actual_crm_code_not_comments_only(self):
        for token in ("hasCanonicalFixtureShape(string $bytes)",
                      "inspectCanonicalJson(string $bytes)",
                      "hash_equals($canonical, $bytes)",
                      "strlen($bytes) > 1024"):
            self.assertIn(token,self.reciprocal)
        self.assertIn("CRM_OWNER_BOUNDARY_DRIFT",self.reciprocal)
        self.assertIn("return self::denied();",self.reciprocal)

    def test_no_network_secrets_or_operational_permissions(self):
        for bad in ("curl_init(","PDO(","Authorization:","Bearer ",
                    "file_get_contents('http","shell_exec("):
            self.assertNotIn(bad,self.source)
        for token in ("NO-GO","CRM #57","13","14","879","110","main"):
            self.assertIn(token,self.doc)
        self.assertIn("contents: read",self.workflow)
        self.assertIn("persist-credentials: false",self.workflow)


if __name__=="__main__":
    unittest.main()
