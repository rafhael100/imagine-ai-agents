"""F193 static report regressions: output cannot become a CRM dashboard."""
import importlib.util
import pathlib
import shutil
import subprocess
import sys
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[2]
TOOLS = ROOT / "tools"
sys.path.insert(0, str(TOOLS))
SPEC = importlib.util.spec_from_file_location("f193_renderer", TOOLS / "render_f190_supervision.py")
render_module = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(render_module)


def sample():
    def snap(state, pending, done):
        return {
            "contract": "SYNTHETIC_QUEUE_SUPERVISION_V1",
            "queue": {
                "pending": pending, "reserved": 0, "completed": done,
                "failed": 0, "cancelled": 0, "indeterminate": 1,
            },
            "review": {
                "awaiting_first": 0, "awaiting_second": 0,
                "reviewed_unresolved": 1, "conflict": 0,
            },
            "admission": {"state": state},
            "operational_decision": "NO_GO",
            "real_authorization": False,
            "external_calls": 0,
            "write_calls": 0,
        }
    return {
        "contract": "SYNTHETIC_QUEUE_PAUSE_REHEARSAL_V1",
        "paused": snap("PAUSED", 1, 0),
        "resumed": snap("OPEN", 0, 1),
        "operational_decision": "NO_GO",
        "real_authorization": False,
        "external_calls": 0,
        "write_calls": 0,
    }


class F193StaticReportTests(unittest.TestCase):
    def test_html_is_utf8_accessible_and_fully_static(self):
        page = render_module.render(sample())
        self.assertTrue(page.startswith("<!doctype html>"))
        self.assertIn('lang="pt-BR"', page)
        self.assertIn('aria-labelledby="paused-title"', page)
        self.assertIn('aria-labelledby="resumed-title"', page)
        self.assertIn("NO-GO", page)
        self.assertIn("SOMENTE SIMULADA", page)

    def test_both_snapshots_are_visible_without_ids(self):
        page = render_module.render(sample())
        self.assertIn("Durante a pausa", page)
        self.assertIn("Após a retomada", page)
        self.assertIn("Admissão: PAUSADA", page)
        self.assertIn("Admissão: ABERTA", page)
        self.assertEqual(page.count("Revisados sem conclusão"), 2)
        self.assertEqual(page.count("<section "), 2)
        for secret_name in ("claim_ref", "run_ref", "SYNTHETIC_F190_RUN", "token="):
            self.assertNotIn(secret_name, page)

    def test_no_active_controls_external_resources_or_network(self):
        page = render_module.render(sample()).lower()
        for forbidden in ("<script", "<iframe", "<form", "<button", "<input",
                          "https://", "http://", "<img", "src=", "fetch(",
                          "localstorage", "xmlhttprequest"):
            self.assertNotIn(forbidden, page)

    def test_deterministic_render(self):
        self.assertEqual(render_module.render(sample()), render_module.render(sample()))

    def test_invalid_go_is_denied(self):
        obj = sample()
        obj["paused"]["operational_decision"] = "GO"
        with self.assertRaises(ValueError):
            render_module.render(obj)

    def test_injected_html_as_counter_is_denied(self):
        obj = sample()
        obj["paused"]["queue"]["pending"] = "<img src=x onerror=alert(1)>"
        with self.assertRaises(ValueError):
            render_module.render(obj)

    def test_extra_payload_field_is_denied(self):
        obj = sample()
        obj["resumed"]["customer_id"] = 42
        with self.assertRaises(ValueError):
            render_module.render(obj)

    def test_indeterminate_cannot_be_hidden(self):
        obj = sample()
        obj["resumed"]["queue"]["indeterminate"] = 0
        with self.assertRaises(ValueError):
            render_module.render(obj)

    @unittest.skipUnless(shutil.which("php"), "PHP missing")
    def test_local_php_rehearsal_renders_static_page(self):
        result = subprocess.run(
            [sys.executable, str(TOOLS / "render_f190_supervision.py")],
            cwd=ROOT, input="", text=True, capture_output=True, timeout=30,
        )
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn("NO-GO", result.stdout)
        self.assertIn("Concluídos", result.stdout)
        self.assertNotIn("run_ref", result.stdout)


if __name__ == "__main__":
    unittest.main()
