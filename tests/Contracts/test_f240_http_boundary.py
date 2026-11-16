"""F240: compiled labs + public CRM F246 protocol, no HTTP dispatch."""
from pathlib import Path
import unittest

ROOT=Path(__file__).resolve().parents[2]

class F240HttpBoundary(unittest.TestCase):
    def test_inert_envelope_cannot_authorize_transmission(self):
        src=(ROOT/"src/MockBridge/MockSyntheticHttpEnvelope.php").read_text()
        for token in (
            "MockSyntheticEvidenceGate $evidence",
            "$evidence->checkEvidenceOnce(",
            "'method' => 'POST'",
            "'x-aia-claims' => $claimsJson",
            "'x-aia-signature' => $signatureBase64",
            "'transmission_authorized' => false",
            "'real_authorization' => false",
            "'operational_decision' => 'NO_GO'",
        ):
            self.assertIn(token,src)
        for forbidden in ("curl_init(", "file_get_contents(", "getenv(", "PDO(", "Authorization:", "Bearer "):
            self.assertNotIn(forbidden,src)

    def test_denial_receipt_rejects_positive_and_extra_fields(self):
        src=(ROOT/"src/MockBridge/MockSyntheticHttpDenialReceipt.php").read_text()
        self.assertIn("$status !== 404",src)
        self.assertIn("MockCrmPublicResponseContract::denial()",src)
        self.assertIn("hash_equals($expected, $responseBody)",src)
        self.assertIn("$f246 = array_keys($headers) === ['content-type', 'cache-control']",src)
        self.assertIn("'private, no-store, max-age=0'",src)
        self.assertIn("'x-robots-tag'",src)
        self.assertIn("'x-content-type-options'",src)
        t=(ROOT/"tools/check_f239_denial_receipt.php").read_text()
        self.assertIn("F239_DENIAL_RECEIPT_PASS",t)
        self.assertIn("count($cases)===46",t)

    def test_composed_cycle_and_main_ci_are_mandatory(self):
        gate=(ROOT/"tools/run_r01_offline_quality_gate.sh").read_text()
        for required in (
            "php tools/check_f238_http_envelope.php",
            "php tools/check_f239_denial_receipt.php",
            "php tools/check_f240_http_cycle.php",
            "test_f240_http_boundary.py",
        ):
            self.assertIn(required,gate)
        self.assertIn("operational_decision=NO_GO",gate)
        wf=(ROOT/".github/workflows/r01-dev-integrity.yml").read_text()
        self.assertIn("EXPECTED_PHP: '117'",wf)
        self.assertIn("EXPECTED_CASES: '879'",wf)
        self.assertIn("PRIVATE_CROSS_REPOSITORY_SOURCE_NOT_VERIFIED",wf)

if __name__=="__main__":
    unittest.main()
