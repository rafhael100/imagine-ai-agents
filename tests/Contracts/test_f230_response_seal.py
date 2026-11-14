"""F230 regression: signed CRM denial can never be confused with authorization."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]


class F230ResponseSeal(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.src = (ROOT/"src/MockBridge/MockCrmSignedDenialFixture.php").read_text()
        cls.php = (ROOT/"tools/check_f230_signed_denial.php").read_text()
        cls.wf = (ROOT/".github/workflows/r01-dev-integrity.yml").read_text()
        cls.gate = (ROOT/"tools/run_r01_offline_quality_gate.sh").read_text()
        cls.doc = (ROOT/"docs/AIA-FT01-F230-RESPOSTA-HMAC-NEGATIVA.md").read_text()

    def test_response_is_only_canonical_crm_denial(self):
        for token in (
            "MockCrmReadRequest::decodeCanonicalJson($requestBody)",
            "MockCrmPublicResponseContract::denial()",
            "$crmDenial !== MockCrmPublicResponseContract::denial()",
            "self::canonicalDenial()",
            "hash('sha256', $requestBody)", "hash('sha256', $responseBody)",
            "SYNTHETIC_CRM_AIA_RESPONSE_HMAC_V1",
            "SYNTHETIC_PUBLIC_TEST_ONLY_",
            "hash_equals(self::canonicalDenial(), $responseBody)",
            "count($this->seen) >= $this->capacity",
        ):
            self.assertIn(token, self.src)
        for forbidden in ("curl_init(", "PDO(", "getenv(", "file_get_contents(",
                          "shell_exec(", "Bearer ", "Authorization:"):
            self.assertNotIn(forbidden, self.src)

    def test_bilateral_real_crm_php_and_54_mutations(self):
        for token in (
            "AiaReadOnlyMockBoundary::inspectCanonicalJson(",
            "MockCrmSignedDenialFixture::issue(",
            "MockCrmSignedDenialFixture()",
            "F230_SIGNED_CRM_DENIAL_PASS",
            "TENANT_EXPECTED", "NONCE_EXPECTED", "KEY_CHANGED",
            "REQUEST_SWAP", "REQUEST_WHITESPACE",
            "RESPONSE_WHITESPACE", "RESPONSE_DUPLICATE",
            "RESPONSE_TAMPERED", "CLAIM_TENANT", "CLAIM_NONCE",
            "CLAIM_REQUEST_DIGEST", "CLAIM_RESPONSE_DIGEST",
            "CLAIM_SIGNATURE", "CLAIM_ISSUER", "CLAIM_AUDIENCE",
            "CLAIM_KEY_ORDER", "TOO_EARLY", "EXPIRED",
            "$positives === 3 && $negatives === 54",
            "$fresh->countConsumed() === 0",
            "$outage->setAvailable(false)", "$outage->setAvailable(true)",
            "new MockCrmSignedDenialFixture(1)",
        ):
            self.assertIn(token, self.php)
        for forbidden in ("curl_init(", "PDO(", "getenv(", "file_get_contents(",
                          "shell_exec(", "Bearer ", "Authorization:"):
            self.assertNotIn(forbidden, self.php)

    def test_ci_gate_and_pinned_crm_source(self):
        self.assertIn("test_f230_response_seal.py", self.gate)
        self.assertIn("php -l tools/check_f230_signed_denial.php", self.wf)
        self.assertIn("php tools/check_f230_signed_denial.php", self.wf)
        self.assertIn("d7c7aacc766c7ade4c65dcd211ed4569442144a9", self.wf)
        self.assertLess(
            self.wf.index("git -C _synthetic_crm_fixture rev-parse HEAD"),
            self.wf.index("php tools/check_f230_signed_denial.php"),
        )
        self.assertEqual(self.wf.count("persist-credentials: false"), 2)
        self.assertIn("contents: read", self.wf)

    def test_explicit_no_go(self):
        for marker in ("NO-GO", "54", "3 tenants", "879", "110",
                       "CRM #57", "14 gates", "13 cenários", "main"):
            self.assertIn(marker, self.doc)
        self.assertIn("real_authorization=false external_calls=0", self.php)


if __name__ == "__main__":
    unittest.main()
