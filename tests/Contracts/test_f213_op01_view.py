"""F213: safe, navigable, static five-wave review dashboard."""
from pathlib import Path
import sys
import unittest

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "tools"))
import check_f213_op01_view as view


class F213View(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.html = (ROOT / "examples/f213-op01-plano.html").read_text()

    def test_full_view_is_supported_by_real_packet(self):
        self.assertEqual(view.validate_html(self.html),
                         {"stages": 5, "gates": 14, "cases": 13, "pilot_go": 0})

    def test_no_network_storage_or_actions(self):
        for forbidden in ("http://", "https://", "<script", "<iframe",
                          "<form", "localStorage", "sessionStorage", "onclick="):
            self.assertNotIn(forbidden, self.html)
        self.assertIn("connect-src 'none'", self.html)
        self.assertIn("script-src 'none'", self.html)
        self.assertIn("form-action 'none'", self.html)

    def test_mobile_brand_and_accessible_native_expansion(self):
        self.assertIn("assets/logo-imagine.png", self.html)
        self.assertIn("#f9941d", self.html)
        self.assertIn("@media(max-width:700px)", self.html)
        self.assertEqual(self.html.count("<details>"), 5)
        self.assertEqual(self.html.count("</details>"), 5)
        self.assertEqual(self.html.count('class="workstream"'), 5)

    def test_false_go_is_rejected(self):
        with self.assertRaises(view.ViewError):
            view.validate_html(self.html.replace("Produção: NO-GO", "Produção: GO"))

    def test_missing_gate_is_rejected(self):
        with self.assertRaises(view.ViewError):
            view.validate_html(self.html.replace('data-gate="R01-G01"', 'data-gate="UNKNOWN"'))

    def test_unreviewed_evidence_fabrication_is_rejected(self):
        with self.assertRaises(view.ViewError):
            view.validate_html(self.html.replace(
                "identidade-servico-e-revogacao-verificadas", "evidencia-ficticia"))

    def test_suspicious_external_asset_is_rejected(self):
        with self.assertRaises(view.ViewError):
            view.validate_html(self.html.replace('assets/logo-imagine.png',
                                                 'https://evil.example/logo.png'))

    def test_quality_gate_checks_view_after_f212(self):
        script = (ROOT / "tools/run_r01_offline_quality_gate.sh").read_text()
        self.assertIn("python3 tools/check_f213_op01_view.py", script)
        self.assertIn("test_f213_op01_view.py", script)
        self.assertLess(script.index("python3 tools/plan_op01_release.py --check"),
                        script.index("python3 tools/check_f213_op01_view.py"))


if __name__ == "__main__":
    unittest.main()
