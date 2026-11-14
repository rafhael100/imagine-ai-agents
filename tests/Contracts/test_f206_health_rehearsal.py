"""F206 closed and tamper-resistant health rehearsal (synthetic PHP only)."""
import copy
from pathlib import Path
import shutil
import subprocess
import sys
import unittest

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "tools"))
import verify_f206_health as verifier


@unittest.skipUnless(shutil.which("php"), "PHP CLI missing")
class F206Tests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.original = verifier.fixture()

    def changed(self, mutation):
        data = copy.deepcopy(self.original)
        mutation(data)
        with self.assertRaises(ValueError):
            verifier.verify(data)

    def test_genuine_php_fixture_is_closed(self):
        self.assertTrue(verifier.verify(self.original))
        self.assertEqual(list(self.original["stages"]), list(verifier.STAGES))

    def test_genuine_fixture_is_deterministic(self):
        self.assertEqual(self.original, verifier.fixture())
        result = subprocess.run([sys.executable, str(ROOT / "tools/verify_f206_health.py")],
                                cwd=ROOT, capture_output=True, text=True, timeout=30)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn("stages=8 operational_decision=NO_GO", result.stdout)

    def test_false_operational_go_denied(self):
        self.changed(lambda x: x.update(operational_decision="GO"))

    def test_false_nested_authorization_denied(self):
        self.changed(lambda x: x["stages"]["full_capacity"].update(real_authorization=True))

    def test_invalid_unknown_after_resume_denied(self):
        self.changed(lambda x: x["stages"]["resumed"]["triage"]["queue"].update(indeterminate=0))

    def test_unreviewed_incident_cannot_be_hidden(self):
        self.changed(lambda x: x["stages"]["resumed"]["triage"]["review"].update(
            reviewed_unresolved=0))

    def test_bool_cannot_impersonate_counter(self):
        self.changed(lambda x: x["stages"]["pending"]["capacity"]["jobs"].update(used=True))

    def test_pressure_alert_is_strict(self):
        self.changed(lambda x: x["stages"]["near_capacity"]["capacity"].update(alert="NORMAL"))

    def test_cross_view_count_conflict_denied(self):
        self.changed(lambda x: x["stages"]["full_capacity"]["capacity"]["jobs"].update(
            remaining=1))

    def test_bogus_additional_ref_denied(self):
        self.changed(lambda x: x["stages"]["paused"].update(run_ref="SYNTHETIC_SECRET"))

    def test_paused_resume_budget_cannot_be_falsified(self):
        self.changed(lambda x: x["stages"]["paused"]["capacity"].update(
            resume_budget_available=False))

    def test_extra_stage_is_rejected(self):
        self.changed(lambda x: x["stages"].update(unexpected=x["stages"]["idle"]))


class F206StaticTests(unittest.TestCase):
    def test_cli_never_mentions_network_or_real_authorization(self):
        php = (ROOT / "examples/f206-queue-health-demo.php").read_text(encoding="utf-8")
        self.assertIn("MockQueueHealthSnapshot", php)
        self.assertIn("SYNTHETIC_QUEUE_HEALTH_REHEARSAL_V1", php)
        self.assertIn("NO_GO", php)
        for forbidden in ("curl_init(", "file_get_contents('http", "PDO(", "mysqli_",
                          "exec(", "shell_exec(", "API_KEY"):
            self.assertNotIn(forbidden, php)

    def test_gate_keeps_verification_mandatory(self):
        gate = (ROOT / "tools/run_r01_offline_quality_gate.sh").read_text(encoding="utf-8")
        self.assertIn("python3 tools/verify_f206_health.py", gate)
        self.assertIn("test_f206_health_rehearsal.py", gate)
        self.assertIn("operational_decision=NO_GO", gate)


    @unittest.skipUnless(shutil.which("php"), "PHP CLI missing")
    def test_static_report_contains_only_eight_verified_states(self):
        import render_f206_health as renderer
        page = renderer.render(verifier.fixture())
        self.assertEqual(page.count("<tr>"), 9)
        self.assertIn("Decisão operacional: NO-GO", page)
        self.assertIn("CAPACITY_EXHAUSTED", page)
        self.assertIn("REVIEW_REQUIRED", page)
        self.assertIn("script-src 'none'", page)
        self.assertNotIn("<script", page)
        self.assertNotIn("SYNTHETIC_F206_RUN_", page)

    @unittest.skipUnless(shutil.which("php"), "PHP CLI missing")
    def test_static_report_denies_false_go_before_rendering(self):
        import render_f206_health as renderer
        bad = verifier.fixture()
        bad["stages"]["paused"]["operational_decision"] = "GO"
        with self.assertRaises(ValueError):
            renderer.render(bad)

    @unittest.skipUnless(shutil.which("php"), "PHP CLI missing")
    def test_static_renderer_is_deterministic_cli(self):
        path = str(ROOT / "tools/render_f206_health.py")
        a = subprocess.run([sys.executable, path], cwd=ROOT,
                           capture_output=True, text=True, timeout=25)
        b = subprocess.run([sys.executable, path], cwd=ROOT,
                           capture_output=True, text=True, timeout=25)
        self.assertEqual(a.returncode, 0, a.stderr)
        self.assertEqual(a.stdout, b.stdout)
        self.assertTrue(a.stdout.startswith("<!doctype html>"))

    def test_static_renderer_has_no_network_or_persistent_write(self):
        code = (ROOT / "tools/render_f206_health.py").read_text(encoding="utf-8")
        self.assertIn("verify(data)", code)
        self.assertIn("escape(str(cell), quote=True)", code)
        self.assertIn("script-src 'none'", code)
        for forbidden in ("fetch(", "requests.", "urllib.", "open(",
                          "write_text(", "FileIO(", "subprocess.run("):
            self.assertNotIn(forbidden, code)


if __name__ == "__main__":
    unittest.main()
