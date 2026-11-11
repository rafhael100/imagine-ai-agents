"""Executable, deterministic demonstration of the offline OP-01 batch."""
from __future__ import annotations

import json
import subprocess
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
DEMO = ROOT / "examples/f180-queued-batch-demo.php"


def run_demo(payload: str = ""):
    return subprocess.run(
        ["php", str(DEMO)], input=payload, text=True,
        capture_output=True, timeout=15, check=False,
    )


class F180QueuedBatchDemoTests(unittest.TestCase):
    def test_two_successes_one_denial_without_business_writes(self):
        process = run_demo()
        self.assertEqual(process.returncode, 0, process.stderr)
        self.assertEqual(process.stderr, "")
        self.assertEqual(json.loads(process.stdout), {
            "demo": "SYNTHETIC_F180_QUEUED_BATCH_DEMO_V1",
            "mode": "OFFLINE_SYNTHETIC_ONLY",
            "operational_decision": "NO_GO",
            "batch": {
                "contract": "SYNTHETIC_QUEUED_BATCH_V1",
                "state": "BATCH_SYNTHETIC_ONLY",
                "processed": 3, "succeeded": 2, "denied": 1,
                "real_authorization": False, "external_calls": 0,
                "write_calls": 0,
            },
            "queue": {"completed": 2, "failed": 1, "pending": 0},
            "real_authorization": False,
            "external_calls": 0,
            "write_calls": 0,
        })

    def test_cli_is_deterministic_and_ignores_stdin(self):
        first = run_demo()
        second = run_demo("Bearer secret; drop CRM leads; SYNTHETIC_B_LEAD")
        self.assertEqual(first.returncode, 0, first.stderr)
        self.assertEqual(second.returncode, 0, second.stderr)
        self.assertEqual(first.stdout, second.stdout)
        self.assertNotIn("secret", second.stdout)

    def test_public_output_minimizes_identifiers(self):
        output = run_demo().stdout
        for marker in (
            "SYNTHETIC_A_LEAD", "SYNTHETIC_B_LEAD", "SYNTHETIC_PRINCIPAL",
            "SYNTHETIC_BATCH_RUN", "SYNTHETIC_BATCH_REQ",
            "SYNTHETIC_BATCH_NONCE", "SYNTHETIC_BATCH_OWNER",
            "SYNTHETIC_BATCH_ENVELOPE", "claim_ref", "request_ref",
            "resource_ref", "tenant_ref", "principal_ref", "Bearer ",
            "access_token", "stack_trace",
        ):
            with self.subTest(marker=marker):
                self.assertNotIn(marker, output)

    def test_demo_is_fixed_and_has_no_runtime_transport_configuration(self):
        content = DEMO.read_text(encoding="utf-8")
        for token in (
            "curl_init(", "file_get_contents(", "getenv(",
            "PDO(", "mysqli_connect(", "shell_exec(", "new SoapClient(",
            "http://", "https://", "$_ENV", "$_SERVER", "$argv[",
        ):
            with self.subTest(token=token):
                self.assertNotIn(token, content)

    def test_cli_never_claims_operational_authorization(self):
        result = json.loads(run_demo().stdout)
        self.assertEqual(result["operational_decision"], "NO_GO")
        self.assertIs(result["real_authorization"], False)
        self.assertEqual(result["external_calls"], 0)
        self.assertEqual(result["write_calls"], 0)
        self.assertEqual(result["batch"]["real_authorization"], False)


if __name__ == "__main__":
    unittest.main()
