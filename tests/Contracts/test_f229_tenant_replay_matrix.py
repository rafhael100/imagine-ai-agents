"""F229: actual Agents issuer vs pinned CRM verifier, tenant isolation and replay."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]


class F229TenantReplayMatrix(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.script = (ROOT / "tools/check_f229_tenant_replay_matrix.php").read_text()
        cls.workflow = (ROOT / ".github/workflows/r01-dev-integrity.yml").read_text()
        cls.gate = (ROOT / "tools/run_r01_offline_quality_gate.sh").read_text()
        cls.doc = (ROOT / "docs/AIA-FT01-F229-TENANT-HMAC-REPLAY.md").read_text()

    def test_source_is_original_issuer_and_pinned_crm_verifier(self):
        for token in (
            "MockCrmReadRequest::issueSyntheticSignedFixture(",
            "MockCrmReadRequest::decodeCanonicalJson(",
            "AiaHmacFixturePreflight::matchesFixture(",
            "AiaHmacFixturePreflight::inspectFixture(",
            "AiaFixtureNonceLedger(",
            "F229_TENANT_REPLAY_MATRIX_PASS",
            "real_authorization=false external_calls=0",
        ):
            self.assertIn(token, self.script)
        self.assertIn("d7c7aacc766c7ade4c65dcd211ed4569442144a9", self.workflow)

    def test_fail_closed_matrix_and_non_consumption(self):
        for token in (
            "SYNTHETIC_TENANT_A", "SYNTHETIC_TENANT_B", "SYNTHETIC_TENANT_C",
            "CROSS_TENANT", "DIFFERENT_KEY", "DIFFERENT_BODY",
            "WHITESPACE", "DUPLICATE_JSON", "CLAIM_TENANT",
            "CLAIM_NONCE", "CLAIM_ISSUER", "CLAIM_AUDIENCE",
            "CLAIM_DIGEST", "CLAIM_MAC", "CLAIM_EXPIRES", "CLAIM_ISSUED",
            "EXTRA_CLAIM", "KEY_ORDER", "BEFORE_ISSUED", "AFTER_EXPIRES",
            "OVERSIZED_BODY", "$negatives === 54",
            "$ledger->countConsumed() === 0",
            "$outage->setAvailable(false)", "$outage->setAvailable(true)",
            "new AiaFixtureNonceLedger(1)", "$shared->countConsumed() === 1",
        ):
            self.assertIn(token, self.script)
        self.assertIn("DENIED_OR_NOT_FOUND", self.script)
        self.assertIn("'real_authorization' => false", self.script)

    def test_required_quality_gate_and_post_checkout_execution(self):
        self.assertIn("test_f229_tenant_replay_matrix.py", self.gate)
        self.assertIn("php -l tools/check_f229_tenant_replay_matrix.php", self.workflow)
        self.assertIn("php tools/check_f229_tenant_replay_matrix.php", self.workflow)
        self.assertLess(
            self.workflow.index("git -C _synthetic_crm_fixture rev-parse HEAD"),
            self.workflow.index("php tools/check_f229_tenant_replay_matrix.php"),
        )
        self.assertEqual(self.workflow.count("persist-credentials: false"), 2)
        self.assertIn("contents: read", self.workflow)

    def test_lab_only_and_no_go(self):
        for token in ("NO-GO", "CRM #57", "14 gates", "13 cenários",
                      "879", "110", "54", "main", "staging"):
            self.assertIn(token, self.doc)
        for forbidden in (
            "curl_init(", "PDO(", "getenv(", "file_get_contents(",
            "shell_exec(", "Authorization:", "Bearer ", "requests.get(",
        ):
            self.assertNotIn(forbidden, self.script)


if __name__ == "__main__":
    unittest.main()
