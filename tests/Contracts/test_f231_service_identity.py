"""F231: verify signed, isolated service identity in a no-network lab."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]

class F231ServiceIdentity(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.code = (ROOT/"src/MockBridge/MockServiceIdentityPreflight.php").read_text()
        cls.test = (ROOT/"tools/check_f231_ed25519_preflight.php").read_text()
        cls.gate = (ROOT/"tools/run_r01_offline_quality_gate.sh").read_text()
        cls.wf = (ROOT/".github/workflows/r01-dev-integrity.yml").read_text()
        cls.doc = (ROOT/"docs/AIA-FT01-F231-IDENTIDADE-ED25519-OFFLINE.md").read_text()

    def test_asymmetric_verification_is_not_iam_allow(self):
        for s in ("sodium_crypto_sign_verify_detached(",
                  "hash_equals($encoded, $claimsJson)",
                  "MockCrmReadRequest::decodeCanonicalJson($requestJson)",
                  "hash('sha256', $requestJson)",
                  "SYNTHETIC_AIA_SERVICE_ED25519_V1",
                  "SYNTHETIC_AIA_SERVICE_IDENTITY_V1",
                  "SYNTHETIC_AGENTS_READ_ONLY",
                  "SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES",
                  "SODIUM_CRYPTO_SIGN_BYTES",
                  "count($trustedKeys) > 8",
                  "count($this->consumed) >= $this->capacity",
                  "return false;"):
            self.assertIn(s, self.code)
        for forbidden in ("curl_init(", "PDO(", "getenv(", "file_get_contents(",
                          "Authorization:", "Bearer ", "sodium_crypto_sign_detached(",
                          "sodium_crypto_sign_seed_keypair("):
            self.assertNotIn(forbidden, self.code)

    def test_real_crypto_negatives_not_just_string_mock(self):
        for s in ("sodium_crypto_sign_seed_keypair(",
                  "sodium_crypto_sign_detached(",
                  "WRONG_TENANT_CLAIM", "WRONG_OPERATION",
                  "WRONG_AUDIENCE", "WRONG_ISSUER", "WRONG_SUBJECT",
                  "INVALID_SIGNER", "WRONG_PUBLIC_KEY", "DISABLED_KEY",
                  "NO_TRUSTED_KEYS", "UNKNOWN_KID", "DUPLICATE_JSON_KEY",
                  "BEFORE_ISSUE", "AFTER_EXPIRY", "REQUEST_SWAP",
                  "EXTRA_CLAIM", "OVERSIZE_REQUEST", "OVERSIZE_CLAIMS",
                  "LONG_TTL", "MALFORMED_BASE64",
                  "$allowed===3 && $denied===24",
                  "$fresh->countConsumed() === 0",
                  "$shared->countConsumed() === 1",
                  "new MockServiceIdentityPreflight(1)",
                  "setAvailable(false)", "setAvailable(true)",
                  "F231_ED25519_SERVICE_PREFLIGHT_PASS"):
            self.assertIn(s, self.test)

    def test_gate_and_immutable_no_deploy(self):
        self.assertIn("test_f231_service_identity.py", self.gate)
        self.assertIn("php -l tools/check_f231_ed25519_preflight.php", self.gate)
        self.assertIn("php tools/check_f231_ed25519_preflight.php", self.gate)
        self.assertIn("EXPECTED_PHP: '120'", self.wf)
        self.assertIn("EXPECTED_CASES: '879'", self.wf)
        self.assertIn("contents: read", self.wf)
        self.assertEqual(self.wf.count("persist-credentials: false"),2)
        for s in ("NO-GO", "CRM #57", "14 gates", "13 cenários", "112",
                  "879", "Ed25519", "não", "main", "VPS"):
            self.assertIn(s, self.doc)

if __name__ == "__main__":
    unittest.main()
