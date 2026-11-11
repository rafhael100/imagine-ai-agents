"""F182: deterministic offline queue/reviewer aggregate read model."""
from __future__ import annotations

import json
import subprocess
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
DEMO = ROOT / "examples/f182-queue-supervision-demo.php"


def run_demo(payload: str = "") -> subprocess.CompletedProcess[str]:
    return subprocess.run(
        ["php", str(DEMO)], input=payload, text=True,
        capture_output=True, timeout=15, check=False,
    )


class F182QueueSupervisionDemoTests(unittest.TestCase):
    def test_closed_report_and_disagreement_categories(self):
        p = run_demo()
        self.assertEqual(p.returncode, 0, p.stderr)
        self.assertEqual(p.stderr, "")
        self.assertEqual(json.loads(p.stdout), {
            "contract": "SYNTHETIC_QUEUE_SUPERVISION_V1",
            "queue": {
                "pending": 0, "reserved": 0, "completed": 1,
                "failed": 0, "cancelled": 0, "indeterminate": 3,
            },
            "review": {
                "awaiting_first": 0, "awaiting_second": 1,
                "reviewed_unresolved": 1, "conflict": 1,
            },
            "admission": {"state": "OPEN"},
            "operational_decision": "NO_GO",
            "real_authorization": False,
            "external_calls": 0,
            "write_calls": 0,
        })

    def test_ignores_stdin_and_is_fully_deterministic(self):
        a = run_demo()
        b = run_demo("Bearer PII SECRET; SYNTHETIC_B_LEAD")
        self.assertEqual(a.returncode, 0, a.stderr)
        self.assertEqual(b.returncode, 0, b.stderr)
        self.assertEqual(a.stdout, b.stdout)
        self.assertNotIn("SECRET", b.stdout)

    def test_no_run_or_reviewer_ids_appear(self):
        output = run_demo().stdout
        for marker in (
            "SYNTHETIC_F182_RUN", "SYNTHETIC_F182_REQUEST",
            "SYNTHETIC_A_LEAD", "SYNTHETIC_PRINCIPAL",
            "SYNTHETIC_REVIEWER_", "SYNTHETIC_TIMEOUT_OBSERVED",
            "SYNTHETIC_RESULT_UNKNOWN", "claim_ref", "nonce",
            "tenant_ref", "resource_ref", "principal_ref",
            "run_digest", "reviewer_digest",
        ):
            with self.subTest(marker=marker):
                self.assertNotIn(marker, output)

    def test_source_has_no_external_io_or_untrusted_inputs(self):
        source = DEMO.read_text(encoding="utf-8")
        for token in (
            "curl_init(", "file_get_contents(", "getenv(", "PDO(",
            "mysqli_connect(", "shell_exec(", "new SoapClient(",
            "http://", "https://", "$_ENV", "$_SERVER", "$argv[",
        ):
            with self.subTest(token=token):
                self.assertNotIn(token, source)

    def test_reviewed_unresolved_never_reduces_indeterminate_or_grants_access(self):
        out = json.loads(run_demo().stdout)
        self.assertEqual(sum(out["review"].values()), out["queue"]["indeterminate"])
        self.assertEqual(out["review"]["reviewed_unresolved"], 1)
        self.assertEqual(out["review"]["conflict"], 1)
        self.assertFalse(out["real_authorization"])
        self.assertEqual(out["operational_decision"], "NO_GO")
        self.assertEqual(out["external_calls"], 0)
        self.assertEqual(out["write_calls"], 0)


if __name__ == "__main__":
    unittest.main()
