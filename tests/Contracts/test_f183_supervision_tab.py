"""F183: navigable F108 supervision tab consumes only the F182 synthetic contract."""
from __future__ import annotations

import json
import re
import subprocess
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
PAGE = ROOT / "examples/f108-laboratorio-navegavel.html"
DEMO = ROOT / "examples/f182-queue-supervision-demo.php"


class F183SupervisionTabTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.html = PAGE.read_text(encoding="utf-8")
        match = re.search(
            r'<script type="application/json" id="f183-supervision-data">([^<]+)</script>',
            cls.html,
        )
        assert match is not None, "missing fixed offline report fixture"
        cls.fixture = json.loads(match.group(1))

    def test_browser_fixture_matches_actual_offline_php_contract_exactly(self):
        p = subprocess.run(
            ["php", str(DEMO)], capture_output=True, text=True,
            timeout=15, check=False,
        )
        self.assertEqual(p.returncode, 0, p.stderr)
        self.assertEqual(p.stderr, "")
        self.assertEqual(self.fixture, json.loads(p.stdout))

    def test_accessible_navigation_and_closed_no_go_section(self):
        self.assertIn('data-page-button="supervision"', self.html)
        self.assertIn('id="page-supervision" hidden', self.html)
        self.assertIn('for(const p of ["overview","agents","lab","supervision","audit"])',
                      self.html)
        self.assertIn('aria-label="Tarefas da fila sintética"', self.html)
        self.assertIn('aria-label="Etapas de revisão sintética"', self.html)
        self.assertIn('id="supervision-decision"', self.html)
        self.assertIn('drawSupervision();choose("allow");', self.html)
        self.assertEqual(self.fixture["operational_decision"], "NO_GO")

    def test_all_ten_fields_and_four_summary_metrics_rendered(self):
        ids = (
            "supervision-pending", "supervision-reserved",
            "supervision-completed-row", "supervision-failed",
            "supervision-cancelled", "supervision-indeterminate-row",
            "supervision-first", "supervision-second",
            "supervision-unresolved", "supervision-conflict-row",
            "supervision-completed", "supervision-indeterminate",
            "supervision-pending-review", "supervision-conflict",
        )
        for field in ids:
            with self.subTest(field=field):
                self.assertEqual(self.html.count(f'id="{field}"'), 1)
                self.assertIn(f'["{field}",', self.html)

    def test_no_network_persistence_or_new_external_dependency(self):
        self.assertIn("connect-src 'none'", self.html)
        for token in ("<script src=", "<iframe", "XMLHttpRequest(",
                      "fetch(", "WebSocket(", "localStorage(", "sessionStorage(",
                      "http://", "https://"):
            with self.subTest(token=token):
                self.assertNotIn(token, self.html)

    def test_review_aggregation_does_not_disappear_or_become_approved(self):
        q = self.fixture["queue"]
        v = self.fixture["review"]
        self.assertEqual(q["indeterminate"], 3)
        self.assertEqual(sum(v.values()), q["indeterminate"])
        self.assertEqual(v["reviewed_unresolved"], 1)
        self.assertEqual(v["conflict"], 1)
        self.assertFalse(self.fixture["real_authorization"])
        self.assertEqual(self.fixture["external_calls"], 0)
        self.assertEqual(self.fixture["write_calls"], 0)

    def test_fixture_contains_no_run_reviewer_or_personal_identifiers(self):
        encoded = json.dumps(self.fixture)
        for marker in ("SYNTHETIC_F182_RUN", "SYNTHETIC_A_LEAD",
                       "SYNTHETIC_REVIEWER", "SYNTHETIC_PRINCIPAL",
                       "request_ref", "claim_ref", "run_digest",
                       "reviewer_digest", "access_token", "nonce"):
            with self.subTest(marker=marker):
                self.assertNotIn(marker, encoded)


if __name__ == "__main__":
    unittest.main()
