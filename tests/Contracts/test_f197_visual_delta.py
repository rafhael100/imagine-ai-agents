"""F197: assert browser transition deltas use only verified PHP snapshots."""
import json
import re
from pathlib import Path
import shutil
import subprocess
import unittest

ROOT = Path(__file__).resolve().parents[2]
HTML = ROOT / "examples/f108-laboratorio-navegavel.html"
SMOKE = ROOT / "tests/Browser/f108_smoke.cjs"


def fixture():
    page = HTML.read_text(encoding="utf-8")
    match = re.search(r'<script type="application/json" id="f196-transition-data">([^<]+)</script>', page)
    if not match:
        raise AssertionError("F196 fixture missing")
    return json.loads(match.group(1))


class F197Tests(unittest.TestCase):
    def test_exact_deltas_and_uncertainty(self):
        data = fixture()
        before, after = data["paused"]["queue"], data["resumed"]["queue"]
        self.assertEqual(after["pending"] - before["pending"], -1)
        self.assertEqual(after["completed"] - before["completed"], 1)
        self.assertEqual(after["indeterminate"] - before["indeterminate"], 0)
        self.assertEqual(data["operational_decision"], "NO_GO")

    def test_metrics_have_accessible_static_locations(self):
        page = HTML.read_text(encoding="utf-8")
        self.assertEqual(page.count('id="queue-delta-pending"'), 1)
        self.assertEqual(page.count('id="queue-delta-completed"'), 1)
        self.assertEqual(page.count('id="queue-delta-unknown"'), 1)
        self.assertIn('aria-label="Variação agregada entre PAUSED e OPEN"', page)
        self.assertIn("não significa que uma operação real foi executada", page)

    def test_browser_only_renders_verified_number_not_user_input(self):
        page = HTML.read_text(encoding="utf-8")
        self.assertIn("signedDelta(after.pending-before.pending)", page)
        self.assertIn("signedDelta(after.completed-before.completed)", page)
        self.assertIn("signedDelta(after.indeterminate-before.indeterminate)", page)
        self.assertIn("validateTransitionSnapshot(transitionRaw.paused", page)
        self.assertIn("validateTransitionSnapshot(transitionRaw.resumed", page)
        self.assertNotIn('innerHTML=transitionRaw', page)

    def test_smoke_covers_both_states_and_no_go(self):
        smoke = SMOKE.read_text(encoding="utf-8")
        self.assertIn('byId("queue-delta-unknown")', smoke)
        self.assertIn('byId("queue-transition-resumed").click()', smoke)
        self.assertIn('byId("supervision-decision").textContent, "NO-GO"', smoke)

    @unittest.skipUnless(shutil.which("php"), "PHP missing")
    def test_fixture_still_matches_real_php(self):
        process = subprocess.run(["php", "examples/f190-queue-admission-demo.php"], cwd=ROOT,
                                 capture_output=True, text=True, timeout=15)
        self.assertEqual(process.returncode, 0, process.stderr)
        self.assertEqual(fixture(), json.loads(process.stdout))


if __name__ == "__main__":
    unittest.main()
