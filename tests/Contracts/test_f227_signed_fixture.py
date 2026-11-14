"""F227: the actual Agents signer produces body/proof atomically for CRM."""
from pathlib import Path
import unittest

ROOT=Path(__file__).resolve().parents[2]


class F227Signer(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.source=(ROOT/"src/MockBridge/MockCrmReadRequest.php").read_text()
        cls.cross=(ROOT/"tools/check_f226_signed_fixture.php").read_text()
        cls.actual=(ROOT/"tools/check_f227_signed_fixture.php").read_text()
        cls.gate=(ROOT/"tools/run_r01_offline_quality_gate.sh").read_text()
        cls.doc=(ROOT/"docs/AIA-FT01-F227-ASSINATURA-CANONICA-ATOMICA.md").read_text()

    def test_source_binds_bytes_proof_and_mock_key_only(self):
        block=self.source.split(
            "public static function issueSyntheticSignedFixture(",1
        )[1].split("/** Only known ordered mock values",1)[0]
        for part in ("self::encodeCanonicalJson($wire)",
                     "SYNTHETIC_PUBLIC_TEST_ONLY_",
                     "SYNTHETIC_AIA_HMAC_PROOF_V1",
                     "hash('sha256', $body)",
                     "hash_hmac('sha256', $message, $fixtureKey)",
                     "$expiresAt - $issuedAt > 30",
                     "return ['body' => $body, 'proof' => $proof];"):
            self.assertIn(part,block)
        for forbidden in ("curl_init(", "PDO(", "getenv(",
                          "file_get_contents(", "Bearer "):
            self.assertNotIn(forbidden,block)

    def test_bilateral_php_calls_agents_signer_and_crm_checker(self):
        self.assertIn("MockCrmReadRequest::issueSyntheticSignedFixture(",self.cross)
        self.assertIn("$proof===f226Proof(",self.cross)
        self.assertIn("AiaHmacFixturePreflight::matchesFixture(",self.cross)
        self.assertIn("F226_SIGNED_FIXTURE_CROSS_SOURCE_PASS",self.cross)

    def test_php_executes_independent_mac_and_fourteen_negatives(self):
        for part in ("MockCrmReadRequest::decodeCanonicalJson(",
                     "hash_equals($independent,$proof['signature'])",
                     "['admin'=>true]", "['fields'=>['email']]",
                     "f227Check(count($bad)===14)",
                     "F227_SIGNED_FIXTURE_PASS"):
            self.assertIn(part,self.actual)
        for part in ("test_f227_signed_fixture.py",
                     "php -l tools/check_f227_signed_fixture.php",
                     "php tools/check_f227_signed_fixture.php"):
            self.assertIn(part,self.gate)
        self.assertLess(self.gate.index("php tools/check_f227_signed_fixture.php"),
                        self.gate.index("bash tools/validate_ft01_offline.sh"))

    def test_no_go_and_baseline_unchanged(self):
        for part in ("NO-GO","CRM #57","879","110","14 gates",
                     "13 cenários","main"):
            self.assertIn(part,self.doc)


if __name__=="__main__":
    unittest.main()
