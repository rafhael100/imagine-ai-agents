"""F203: the actual six-stage browser contract must deny altered fixtures."""
from pathlib import Path
import shutil
import subprocess
import unittest

ROOT = Path(__file__).resolve().parents[2]
NODE = ROOT / "tests/Browser/f202_triage_tamper_smoke.cjs"


class F203Tests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.code = NODE.read_text(encoding="utf-8")
        cls.gate = (ROOT / "tools/run_r01_offline_quality_gate.sh").read_text(encoding="utf-8")

    def test_real_validator_extracted_not_reimplemented(self):
        self.assertIn('html.slice(jsStart, jsEnd)', self.code)
        self.assertIn('vm.runInNewContext(', self.code)
        self.assertIn('actual F202 validator is bounded', self.code)

    def test_two_dozen_hostile_mutations(self):
        labels = [line for line in self.code.splitlines()
                  if line.strip().startswith('["')]
        self.assertEqual(len(labels), 24)
        for required in ("false operational GO", "real auth inside stage",
                         "counter string injection", "missing stage",
                         "unsafe next step", "pretend resolved"):
            self.assertIn(required, self.code)

    def test_no_unverified_rendering_after_denial(self):
        self.assertIn('get("triage-view-state")', self.code)
        self.assertIn('"INIT"', self.code)
        self.assertIn('cannot render unverified data', self.code)

    def test_quality_gate_invokes_browser_attack_runner(self):
        self.assertIn("node tests/Browser/f202_triage_tamper_smoke.cjs", self.gate)
        self.assertIn("if command -v node", self.gate)
        self.assertIn("test_f203_triage_adversarial.py", self.gate)

    def test_no_real_services_in_attack_harness(self):
        for banned in ("https://", "http://", "process.env.",
                       "child_process", "writeFileSync"):
            self.assertNotIn(banned, self.code)

    @unittest.skipUnless(shutil.which("node"), "Node CLI missing")
    def test_node_attack_suite_runs_cleanly(self):
        out = subprocess.run(["node", str(NODE)], cwd=ROOT,
                             text=True, capture_output=True, timeout=15)
        self.assertEqual(out.returncode, 0, out.stderr)
        self.assertIn("F202_TRIAGE_ADVERSARIAL_PASS attacks=24", out.stdout)


if __name__ == "__main__":
    unittest.main()
