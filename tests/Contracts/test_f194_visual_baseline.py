"""F194: prevent public-looking visual mock from showing stale test numbers."""
import pathlib
import re
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[2]
HTML = ROOT / "examples/f108-laboratorio-navegavel.html"
STATUS = ROOT / "STATUS-ATUAL.md"


class F194Tests(unittest.TestCase):
    def test_preview_baseline_matches_canonical_status(self):
        page = HTML.read_text(encoding="utf-8")
        status = STATUS.read_text(encoding="utf-8")
        match = re.search(r"\*\*(\d+) testes PHP / lint (\d+)", status)
        self.assertIsNotNone(match, "Canonical status must provide the baseline")
        cases, lint = match.groups()
        self.assertEqual(page.count('data-baseline-cases="' + cases + '"'), 1)
        self.assertEqual(page.count('data-baseline-lint="' + lint + '"'), 1)

    def test_stale_f107_numbers_not_advertised_as_current(self):
        page = HTML.read_text(encoding="utf-8")
        self.assertNotIn("<strong>85/85</strong>", page)
        self.assertNotIn("<strong>85 / 85</strong>", page)
        self.assertNotIn("<strong>43</strong><span class=\"hint\">Hashes", page)
        self.assertIn("Baseline CI offline", page)
        self.assertIn("Não representa homologação real", page)

    def test_no_operational_approval_in_visual_lab(self):
        page = HTML.read_text(encoding="utf-8")
        self.assertIn("Sem autenticação, APIs, banco de dados, IA real ou publicação", page)
        self.assertIn("SIMULAÇÃO · SEM CONEXÃO REAL", page)
        self.assertNotIn('data-operational-decision="GO"', page)

    def test_single_source_unchanged_for_actual_php_gate(self):
        script = (ROOT / "tools/run_r01_offline_quality_gate.sh").read_text(encoding="utf-8")
        self.assertIn("EXPECTED_CASES", script) if "EXPECTED_CASES" in script else self.assertIn("validate_ft01_offline.sh", script)
        self.assertIn("test_f194_visual_baseline.py", script)


if __name__ == "__main__":
    unittest.main()
