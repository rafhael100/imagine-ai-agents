"""F186: bounded, idempotent, local-only agent draft collection."""
from __future__ import annotations
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
PAGE = ROOT / "examples/f108-laboratorio-navegavel.html"
SMOKE = ROOT / "tests/Browser/f108_smoke.cjs"

class F186DraftCollectionTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.html = PAGE.read_text(encoding="utf-8")
        cls.smoke = SMOKE.read_text(encoding="utf-8")

    def test_bounded_in_memory_collection_only(self):
        for piece in (
            "const sessionAgentDrafts=[];", "const MAX_SESSION_DRAFTS=5;",
            "sessionAgentDrafts.length>=MAX_SESSION_DRAFTS",
            'get("agent-draft-count").textContent=String(sessionAgentDrafts.length)+" / 5";',
            "sessionAgentDrafts.splice(0);",
        ):
            with self.subTest(piece=piece):
                self.assertIn(piece, self.html)
        self.assertIn('Limite local de cinco rascunhos atingido', self.html)

    def test_case_insensitive_name_dedupe_and_conflict(self):
        self.assertIn('agentDraft.name.toLocaleLowerCase("pt-BR")', self.html)
        self.assertIn('sessionAgentDrafts.find(x=>x.lookup===lookup)', self.html)
        self.assertIn('existing.templateKey===key?', self.html)
        self.assertIn('Este rascunho já está na lista (sem duplicar)', self.html)
        self.assertIn('Nome já utilizado por outro modelo nesta sessão.', self.html)
        self.assertIn('Object.freeze({name:agentDraft.name,templateKey:key,lookup})', self.html)

    def test_no_stale_or_deleted_draft_can_be_reopened(self):
        for piece in (
            'agentDraft.template!==agentDraftTemplates[key]||name!==agentDraft.name',
            'if(!sessionAgentDrafts.includes(record))return;',
            'const index=sessionAgentDrafts.indexOf(record);',
            'if(index<0)return;',
            'sessionAgentDrafts.splice(index,1);',
            'clearAgentDraft();',
        ):
            self.assertIn(piece, self.html)
        self.assertIn('Prévia desatualizada; valide os campos novamente.', self.html)

    def test_actions_accessible_and_rendered_with_safe_text_only(self):
        for ident in (
            "agent-draft-save", "agent-draft-collection",
            "agent-draft-count", "agent-draft-collection-status",
            "agent-draft-clear-all",
        ):
            self.assertEqual(self.html.count(f'id="{ident}"'), 1)
        self.assertIn('role="status" aria-live="polite" id="agent-draft-collection-status"', self.html)
        self.assertIn('get("agent-draft-save").disabled=agentDraft===null;', self.html)
        self.assertIn('label.textContent=record.name+', self.html)
        self.assertIn('root.replaceChildren();', self.html)
        self.assertNotIn('innerHTML', self.html)

    def test_dom_smoke_covers_capacity_dedupe_restore_delete_and_reset(self):
        for phrase in (
            '"1 / 5", "first saved in session"',
            '"1 / 5", "exact duplicate idempotent"',
            '"5 / 5", "capacity limited to five"',
            '"5 / 5", "sixth denied with no eviction"',
            'byId("agent-draft-name").value="Nome Alterado Sem Evento"',
            'savedFirst.children[1].click();',
            'savedFirst.children[2].click();',
            '"stale detached row cannot resurrect deleted draft"',
            'byId("agent-draft-clear-all").click();',
            '"draft collection did not execute scenarios"',
        ):
            self.assertIn(phrase, self.smoke)

    def test_no_persistence_network_or_untrusted_execution(self):
        self.assertIn("connect-src 'none'", self.html)
        for token in (
            'fetch(', 'XMLHttpRequest(', 'WebSocket(', 'localStorage(',
            'sessionStorage(', 'indexedDB', 'document.cookie', '<script src=',
            'http://', 'https://', 'eval(', 'new Function(',
        ):
            with self.subTest(token=token):
                self.assertNotIn(token, self.html)
        self.assertIn('Nenhum item foi adicionado.', self.html)

    def test_no_go_and_unpublished_preview_are_preserved(self):
        self.assertIn('NÃO PUBLICADO · NO-GO', self.html)
        self.assertIn('RASCUNHO LOCAL · SEM APROVAÇÃO', self.html)
        self.assertIn('nenhum agente foi publicado', self.html)
        self.assertIn('id="supervision-decision"', self.html)
        self.assertIn('operational_decision!=="NO_GO"', self.html)


if __name__ == "__main__":
    unittest.main()
