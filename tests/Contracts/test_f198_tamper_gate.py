"""F198: make synthetic browser tamper regression a protected quality gate."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]


class F198Tests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.source = (ROOT / "tests/Browser/f196_tamper_smoke.cjs").read_text(encoding="utf-8")
        cls.gate = (ROOT / "tools/run_r01_offline_quality_gate.sh").read_text(encoding="utf-8")

    def test_mutations_exercise_real_production_browser_function(self):
        self.assertIn('html.slice(jsStart, jsEnd)', self.source)
        self.assertIn('vm.runInNewContext(command, { get }', self.source)
        self.assertIn('validateTransitionSnapshot', self.source)

    def test_fifteen_or_more_adversarial_contract_mutations(self):
        self.assertGreaterEqual(self.source.count('["'), 16)
        for phrase in ("unexpected outer key", "false GO envelope", "extra reviewer identity",
                       "HTML injected into pending count", "boolean coerced as integer",
                       "real authority asserted", "missing snapshot"):
            self.assertIn(phrase, self.source)

    def test_no_rendering_after_rejected_contract(self):
        self.assertIn('elements.get("queue-transition-state").textContent', self.source)
        self.assertIn('"INIT", label + " cannot render unverified state"', self.source)
        self.assertIn('"F196_ADVERSARIAL_PASS cases="', self.source)

    def test_ci_runs_inert_smoke_when_node_exists(self):
        self.assertIn("node tests/Browser/f196_tamper_smoke.cjs", self.gate)
        self.assertIn("if command -v node", self.gate)
        self.assertIn("operational_decision=NO_GO", self.gate)

    def test_script_never_calls_real_services(self):
        for text in ("fetch(", "XMLHttpRequest(", "WebSocket(", "http://",
                     "https://", "child_process", "process.env.", "writeFileSync"):
            self.assertNotIn(text, self.source)


if __name__ == "__main__":
    unittest.main()
