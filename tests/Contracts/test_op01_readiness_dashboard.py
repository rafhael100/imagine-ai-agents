"""Deterministic tests for offline OP-01 readiness review dashboard."""
import csv
import importlib.util
import json
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SCRIPT = ROOT / "tools/check_op01_readiness_dashboard.py"
HTML = ROOT / "examples/r01-op01-readiness-offline.html"
GATES = ROOT / "docs/evidencias/aia-r01-gates-op01.csv"
CASES = ROOT / "docs/evidencias/op01-casos-homologacao.csv"
spec = importlib.util.spec_from_file_location("op01_dashboard_checker", SCRIPT)
checker = importlib.util.module_from_spec(spec)
spec.loader.exec_module(checker)

class DashboardChecks(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        directory = Path(self.tmp.name)
        self.html = directory / "dashboard.html"
        self.gates = directory / "gates.csv"
        self.cases = directory / "cases.csv"
        self.html.write_bytes(HTML.read_bytes())
        self.gates.write_bytes(GATES.read_bytes())
        self.cases.write_bytes(CASES.read_bytes())
    def verify(self):
        return checker.check(self.html, self.gates, self.cases)
    def test_complete_inventory_and_no_go(self):
        report = self.verify()
        self.assertEqual(report["gates"], 14)
        self.assertEqual(report["cases"], 13)
        self.assertEqual(report["pilot_decision"], "NO_GO")
        self.assertEqual(report["external_calls"], 0)
    def test_no_external_assets_or_network(self):
        self.assertIn("connect-src 'none'", self.html.read_text())
        self.verify()
    def test_missing_gate_denied(self):
        self.gates.write_text("\n".join(self.gates.read_text().splitlines()[:-1])+"\n")
        with self.assertRaises(Exception):
            self.verify()
    def test_misleading_approval_denied(self):
        self.gates.write_text(self.gates.read_text().replace(",ABERTO,", ",HOMOLOGADO,", 1))
        with self.assertRaises(Exception):
            self.verify()
    def test_removed_card_denied(self):
        source = self.html.read_text()
        self.html.write_text(source.replace('data-id="R01-G01"', 'data-id="MISSING"'))
        with self.assertRaises(checker.DashboardError):
            self.verify()
    def test_modified_case_detected(self):
        self.html.write_text(self.html.read_text().replace(
            "teste-identidade-real", "evidencia-forjada"))
        with self.assertRaises(checker.DashboardError):
            self.verify()
    def test_network_code_detected(self):
        self.html.write_text(self.html.read_text().replace(
            "refresh();", "fetch('/danger'); refresh();", 1))
        with self.assertRaises(checker.DashboardError):
            self.verify()
    def test_missing_no_go_detected(self):
        self.html.write_text(self.html.read_text().replace("NO-GO", "PRONTO"))
        with self.assertRaises(checker.DashboardError):
            self.verify()
    def test_case_sheet_unapproved_execution_rejected(self):
        self.cases.write_text(self.cases.read_text().replace("NAO_EXECUTADO", "APROVADO", 1))
        with self.assertRaises(Exception):
            self.verify()

if __name__ == "__main__":
    unittest.main()
