"""F185: offline agent draft configuration with no publication or authority."""
from __future__ import annotations
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
PAGE = ROOT / "examples/f108-laboratorio-navegavel.html"
SMOKE = ROOT / "tests/Browser/f108_smoke.cjs"

class F185AgentDraftWorkbenchTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.html = PAGE.read_text(encoding="utf-8")
        cls.smoke = SMOKE.read_text(encoding="utf-8")

    def test_accessible_name_model_preview_and_nonoperational_controls(self):
        for ident in (
            "agent-draft-template", "agent-draft-name", "agent-draft-generate",
            "agent-draft-test", "agent-draft-reset", "agent-draft-status",
            "agent-draft-preview-name", "agent-draft-preview-template",
            "agent-draft-preview-purpose", "agent-draft-preview-state",
        ):
            with self.subTest(ident=ident):
                self.assertEqual(self.html.count(f'id="{ident}"'), 1)
        self.assertIn('for="agent-draft-template"', self.html)
        self.assertIn('for="agent-draft-name"', self.html)
        self.assertIn('role="status" aria-live="polite"', self.html)
        self.assertIn('id="agent-draft-name" type="text" maxlength="48" autocomplete="off"', self.html)
        self.assertIn('id="agent-draft-test" disabled', self.html)

    def test_template_and_scenario_are_closed_and_not_user_supplied(self):
        for token in (
            "crm:Object.freeze(", "performance:Object.freeze(",
            "universidade:Object.freeze(", 'scenario:"allow"',
            'scenario:"dynamic"', 'scenario:"injection"',
            'Object.prototype.hasOwnProperty.call(agentDraftTemplates,key)',
            'if(!["allow","dynamic","injection"].includes(scenario))return;',
        ):
            self.assertIn(token, self.html)

    def test_restricted_names_never_become_executable_markup_or_prompts(self):
        for token in (
            'raw.length>80', 'raw.normalize("NFC").trim()',
            'name.length<3||name.length>48',
            r'/^[\p{L}\p{N} ._-]+$/u.test(name)',
            'get("agent-draft-preview-name").textContent=',
            'agentDraft=Object.freeze({name,template:agentDraftTemplates[key]})',
        ):
            self.assertIn(token, self.html)
        self.assertNotIn("innerHTML", self.html)
        self.assertNotIn("insertAdjacentHTML", self.html)
        self.assertNotIn("eval(", self.html)
        self.assertNotIn("new Function(", self.html)

    def test_stale_changes_fail_closed_and_reset_clears_transient_draft(self):
        for token in (
            'get("agent-draft-template").addEventListener("change",invalidateAgentDraft)',
            'get("agent-draft-name").addEventListener("input",invalidateAgentDraft)',
            'agentDraft=null;',
            'get("agent-draft-test").disabled=agentDraft===null;',
            'get("agent-draft-name").value="";',
            'get("agent-draft-template").value="crm";',
            'if(agentDraft===null)return;',
        ):
            self.assertIn(token, self.html)

    def test_browser_smoke_covers_each_template_and_hostile_inputs(self):
        for token in (
            'byId("agent-draft-generate").click()',
            'byId("agent-draft-test").click()',
            'byId("agent-draft-reset").click()',
            'byId("agent-draft-template").value="crm"',
            'byId("agent-draft-template").value="performance"',
            'byId("agent-draft-template").value="universidade"',
            'byId("agent-draft-template").value="__proto__"',
            'byId("agent-draft-name").value="<img src=x onerror=alert(1)>"',
            '"A".repeat(100000)', 'byId("audit-runs").textContent, "0"',
        ):
            self.assertIn(token, self.smoke)

    def test_no_persistence_network_api_or_automatic_business_write(self):
        self.assertIn("connect-src 'none'", self.html)
        for token in (
            "fetch(", "XMLHttpRequest(", "WebSocket(", "localStorage(",
            "sessionStorage(", "<script src=", "<iframe",
            "http://", "https://", "window.open(", "document.cookie",
        ):
            with self.subTest(token=token):
                self.assertNotIn(token, self.html)
        self.assertIn('RASCUNHO LOCAL · SEM APROVAÇÃO', self.html)
        self.assertIn('NÃO PUBLICADO · NO-GO', self.html)

    def test_mobile_layout_reuses_existing_breakpoint(self):
        self.assertIn('.draft-fields{display:grid;', self.html)
        self.assertIn('@media(max-width:700px){.draft-fields,.draft-result dl{grid-template-columns:1fr}}', self.html)
        self.assertIn('overflow-wrap:anywhere', self.html)


if __name__ == "__main__":
    unittest.main()
