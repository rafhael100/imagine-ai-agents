"""Deterministic offline generator tests; no real CRM API or network."""
import csv
import importlib.util
import json
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SCRIPT = ROOT / "tools/generate_op01_dashboard.py"
TEMPLATE = ROOT / "examples/r01-op01-readiness-offline.template.html"
HTML = ROOT / "examples/r01-op01-readiness-offline.html"
GATES = ROOT / "docs/evidencias/aia-r01-gates-op01.csv"
CASES = ROOT / "docs/evidencias/op01-casos-homologacao.csv"

sys.path.insert(0, str(ROOT / "tools"))
spec = importlib.util.spec_from_file_location("op01_dashboard_generator", SCRIPT)
generator = importlib.util.module_from_spec(spec)
spec.loader.exec_module(generator)


class GeneratorTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        tmp = Path(self.tmp.name)
        self.template, self.output = tmp / "template.html", tmp / "rendered.html"
        self.gates, self.cases = tmp / "gates.csv", tmp / "cases.csv"
        for source, target in (
            (TEMPLATE, self.template), (HTML, self.output),
            (GATES, self.gates), (CASES, self.cases)
        ):
            target.write_bytes(source.read_bytes())

    def render(self):
        return generator.render(self.template, self.gates, self.cases)

    def invoke(self, *extra):
        cmd = [
            sys.executable, str(SCRIPT),
            "--template", str(self.template),
            "--gates", str(self.gates),
            "--cases", str(self.cases),
            "--output", str(self.output),
            *extra,
        ]
        p = subprocess.run(cmd, capture_output=True, text=True, timeout=10)
        report = json.loads(p.stdout)
        self.assertEqual(report["pilot_decision"], "NO_GO")
        self.assertFalse(report["authorizes_deploy"])
        self.assertFalse(report["authorizes_credentials"])
        self.assertEqual(report["external_calls"], 0)
        return p.returncode, report

    def change_gate(self, key, value):
        with self.gates.open(encoding="utf-8", newline="") as f:
            reader = csv.DictReader(f)
            names = reader.fieldnames
            rows = list(reader)
        rows[0][key] = value
        with self.gates.open("w", encoding="utf-8", newline="") as f:
            writer = csv.DictWriter(f, fieldnames=names)
            writer.writeheader()
            writer.writerows(rows)

    def test_committed_page_identical_to_compilation(self):
        self.assertEqual(self.render().encode("utf-8"), HTML.read_bytes())
        rc, result = self.invoke("--check")
        self.assertEqual(rc, 0)
        self.assertTrue(result["generated_snapshot_valid"])

    def test_deterministic_repeat(self):
        self.assertEqual(self.render(), self.render())

    def test_no_go_does_not_turn_into_operational_go(self):
        result = self.render()
        self.assertIn("NO-GO", result)
        self.assertIn("connect-src 'none'", result)
        self.assertEqual(result.count('data-kind="gate" data-id='), 14)
        self.assertEqual(result.count('data-kind="case" data-id='), 13)

    def test_inconsistent_snapshot_fails(self):
        self.output.write_text(self.output.read_text().replace("CRM PR #57", "CRM PR #58"))
        rc, report = self.invoke("--check")
        self.assertEqual(rc, 1)
        self.assertEqual(report["error_code"], "DASHBOARD_OUT_OF_SYNC")

    def test_local_write_restores_expected_html_only(self):
        self.output.write_text("offline invalid")
        rc, report = self.invoke("--write")
        self.assertEqual(rc, 0)
        self.assertEqual(self.output.read_bytes(), HTML.read_bytes())
        rc, _ = self.invoke("--check")
        self.assertEqual(rc, 0)

    def test_closed_gate_rejected_before_render(self):
        self.change_gate("estado", "APROVADO")
        with self.assertRaises(Exception):
            self.render()
        rc, report = self.invoke("--write")
        self.assertEqual(rc, 1)
        self.assertFalse(report["generated_snapshot_valid"])
        self.assertEqual(self.output.read_bytes(), HTML.read_bytes())

    def test_missing_case_denied(self):
        self.cases.write_text("\n".join(self.cases.read_text().splitlines()[:-1]) + "\n")
        rc, _ = self.invoke("--write")
        self.assertEqual(rc, 1)

    def test_duplicate_marker_denied(self):
        self.template.write_text(self.template.read_text() + "\n{{GATE_ROWS}}")
        with self.assertRaises(generator.RenderError):
            self.render()

    def test_missing_marker_denied(self):
        self.template.write_text(self.template.read_text().replace("{{CASE_ROWS}}", ""))
        with self.assertRaises(generator.RenderError):
            self.render()

    def test_html_markup_and_attribute_are_escaped(self):
        attack = '''"><img src="https://invalid.example/asset" onerror="alert(1)">'''
        self.change_gate("evidencia_pendente", attack)
        result = self.render()
        self.assertNotIn("<img ", result)
        self.assertIn("&lt;img src=&quot;https://invalid.example/asset&quot;", result)
        self.assertNotIn('onerror="alert(1)"', result)
        self.assertIn("NO-GO", result)

    def test_case_content_is_html_escaped(self):
        source = self.cases.read_text()
        self.cases.write_text(source.replace("teste-identidade-real", "<b>TESTE</b>"))
        result = self.render()
        self.assertIn("&lt;b&gt;TESTE&lt;/b&gt;", result)
        self.assertNotIn("<b>TESTE</b>", result)

    def test_formula_like_csv_denied(self):
        self.change_gate("evidencia_pendente", "=HYPERLINK(A1)")
        rc, _ = self.invoke("--write")
        self.assertEqual(rc, 1)

    def test_empty_inventory_denied(self):
        self.gates.write_text("")
        rc, _ = self.invoke("--check")
        self.assertEqual(rc, 1)


if __name__ == "__main__":
    unittest.main()
