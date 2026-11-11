"""F195 regression checks for trusted queue delta display, offline only."""
import pathlib
import sys
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "tools"))
sys.path.insert(0, str(ROOT / "tests" / "Contracts"))
import render_f190_supervision as report
from test_f193_supervision_report import sample


class F195Tests(unittest.TestCase):
    def test_transition_delta_is_exact(self):
        page = report.render(sample())
        self.assertIn("Diferenças entre os dois estados", page)
        self.assertIn('<th scope="row">Pendentes</th><td>−1</td>', page)
        self.assertIn('<th scope="row">Concluídos</th><td>+1</td>', page)
        self.assertIn('<th scope="row">Indeterminados</th><td>0</td>', page)
        self.assertEqual(page.count("<aside "), 1)

    def test_zero_deltas_are_not_hidden(self):
        page = report.render(sample())
        for field in ("Reservados", "Falhas", "Cancelados"):
            self.assertIn('<th scope="row">' + field + '</th><td>0</td>', page)

    def test_incident_remains_unresolved_and_not_authorized(self):
        page = report.render(sample())
        self.assertIn("INDETERMINADO: 1 antes e depois", page)
        self.assertIn("Revisão não concede GO", page)
        self.assertIn("OPERAÇÃO REAL: NO-GO", page)

    def test_missing_uncertainty_is_refused_before_rendering(self):
        state = sample()
        state["resumed"]["queue"]["indeterminate"] = 0
        with self.assertRaises(ValueError):
            report.render(state)

    def test_untrusted_counter_html_is_rejected(self):
        state = sample()
        state["paused"]["queue"]["pending"] = "<script>alert(1)</script>"
        with self.assertRaises(ValueError):
            report.render(state)

    def test_render_is_deterministic_and_has_no_active_controls(self):
        page = report.render(sample())
        self.assertEqual(page, report.render(sample()))
        for forbidden in ("<script", "<form", "<button", "https://", "http://"):
            self.assertNotIn(forbidden, page)


if __name__ == "__main__":
    unittest.main()
