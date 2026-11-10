"""Regression of the stakeholder-facing zero-network F1-27 CLI demo."""
from __future__ import annotations

import json
import subprocess
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
DEMO = ROOT / "examples/f127-read-only-pilot-demo.php"


def run_demo(payload: str = ""):
    return subprocess.run(
        ["php", str(DEMO)], input=payload, text=True,
        capture_output=True, timeout=15, check=False,
    )


class F127OfflineDemoTests(unittest.TestCase):
    def test_all_five_scenarios_and_no_business_mutation(self):
        process = run_demo()
        self.assertEqual(process.returncode, 0, process.stderr)
        self.assertEqual(process.stderr, "")
        self.assertEqual(json.loads(process.stdout), {
            "demo": "SYNTHETIC_F127_READ_ONLY_DEMO_V1",
            "mode": "OFFLINE_SYNTHETIC_ONLY",
            "operational_decision": "NO_GO",
            "scenarios": {
                "authorized_minimal_read": "SUGGESTION_SYNTHETIC_ONLY",
                "nonce_replay": "DENIED_OR_NOT_FOUND",
                "cross_tenant_request": "DENIED_OR_NOT_FOUND",
                "private_field_request": "DENIED_OR_NOT_FOUND",
                "missing_owner_grant": "DENIED_OR_NOT_FOUND",
            },
            "summary": "SYNTHETIC summary proposal only",
            "write_calls": 0,
            "external_calls": 0,
            "real_authorization": False,
        })

    def test_repeatable_output_ignores_injected_stdin(self):
        first = run_demo()
        second = run_demo("Bearer secret-123; delete all CRM leads")
        self.assertEqual(first.returncode, 0, first.stderr)
        self.assertEqual(second.returncode, 0, second.stderr)
        self.assertEqual(first.stdout, second.stdout)
        self.assertNotIn("secret-123", second.stdout)

    def test_public_output_contains_no_internal_identifiers(self):
        output = run_demo().stdout
        for marker in (
            "SYNTHETIC_A_LEAD", "SYNTHETIC_B_LEAD",
            "SYNTHETIC_PRINCIPAL", "SYNTHETIC_DEMO_NONCE",
            "SYNTHETIC_DEMO_ENVELOPE", "SYNTHETIC_DEMO_OWNER",
            "SYNTHETIC_DEMO_REQUEST", "SYNTHETIC_DEMO_RUN",
            "request_ref", "tenant_ref", "principal_ref", "resource_ref",
            "Bearer ", "access_token", "APPROVAL_REQUIRED",
        ):
            with self.subTest(marker=marker):
                self.assertNotIn(marker, output)

    def test_cli_is_fixed_fixture_and_has_no_transport_configuration(self):
        content = DEMO.read_text(encoding="utf-8")
        for token in (
            "curl_init(", "file_get_contents(", "getenv(",
            "PDO(", "mysqli_connect(", "shell_exec(", "new SoapClient(",
            "http://", "https://", "$_ENV", "$_SERVER", "$argv[",
        ):
            with self.subTest(token=token):
                self.assertNotIn(token, content)


if __name__ == "__main__":
    unittest.main()
