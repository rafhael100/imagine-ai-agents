"""F212: real missing gates and cases become an actionable, always-NO-GO plan."""
import csv
from copy import deepcopy
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "tools"))
import plan_op01_release as release
import check_r01_readiness as readiness
import generate_op01_bilateral_packet as packet


class F212CriticalPath(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        path = Path(self.tmp.name)
        self.gates = path / "gates.csv"
        self.cases = path / "cases.csv"
        self.packet = path / "packet.json"
        self.gates.write_bytes(readiness.GATE_FILE.read_bytes())
        self.cases.write_bytes(readiness.CASE_FILE.read_bytes())
        self.packet.write_bytes(packet.OUTPUT.read_bytes())

    def compile(self):
        return release.compile_plan(
            ledger=self.gates, cases=self.cases, packet_file=self.packet)

    def test_full_coverage_ordered_and_readonly(self):
        report = self.compile()
        self.assertEqual(report["operational_decision"], "NO_GO")
        self.assertEqual([w["id"] for w in report["workstreams"]],
                         ["W01", "W02", "W03", "W04", "W05"])
        self.assertEqual(report["open_gates"], 14)
        self.assertEqual(report["pending_tests"], 13)
        self.assertEqual(report["pending_reviews"], 7)
        self.assertEqual(report["real_tests_executed"], 0)
        self.assertIs(report["integration_enabled"], False)
        self.assertIs(report["can_deploy"], False)
        self.assertIs(report["can_use_credentials"], False)
        self.assertEqual(report["external_calls"], 0)
        self.assertEqual(
            {g for w in report["workstreams"] for g in w["gates"]},
            set(readiness.REQUIRED_GATES))
        self.assertEqual(
            {c for w in report["workstreams"] for c in w["test_cases"]},
            set(readiness.REQUIRED_CASES))

    def test_every_stage_is_blocked_and_has_concrete_evidence(self):
        self.assertEqual(
            {w["status"] for w in self.compile()["workstreams"]},
            {"BLOCKED_UNTIL_INDEPENDENT_REVIEW"})
        for stage in self.compile()["workstreams"]:
            self.assertFalse(stage["authorizes_access"])
            self.assertEqual(stage["real_tests_executed"], 0)
            self.assertTrue(stage["required_evidence"])
            self.assertTrue(stage["required_tests"])
            self.assertTrue(stage["review_slots"])
        self.assertEqual(self.compile()["workstreams"][-1]["depends_on"],
                         ["W01", "W02", "W03", "W04"])

    def mutate_csv(self, path, field, value):
        with path.open(newline="", encoding="utf-8") as f:
            reader = csv.DictReader(f)
            rows = list(reader)
            fields = reader.fieldnames
        rows[0][field] = value
        with path.open("w", newline="", encoding="utf-8") as f:
            writer = csv.DictWriter(f, fieldnames=fields)
            writer.writeheader()
            writer.writerows(rows)

    def test_fake_gate_approval_rejected(self):
        self.mutate_csv(self.gates, "estado", "APROVADO")
        with self.assertRaises(readiness.InventoryError):
            self.compile()

    def test_fake_real_scenario_execution_rejected(self):
        self.mutate_csv(self.cases, "estado_execucao", "PASS")
        with self.assertRaises(readiness.InventoryError):
            self.compile()

    def test_stale_packet_rejected(self):
        self.packet.write_text("{}", encoding="utf-8")
        with self.assertRaises(release.PlanError):
            self.compile()

    def test_review_progress_does_not_infer_a_release(self):
        self.mutate_csv(self.gates, "estado", "EM_REVISAO")
        with self.assertRaises(release.PlanError):
            self.compile()

    def test_mandatory_no_network_gate_and_subprocess_result(self):
        gate = (ROOT / "tools/run_r01_offline_quality_gate.sh").read_text()
        self.assertIn("python3 tools/plan_op01_release.py --check", gate)
        self.assertIn("test_f212_op01_critical_path.py", gate)
        res = subprocess.run(
            [sys.executable, str(ROOT / "tools/plan_op01_release.py"), "--check"],
            capture_output=True, text=True, timeout=10)
        self.assertEqual(res.returncode, 0, res.stderr)
        self.assertIn("operational_decision=NO_GO", res.stdout)

    def test_markdown_has_all_five_workstreams_without_approved_state(self):
        md = release.render_markdown(self.compile())
        self.assertEqual(md.count("\n## W"), 5)
        self.assertIn("NO-GO", md)
        self.assertIn("OP01-P02", md)
        self.assertIn("CRM-AUT", md)
        self.assertNotIn("APROVADO", md)

    def test_no_privileged_tools_in_planner(self):
        script = (ROOT / "tools/plan_op01_release.py").read_text()
        for forbidden in ("import requests", "urllib.request", "subprocess",
                          "socket.", "openai.", "api.github.com", "os.system",
                          "shutil.rmtree", "Authorization:", "Bearer "):
            self.assertNotIn(forbidden, script)


if __name__ == "__main__":
    unittest.main()
