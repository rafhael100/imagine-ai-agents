"""F181: deterministic, no-network proof of mock FIFO observation states."""
from __future__ import annotations

import json
import subprocess
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
DEMO = ROOT / "examples/f181-queue-observation-demo.php"


def run_demo(payload: str = "") -> subprocess.CompletedProcess[str]:
    return subprocess.run(
        ["php", str(DEMO)], input=payload, text=True,
        capture_output=True, timeout=15, check=False,
    )


class F181QueueObservationTests(unittest.TestCase):
    def test_exact_public_statuses_and_partial_batch_counts(self):
        p = run_demo()
        self.assertEqual(p.returncode, 0, p.stderr)
        self.assertEqual(p.stderr, "")
        self.assertEqual(json.loads(p.stdout), {
            "demo": "SYNTHETIC_F181_QUEUE_STATES_DEMO_V1",
            "mode": "OFFLINE_SYNTHETIC_ONLY",
            "operational_decision": "NO_GO",
            "states": {
                "no_job": "EMPTY",
                "reserved": "IN_PROGRESS",
                "expired": "REVIEW_REQUIRED",
                "batch_reserved": "IN_PROGRESS",
                "batch_expired": "REVIEW_REQUIRED",
                "partial_batch": "REVIEW_REQUIRED",
            },
            "partial": {"processed": 1, "succeeded": 1, "denied": 0},
            "queue": {"completed": 1, "indeterminate": 1, "pending": 0},
            "real_authorization": False,
            "external_calls": 0,
            "write_calls": 0,
        })

    def test_repeatability_and_untrusted_stdin_never_change_output(self):
        a = run_demo()
        b = run_demo("Bearer SECRET; SYNTHETIC_B_LEAD; DROP TABLE leads")
        self.assertEqual(a.returncode, 0, a.stderr)
        self.assertEqual(b.returncode, 0, b.stderr)
        self.assertEqual(a.stdout, b.stdout)
        self.assertNotIn("SECRET", b.stdout)

    def test_public_output_has_no_resource_run_claim_or_nonce(self):
        output = run_demo().stdout
        for marker in (
            "SYNTHETIC_A_LEAD", "SYNTHETIC_PRINCIPAL", "SYNTHETIC_F181_RUN",
            "SYNTHETIC_F181_REQ", "SYNTHETIC_F181_NONCE",
            "SYNTHETIC_F181_ENVELOPE", "SYNTHETIC_F181_OWNER",
            "claim_ref", "request_ref", "resource_ref", "tenant_ref",
            "principal_ref", "access_token", "Bearer ", "stack_trace",
        ):
            with self.subTest(marker=marker):
                self.assertNotIn(marker, output)

    def test_cli_has_no_dynamic_network_or_database_inputs(self):
        source = DEMO.read_text(encoding="utf-8")
        for token in (
            "curl_init(", "file_get_contents(", "getenv(", "PDO(",
            "mysqli_connect(", "shell_exec(", "new SoapClient(",
            "http://", "https://", "$_ENV", "$_SERVER", "$argv[",
        ):
            with self.subTest(token=token):
                self.assertNotIn(token, source)

    def test_no_phantom_work_is_reported_during_wait_or_review(self):
        report = json.loads(run_demo().stdout)
        self.assertEqual(report["states"]["batch_reserved"], "IN_PROGRESS")
        self.assertEqual(report["states"]["batch_expired"], "REVIEW_REQUIRED")
        self.assertEqual(report["partial"]["processed"], 1)
        self.assertEqual(report["partial"]["succeeded"], 1)
        self.assertEqual(report["queue"]["indeterminate"], 1)
        self.assertFalse(report["real_authorization"])
        self.assertEqual(report["external_calls"], 0)
        self.assertEqual(report["write_calls"], 0)

    def test_demo_retains_unambiguous_no_go_marker(self):
        report = json.loads(run_demo().stdout)
        self.assertEqual(report["mode"], "OFFLINE_SYNTHETIC_ONLY")
        self.assertEqual(report["operational_decision"], "NO_GO")
        self.assertFalse(report["real_authorization"])
        self.assertNotIn("approval", report)
        self.assertNotIn("client", report)


if __name__ == "__main__":
    unittest.main()
