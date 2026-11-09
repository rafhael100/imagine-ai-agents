"""Negative controls for the R01/OP-01 static inventory linter; no network."""
import csv
import json
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SCRIPT = ROOT / "tools/check_r01_readiness.py"
GATES = ROOT / "docs/evidencias/aia-r01-gates-op01.csv"
CASES = ROOT / "docs/evidencias/op01-casos-homologacao.csv"


class ReadinessInventoryTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.gates = Path(self.tmp.name) / "gates.csv"
        self.cases = Path(self.tmp.name) / "cases.csv"
        self.gates.write_bytes(GATES.read_bytes())
        self.cases.write_bytes(CASES.read_bytes())

    def run_checker(self):
        process = subprocess.run(
            [sys.executable, str(SCRIPT), "--ledger", str(self.gates),
             "--cases", str(self.cases)],
            capture_output=True, text=True, check=False, timeout=10,
        )
        report = json.loads(process.stdout)
        self.assertEqual(report["pilot_decision"], "NO_GO")
        self.assertFalse(report["authorizes_deploy"])
        self.assertFalse(report["authorizes_credentials"])
        self.assertEqual(report["external_calls"], 0)
        return process.returncode, report

    def alter(self, path, mutation):
        with path.open(encoding="utf-8", newline="") as stream:
            reader = csv.DictReader(stream)
            fields = reader.fieldnames
            rows = list(reader)
        mutation(rows)
        with path.open("w", encoding="utf-8", newline="") as stream:
            writer = csv.DictWriter(stream, fieldnames=fields)
            writer.writeheader()
            writer.writerows(rows)

    def assert_rejected(self, code):
        status, report = self.run_checker()
        self.assertEqual(status, 1)
        self.assertFalse(report["inventory_valid"])
        self.assertEqual(report["error_code"], code)

    def test_baseline_is_complete_but_no_go(self):
        status, report = self.run_checker()
        self.assertEqual(status, 0)
        self.assertTrue(report["inventory_valid"])
        self.assertEqual(report["gate_count"], 14)
        self.assertEqual(report["gates_open"], 14)
        self.assertEqual(report["case_count"], 13)
        self.assertEqual(report["cases_not_executed"], 13)

    def test_gate_missing(self):
        self.alter(self.gates, lambda rows: rows.pop())
        self.assert_rejected("GATE_COVERAGE_INCOMPLETE")

    def test_gate_duplicate(self):
        self.alter(self.gates, lambda rows: rows.append(rows[0].copy()))
        self.assert_rejected("GATE_DUPLICATE")

    def test_unknown_gate(self):
        self.alter(self.gates, lambda rows: rows[0].update(id="R01-G99"))
        self.assert_rejected("GATE_SCOPE_INVALID")

    def test_gate_type_drift(self):
        self.alter(self.gates, lambda rows: rows[0].update(tipo="crm"))
        self.assert_rejected("GATE_SCOPE_INVALID")

    def test_gate_self_approval_rejected(self):
        for status in ("APROVADO", "HOMOLOGADO", "FECHADO"):
            with self.subTest(status=status):
                self.gates.write_bytes(GATES.read_bytes())
                self.alter(self.gates, lambda rows: rows[0].update(estado=status))
                self.assert_rejected("UNVERIFIED_GATE_CLOSURE")

    def test_review_state_is_still_no_go(self):
        self.alter(self.gates, lambda rows: rows[0].update(estado="EM_REVISAO"))
        status, report = self.run_checker()
        self.assertEqual(status, 0)
        self.assertEqual(report["gates_under_review"], 1)
        self.assertEqual(report["pilot_decision"], "NO_GO")

    def test_missing_required_evidence_label(self):
        self.alter(self.gates, lambda rows: rows[0].update(evidencia_pendente=""))
        self.assert_rejected("CELL_INVALID")

    def test_csv_formula_prefix_rejected(self):
        self.alter(self.gates, lambda rows: rows[0].update(autoridade_sugerida="=SUM(A1:A3)"))
        self.assert_rejected("CELL_INVALID")

    def test_unknown_column_rejected(self):
        lines = self.gates.read_text(encoding="utf-8").splitlines()
        lines[0] += ",EXTRA"
        self.gates.write_text("\n".join(lines) + "\n", encoding="utf-8")
        self.assert_rejected("HEADER_INVALID")

    def test_case_missing(self):
        self.alter(self.cases, lambda rows: rows.pop())
        self.assert_rejected("CASE_COVERAGE_INCOMPLETE")

    def test_case_duplicate(self):
        self.alter(self.cases, lambda rows: rows.append(rows[0].copy()))
        self.assert_rejected("CASE_DUPLICATE")

    def test_case_outcome_expansion(self):
        self.alter(self.cases, lambda rows: rows[-1].update(resultado_esperado="ALTERACAO"))
        self.assert_rejected("CASE_EXPECTATION_INVALID")

    def test_claimed_case_execution_rejected(self):
        self.alter(self.cases, lambda rows: rows[0].update(estado_execucao="PASS"))
        self.assert_rejected("UNVERIFIED_CASE_EXECUTION")

    def test_no_file_no_go(self):
        self.cases.unlink()
        self.assert_rejected("CSV_UNREADABLE")


if __name__ == "__main__":
    unittest.main()
