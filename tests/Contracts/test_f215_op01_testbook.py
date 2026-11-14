"""F215: 13 review-ready specifications, zero real executions."""
from pathlib import Path
import csv
import json
import sys
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "tools"))
import build_op01_testbook as f215
import check_r01_readiness as readiness
import generate_op01_bilateral_packet as packet


class F215Testbook(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        path = Path(self.tmp.name)
        self.gates, self.cases, self.packet = (
            path / "gates.csv", path / "cases.csv", path / "packet.json")
        self.gates.write_bytes(readiness.GATE_FILE.read_bytes())
        self.cases.write_bytes(readiness.CASE_FILE.read_bytes())
        self.packet.write_bytes(packet.OUTPUT.read_bytes())

    def book(self):
        return f215.compile_testbook(self.gates, self.cases, self.packet)

    def change_case(self, field, value):
        with self.cases.open(newline="", encoding="utf-8") as stream:
            reader = csv.DictReader(stream)
            rows = list(reader)
            headers = reader.fieldnames
        rows[0][field] = value
        with self.cases.open("w", newline="", encoding="utf-8") as stream:
            writer = csv.DictWriter(stream, fieldnames=headers)
            writer.writeheader()
            writer.writerows(rows)

    def test_11_negative_2_positive_and_no_real_execution(self):
        book = self.book()
        self.assertEqual(book["operational_decision"], "NO_GO")
        self.assertEqual(book["real_scenarios_pending"], 13)
        self.assertEqual(book["real_scenarios_executed"], 0)
        self.assertEqual(book["open_gates"], 14)
        self.assertEqual(book["independent_reviews_pending"], 7)
        self.assertEqual(len(book["scenarios"]), 13)
        self.assertEqual(sum(s["expected_synthetic_behavior"] ==
                             "DENIED_OR_NOT_FOUND" for s in book["scenarios"]), 11)
        self.assertEqual(sum(s["expected_synthetic_behavior"] ==
                             "READ_MINIMAL_ONLY" for s in book["scenarios"]), 2)

    def test_every_case_has_independent_review_and_concrete_steps(self):
        for spec in self.book()["scenarios"]:
            self.assertEqual(spec["real_execution_status"], "NAO_EXECUTADO")
            self.assertIsNone(spec["real_observation"])
            self.assertFalse(spec["approved"])
            self.assertFalse(spec["permits_customer_data"])
            self.assertFalse(spec["permits_deployment"])
            self.assertTrue(spec["design"])
            self.assertTrue(spec["suggested_reviewers"])
            self.assertTrue(spec["evidence_pending"])
            self.assertEqual(len(spec["preconditions"]), 3)
        self.assertEqual({s["id"] for s in self.book()["scenarios"]},
                         set(readiness.REQUIRED_CASES))

    def test_change_status_to_pass_cannot_be_accepted(self):
        self.change_case("estado_execucao", "PASS")
        with self.assertRaises(readiness.InventoryError):
            self.book()

    def test_inject_an_extra_case_denies(self):
        with self.cases.open("a", encoding="utf-8") as f:
            f.write("OP01-X99,NEGATIVO,injected,NEGAR,NAO_EXECUTADO,fake\n")
        with self.assertRaises(readiness.InventoryError):
            self.book()

    def test_change_expected_does_not_become_approval(self):
        self.change_case("resultado_esperado", "LEITURA_MINIMA")
        with self.assertRaises(readiness.InventoryError):
            self.book()

    def test_tampered_packet_fails_closed(self):
        self.packet.write_text("{}", encoding="utf-8")
        with self.assertRaises(f215.plan.PlanError):
            self.book()

    def test_no_network_or_credential_export(self):
        code = (ROOT / "tools/build_op01_testbook.py").read_text()
        for forbidden in ("import requests", "urllib.request", "import socket",
                          "subprocess", "openai.", "http://", "https://",
                          "Authorization:", "Bearer ", "create_token"):
            self.assertNotIn(forbidden, code)
        report = self.book()
        self.assertFalse(report["permits_credentials"])
        self.assertFalse(report["permits_deploy"])
        self.assertFalse(report["permits_customer_data"])
        self.assertEqual(report["external_calls"], 0)

    def test_markdown_is_actionable_but_never_claims_success(self):
        text = f215.markdown(self.book())
        self.assertEqual(text.count("\n## OP01-"), 13)
        self.assertIn("NO-GO", text)
        self.assertIn("NAO_EXECUTADO", text)
        self.assertIn("READ_MINIMAL_ONLY", text)
        self.assertNotIn("APROVADO", text)

    def test_offline_gate_requires_testbook(self):
        gate = (ROOT / "tools/run_r01_offline_quality_gate.sh").read_text()
        self.assertIn("python3 tools/build_op01_testbook.py --check", gate)
        self.assertIn("test_f215_op01_testbook.py", gate)
        self.assertLess(gate.index("python3 tools/plan_op01_release.py --check"),
                        gate.index("python3 tools/build_op01_testbook.py --check"))


if __name__ == "__main__":
    unittest.main()
