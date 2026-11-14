"""F220: both actual facade and CRM Core must inspect canonical bytes safely."""
from pathlib import Path
import unittest

ROOT=Path(__file__).resolve().parents[2]


class F220ResponseJSON(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.code=(ROOT/"src/MockBridge/MockCrmPublicResponseContract.php").read_text()
        cls.php=(ROOT/"tools/check_f220_canonical_response.php").read_text()
        cls.cross=(ROOT/"tools/check_crm_aia_real_facade_core.php").read_text()
        cls.gate=(ROOT/"tools/run_r01_offline_quality_gate.sh").read_text()
        cls.workflow=(ROOT/".github/workflows/r01-dev-integrity.yml").read_text()
        cls.doc=(ROOT/"docs/AIA-FT01-F220-RESPOSTA-JSON.md").read_text()

    def test_closed_canonical_payload_with_bounded_depth(self):
        for marker in ("decodeCanonicalJson(", "encodeCanonicalJson(",
                       "strlen($bytes) > 512",
                       "json_decode($bytes, true, 4, JSON_THROW_ON_ERROR)",
                       "hash_equals($canonical, $bytes)",
                       "self::isSafe($body, $request, $context)"):
            self.assertIn(marker,self.code)
        self.assertIn("isAcceptedRead(array $body",self.code)

    def test_actual_facade_fixture_and_adversarial_mutations(self):
        for marker in ("op01_php_public_response_probe.php",
                       "DUPLICATE_ROOT", "DUPLICATE_PROJECTION",
                       "WHITESPACE", "ESCAPED", "REORDER", "EXTRA",
                       "BAD_STATE", "AUTH_TRUE", "EXTERNAL_CALL",
                       "PERSONAL_FIELD", "PRIVATE_VALUE", "FIELD_ORDER",
                       "MISSING_KEY", "DENIAL_PROJECTION", "TRAILING",
                       "OVER_SIZE", "INVALID_UTF8", "ROOT_NULL",
                       "ROOT_ARRAY", "EMPTY", "F220_DENIAL_AS_SUCCESS"):
            self.assertIn(marker,self.php)

    def test_crm_cross_source_checks_serialized_php_facade_output(self):
        self.assertIn("MockCrmPublicResponseContract::encodeCanonicalJson(",self.cross)
        self.assertIn("MockCrmPublicResponseContract::decodeCanonicalJson(",self.cross)
        self.assertIn("$inspector->inspect($decoded, $fields)",self.cross)
        self.assertIn("f210NoRights($roundtripAssessment)",self.cross)
        self.assertIn("php tools/check_crm_aia_real_facade_core.php",self.workflow)

    def test_runner_required_in_ci_and_no_real_authorization(self):
        for marker in ("test_f219_canonical_json.py",
                       "test_f220_canonical_response.py",
                       "php -l tools/check_f220_canonical_response.php",
                       "php tools/check_f220_canonical_response.php"):
            self.assertIn(marker,self.gate)
        self.assertLess(
            self.gate.index("php tools/check_f219_canonical_json.php"),
            self.gate.index("php tools/check_f220_canonical_response.php"))
        for m in ("NO-GO","CRM #57","879","110","13","14","Sem HTTP"):
            self.assertIn(m,self.doc)
        for bad in ("curl_init(", "PDO(", "Authorization:", "Bearer ",
                    "getenv(", "file_get_contents('http"):
            self.assertNotIn(bad,self.php)


if __name__=="__main__":
    unittest.main()
