"""F199: preview exposes no live permissions or hidden operational access."""
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]
HTML = ROOT / "examples/f108-laboratorio-navegavel.html"
SMOKE = ROOT / "tests/Browser/f108_smoke.cjs"


class F199Tests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.page = HTML.read_text(encoding="utf-8")

    def test_three_fixed_templates_have_read_denied_gate_attributes(self):
        for template in ("crm", "performance", "universidade"):
            self.assertIn(template + ":Object.freeze({", self.page)
        for field in ("source", "denied", "gate"):
            self.assertEqual(self.page.count("agentDraft.template." + field), 1)
        self.assertIn("R01-G01..G11", self.page)
        self.assertIn("UNTRUSTED_DATA", self.page)
        self.assertIn("sem contas conectadas", self.page)

    def test_no_new_user_supplied_authority(self):
        self.assertIn("Object.prototype.hasOwnProperty.call(agentDraftTemplates,key)", self.page)
        self.assertIn('agentDraft=Object.freeze({name,template:agentDraftTemplates[key]})', self.page)
        for banned in ("agentDraft.permissions=", "agentDraft.role=", "agentDraft.token=",
                       "agentDraft.approval=", "innerHTML=agentDraft"):
            self.assertNotIn(banned, self.page)

    def test_accessible_preview_slots_and_empty_reset(self):
        for field in ("source", "denied", "gate"):
            self.assertEqual(self.page.count('id="agent-draft-preview-' + field + '"'), 1)
            self.assertIn('get("agent-draft-preview-' + field +
                          '").textContent=agentDraft===null?"—"', self.page)
        self.assertIn('aria-label="Prévia local do agente"', self.page)
        self.assertIn("NÃO PUBLICADO · NO-GO", self.page)

    def test_export_schema_stays_name_and_template_only(self):
        transfer = (ROOT / "tests/Contracts/test_f187_draft_transfer.py").read_text(encoding="utf-8")
        self.assertIn("SYNTHETIC_AGENT_DRAFTS_V1", transfer)
        self.assertIn("name", transfer)
        self.assertIn("template", transfer)
        self.assertNotIn('drafts:[{source:', self.page)

    def test_existing_browser_smoke_checks_every_model(self):
        smoke = SMOKE.read_text(encoding="utf-8")
        for term in ("Lead fictício: referência e resumo mínimo", "sem contas conectadas",
                     "UNTRUSTED_DATA", 'agent-draft-preview-gate'):
            self.assertIn(term, smoke)


if __name__ == "__main__":
    unittest.main()
