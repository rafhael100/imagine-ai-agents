"""F187: closed, manual JSON draft transfer; browser-only and atomic."""
from __future__ import annotations
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
PAGE = ROOT / "examples/f108-laboratorio-navegavel.html"
SMOKE = ROOT / "tests/Browser/f108_smoke.cjs"

class F187DraftTransferTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.html = PAGE.read_text(encoding="utf-8")
        cls.smoke = SMOKE.read_text(encoding="utf-8")

    def test_accessible_user_mediated_json_controls(self):
        for field in (
            "agent-draft-transfer-json", "agent-draft-export",
            "agent-draft-import", "agent-draft-clear-json",
            "agent-draft-transfer-status",
        ):
            self.assertEqual(self.html.count(f'id="{field}"'), 1)
        self.assertIn('for="agent-draft-transfer-json"', self.html)
        self.assertIn('id="agent-draft-transfer-json" rows="5" maxlength="4096"', self.html)
        self.assertIn('role="status" aria-live="polite" id="agent-draft-transfer-status"', self.html)
        for evt in ('exportSessionDrafts', 'importSessionDrafts', 'clearTransferText'):
            self.assertIn(evt, self.html)

    def test_export_schema_is_bounded_and_contains_only_draft_fields(self):
        for token in (
            'const DRAFT_TRANSFER_VERSION="SYNTHETIC_AGENT_DRAFTS_V1";',
            "const MAX_TRANSFER_CHARS=4096;",
            'drafts:sessionAgentDrafts.map(x=>({name:x.name,template:x.templateKey}))',
            'const text=JSON.stringify(packet);',
            'if(text.length>MAX_TRANSFER_CHARS)',
            'get("agent-draft-transfer-json").value=text;',
        ):
            self.assertIn(token, self.html)

    def test_import_validates_closed_schema_and_template_before_mutation(self):
        for token in (
            'Object.keys(packet).sort().join("|")!=="drafts|version"',
            'packet.version!==DRAFT_TRANSFER_VERSION',
            '!Array.isArray(packet.drafts)',
            'packet.drafts.length>MAX_SESSION_DRAFTS',
            'Object.keys(item).sort().join("|")!=="name|template"',
            'Object.prototype.hasOwnProperty.call(agentDraftTemplates,item.template)',
            'name!==item.name',
            'usedNames.has(lookup)',
            'const staged=[];const usedNames=new Set();',
            'sessionAgentDrafts.splice(0,sessionAgentDrafts.length,...staged);',
        ):
            self.assertIn(token, self.html)

    def test_unicode_name_normalization_and_bounded_input(self):
        for token in (
            'if(typeof value!=="string"||value.length>80)return null;',
            'value.normalize("NFC").trim()',
            r'/^[\p{L}\p{N} ._-]+$/u.test(name)',
            'if(name.length<3||name.length>48',
            'raw.length>MAX_TRANSFER_CHARS',
        ):
            self.assertIn(token, self.html)

    def test_browser_smoke_covers_roundtrip_and_all_or_nothing_rejections(self):
        for token in (
            'byId("agent-draft-export").click()',
            'const transferPackage=',
            'byId("agent-draft-import").click()',
            'byId("agent-draft-clear-json").click()',
            'rejectedImport(\'not json\'', "unknown version",
            '"prototype model"',
            '"unexpected role"', '"casefold duplicates"',
            '"atomic rejection of unsafe second item"',
            '"sixth item denied"', '"oversized input"',
            '"explicit empty packet clears drafts"',
            '"transfer never executes scenarios"',
        ):
            self.assertIn(token, self.smoke)

    def test_no_automatic_upload_download_storage_or_execution(self):
        self.assertIn("connect-src 'none'", self.html)
        for forbidden in (
            'fetch(', 'XMLHttpRequest(', 'WebSocket(', 'localStorage(',
            'sessionStorage(', 'indexedDB', 'document.cookie',
            '<script src=', '<iframe', 'http://', 'https://',
            'window.open(', 'URL.createObjectURL(', 'navigator.clipboard',
            'eval(', 'new Function(', 'innerHTML',
        ):
            with self.subTest(forbidden=forbidden):
                self.assertNotIn(forbidden, self.html)
        self.assertIn("nenhuma transferência ocorreu.", self.html)
        self.assertIn("não publicados.", self.html)

    def test_failed_import_does_not_clear_active_collection(self):
        code = self.html
        validation = code.index('function importSessionDrafts(){')
        mutation = code.index('sessionAgentDrafts.splice(0,sessionAgentDrafts.length,...staged);')
        self.assertGreater(mutation, validation)
        for token in (
            'writeTransferStatus("Item inválido. A lista atual não foi alterada.");',
            'writeTransferStatus("Nome duplicado. A lista atual não foi alterada.");',
            'writeTransferStatus("Formato ou capacidade inválida. A lista atual não foi alterada.");',
        ):
            self.assertIn(token, code[validation:mutation])
        self.assertIn('clearAgentDraft();', code[mutation:])


if __name__ == "__main__":
    unittest.main()
