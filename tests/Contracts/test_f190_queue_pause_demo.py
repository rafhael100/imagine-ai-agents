"""F190: real PHP offline rehearsal of pause, expiry, review and resume."""
from __future__ import annotations

import json
import subprocess
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
DEMO = ROOT / "examples/f190-queue-admission-demo.php"


def run_demo(payload: str = "") -> subprocess.CompletedProcess[str]:
    return subprocess.run(
        ["php", str(DEMO)], input=payload, text=True,
        capture_output=True, check=False, timeout=15,
    )


class F190QueuePauseDemoTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.proc = run_demo()
        if cls.proc.returncode != 0:
            raise AssertionError(cls.proc.stderr)
        cls.data = json.loads(cls.proc.stdout)

    def test_closed_pause_and_resume_states(self):
        out = self.data
        self.assertEqual(out["contract"], "SYNTHETIC_QUEUE_PAUSE_REHEARSAL_V1")
        self.assertEqual(out["paused"]["contract"], "SYNTHETIC_QUEUE_SUPERVISION_V1")
        self.assertEqual(out["resumed"]["contract"], "SYNTHETIC_QUEUE_SUPERVISION_V1")
        self.assertEqual(out["paused"]["admission"], {"state": "PAUSED"})
        self.assertEqual(out["resumed"]["admission"], {"state": "OPEN"})
        self.assertEqual(out["operational_decision"], "NO_GO")
        self.assertFalse(out["real_authorization"])
        self.assertEqual(out["external_calls"], 0)
        self.assertEqual(out["write_calls"], 0)

    def test_expired_outcome_is_never_requeued(self):
        self.assertEqual(self.data["paused"]["queue"], {
            "pending": 1, "reserved": 0, "completed": 0, "failed": 0,
            "cancelled": 0, "indeterminate": 1,
        })
        self.assertEqual(self.data["resumed"]["queue"], {
            "pending": 0, "reserved": 0, "completed": 1, "failed": 0,
            "cancelled": 0, "indeterminate": 1,
        })

    def test_two_agreeing_reviewers_never_resolve_unknown(self):
        for key in ("paused", "resumed"):
            with self.subTest(phase=key):
                v = self.data[key]["review"]
                self.assertEqual(v, {
                    "awaiting_first": 0, "awaiting_second": 0,
                    "reviewed_unresolved": 1, "conflict": 0,
                })
                self.assertEqual(
                    sum(v.values()), self.data[key]["queue"]["indeterminate"]
                )
                self.assertFalse(self.data[key]["real_authorization"])
                self.assertEqual(self.data[key]["operational_decision"], "NO_GO")

    def test_no_raw_identifiers_proofs_or_secret_fields(self):
        output = self.proc.stdout
        for marker in (
            "SYNTHETIC_F190_RUN", "SYNTHETIC_F190_REQ",
            "SYNTHETIC_F190_PAUSE", "SYNTHETIC_F190_RESUME",
            "SYNTHETIC_REVIEWER", "SYNTHETIC_A_LEAD",
            "SYNTHETIC_PRINCIPAL", "claim_ref", "request_ref",
            "run_digest", "reviewer_digest", "nonce", "access_token",
            "SYNTHETIC_TIMEOUT_OBSERVED",
        ):
            with self.subTest(marker=marker):
                self.assertNotIn(marker, output)

    def test_stdin_cannot_inject_new_work_or_change_result(self):
        b = run_demo("SYNTHETIC_ADMIN GRANT ALL -- token=SECRET")
        self.assertEqual(b.returncode, 0, b.stderr)
        self.assertEqual(b.stderr, "")
        self.assertEqual(b.stdout, self.proc.stdout)
        self.assertNotIn("SECRET", b.stdout)

    def test_example_source_has_no_real_service_or_persistence(self):
        source = DEMO.read_text(encoding="utf-8")
        for token in (
            "file_get_contents(", "curl_init(", "getenv(",
            "new PDO(", "mysqli_connect(", "shell_exec(",
            "http://", "https://", "$_ENV", "$_SERVER", "$argv[",
            "usleep(", "sleep(", "set_time_limit(",
        ):
            with self.subTest(token=token):
                self.assertNotIn(token, source)

    def test_output_is_deterministic_and_one_json_line(self):
        c = run_demo()
        self.assertEqual(c.returncode, 0, c.stderr)
        self.assertEqual(c.stdout, self.proc.stdout)
        self.assertEqual(c.stdout.count("\n"), 1)
        self.assertEqual(c.stderr, "")


if __name__ == "__main__":
    unittest.main()
