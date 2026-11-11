"""F184: adversarial static/contract checks for an offline, discardable review sandbox."""
from __future__ import annotations
import json
import re
import subprocess
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
PAGE = ROOT / "examples/f108-laboratorio-navegavel.html"
DEMO = ROOT / "examples/f182-queue-supervision-demo.php"
SMOKE = ROOT / "tests/Browser/f108_smoke.cjs"

class F184ReviewSandboxTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.page = PAGE.read_text(encoding="utf-8")
        cls.smoke = SMOKE.read_text(encoding="utf-8")
        m = re.search(r'<script type="application/json" id="f183-supervision-data">([^<]+)</script>', cls.page)
        if m is None:
            raise AssertionError("fixed F182 snapshot must exist")
        cls.fixture = json.loads(m.group(1))

    def test_php_source_fixture_remains_identical(self):
        p = subprocess.run(["php", str(DEMO)], capture_output=True, text=True, timeout=15, check=False)
        self.assertEqual(p.returncode, 0, p.stderr)
        self.assertEqual(json.loads(p.stdout), self.fixture)
        self.assertEqual(self.fixture["operational_decision"], "NO_GO")

    def test_only_three_explicit_non_operational_controls(self):
        for ident in ("review-sim-agree", "review-sim-disagree", "review-sim-reset"):
            self.assertEqual(self.page.count(f'id="{ident}"'), 1)
            self.assertIn(f'get("{ident}").addEventListener("click"', self.page)
        self.assertIn('id="review-sim-message"', self.page)
        self.assertIn('role="status" aria-live="polite"', self.page)
        self.assertIn('Simular concordância', self.page)
        self.assertIn('Simular divergência', self.page)
        self.assertIn('Reiniciar ensaio', self.page)

    def test_hypothetical_results_are_derived_from_immutable_snapshot(self):
        for token in (
            'const source=JSON.parse(get("f183-supervision-data").textContent);',
            'const result={...source.review};',
            'result.awaiting_second-=1;',
            'result.reviewed_unresolved+=1;',
            'result.conflict+=1;',
            'total!==source.queue.indeterminate',
            'reviewSimulationDecision!==null',
            'reviewSimulationDecision=null;',
            'get("review-sim-indeterminate").textContent=String(source.queue.indeterminate);',
        ):
            with self.subTest(token=token):
                self.assertIn(token, self.page)

    def test_replay_is_denied_and_reset_does_not_approve_anything(self):
        self.assertIn('if(reviewSimulationDecision!==null||!["AGREE","DISAGREE"].includes(decision))return;', self.page)
        self.assertIn('get("review-sim-agree").disabled=reviewSimulationDecision!==null;', self.page)
        self.assertIn('get("review-sim-disagree").disabled=reviewSimulationDecision!==null;', self.page)
        self.assertIn('reviewSimulationDecision=null;drawReviewSimulation();', self.page)
        self.assertIn('SYNTHETIC_F184_UNCERTAINTY_DENIED', self.page)
        self.assertFalse(self.fixture["real_authorization"])
        self.assertEqual(self.fixture["write_calls"], 0)

    def test_dom_smoke_covers_both_outcomes_and_baseline_isolation(self):
        for text in (
            '"review-sim-agree").click()', '"review-sim-disagree").click()',
            '"review-sim-reset").click()', '"supervision-second"',
            '"supervision-unresolved"', '"supervision-decision"',
            '"review-sim-indeterminate"', '"audit-runs"',
        ):
            self.assertIn(text, self.smoke)

    def test_no_io_persistence_credentials_or_operational_identity(self):
        self.assertIn("connect-src 'none'", self.page)
        for token in ('fetch(', 'XMLHttpRequest(', 'WebSocket(', 'localStorage(',
                      'sessionStorage(', '<script src=', '<iframe', 'http://', 'https://',
                      'password', 'api_key', 'access_token'):
            with self.subTest(token=token):
                self.assertNotIn(token, self.page)
        self.assertNotIn('request_ref', json.dumps(self.fixture))
        self.assertNotIn('run_digest', json.dumps(self.fixture))
        self.assertEqual(self.fixture["queue"]["indeterminate"], 3)
        self.assertEqual(sum(self.fixture["review"].values()), 3)


if __name__ == "__main__":
    unittest.main()
