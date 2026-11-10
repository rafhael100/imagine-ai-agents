"""Prevent project-status drift without discarding historic evidence.

Document gate only: it never authorizes an operational CRM integration.
"""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
STATUS = ROOT / "STATUS-ATUAL.md"
ROADMAP = ROOT / "docs/ROADMAP.md"
README = ROOT / "README.md"
AGENT_RULES = ROOT / "AGENTS.md"
ARCHIVED_README = ROOT / "docs/historico/README-ANTES-CONSOLIDACAO-2026-11-10.md"
ARCHIVE = ROOT / "docs/historico/STATUS-ATUAL-ANTES-CONSOLIDACAO-2026-11-10.md"
ARCHIVED_ROADMAP = ROOT / "docs/historico/ROADMAP-ANTES-CONSOLIDACAO-2026-11-10.md"


class CanonicalProjectStatusTests(unittest.TestCase):
    def test_current_status_is_brief_and_points_to_full_history(self):
        current = STATUS.read_text(encoding="utf-8")
        self.assertTrue(current.startswith("# Imagine AI Agents — estado atual\n"))
        self.assertLess(len(current), 16000)
        self.assertIn("docs/historico/STATUS-ATUAL-ANTES-CONSOLIDACAO-2026-11-10.md", current)
        self.assertIn("R01-G01..G11", current)
        self.assertIn("CORE/SEG/AUT", current)
        self.assertIn("565 testes PHP", current)
        self.assertIn("SEC-42 integrada à dev (PR #214)", current)
        self.assertIn("83 arquivos PHP", current)
        self.assertIn("NO-GO", current)
        self.assertIn("CRM PR #57", current)
        self.assertIn("OPEN/DRAFT", current)

    def test_recent_sec39_sec40_are_integrated_with_post_merge_evidence(self):
        current = STATUS.read_text(encoding="utf-8")
        readme = README.read_text(encoding="utf-8")
        rules = AGENT_RULES.read_text(encoding="utf-8")
        self.assertIn("SEC-40 integrada à dev (PR #209)", current)
        self.assertIn("SEC-39 integrada à dev", current)
        self.assertNotIn("SEC-40 em revisão", current)
        self.assertIn("42889307984", current)
        self.assertIn("42899017010", readme)
        self.assertIn("## Baseline integrado à dev — SEC-40", rules)
        self.assertIn("## Baseline integrado à dev — SEC-39", rules)
        self.assertIn("Gate verificado: 533 testes PHP", rules)
        self.assertIn("NO-GO", current)
        self.assertIn("CRM PR #57", current)

    def test_synthetic_rehearsal_does_not_claim_crm_acceptance(self):
        status = STATUS.read_text(encoding="utf-8")
        roadmap = ROADMAP.read_text(encoding="utf-8")
        self.assertIn("EVID-25", status)
        self.assertIn("EVID-26", status)
        self.assertIn("42894696737", (ROOT / "docs/AIA-R01-EVID-26-F163-CI-POSTMERGE.md").read_text(encoding="utf-8"))
        self.assertIn("F163 / EVID-25 integrado à dev (PR #211)", status)
        self.assertIn("13 cenários", status)
        self.assertIn("NAO_EXECUTADO", status)
        self.assertIn("CRM PR #57", roadmap)
        self.assertIn("NO-GO", status)

    def test_sec41_shared_projection_invariant_is_reviewed_not_operational(self):
        current = STATUS.read_text(encoding="utf-8")
        rules = AGENT_RULES.read_text(encoding="utf-8")
        readme = README.read_text(encoding="utf-8")
        self.assertIn("SEC-41 integrada à dev", current)
        self.assertIn("83 arquivos PHP", current)
        historic = (ROOT / "docs/AIA-R01-EVID-27-SEC41-42-POSTMERGE.md").read_text(encoding="utf-8")
        self.assertIn("555 testes PHP", historic)
        self.assertIn("AIA-FT01-SEC-41-PROJECAO-OP01-UNIFICADA.md", rules)
        self.assertIn("EVID-27", current)
        self.assertIn("42899017010", current)
        self.assertIn("565 testes PHP", readme)
        self.assertIn("NAO_EXECUTADO", current)
        self.assertIn("NO-GO", current)

    def test_sec43_offline_recheck_preserves_no_go_and_reviewer_gate(self):
        current = STATUS.read_text(encoding="utf-8")
        rules = AGENT_RULES.read_text(encoding="utf-8")
        self.assertIn("SEC-43 integrada à dev (PR #216)", current)
        self.assertIn("565 testes PHP", current)
        self.assertIn("83 arquivos PHP", current)
        self.assertIn("AIA-FT01-SEC-43-REVALIDACAO-POS-LEITURA.md", rules)
        self.assertIn("EVID-28", current)
        self.assertIn("42902464064", current)
        self.assertIn("42902464064", README.read_text(encoding="utf-8"))
        self.assertIn("NAO_EXECUTADO", current)
        self.assertIn("NO-GO", current)

    def test_history_preserved_without_overwriting_evidence(self):
        previous = ARCHIVE.read_text(encoding="utf-8")
        prior_roadmap = ARCHIVED_ROADMAP.read_text(encoding="utf-8")
        self.assertGreater(len(previous), 100000)
        self.assertGreater(len(prior_roadmap), 20000)
        self.assertIn("PR #177", previous)
        self.assertIn("PR #178", previous)
        self.assertIn("389 testes", previous)
        self.assertIn("SEC-14", prior_roadmap)
        self.assertIn("NO-GO", previous)

    def test_current_readme_is_short_and_old_readme_fully_archived(self):
        current = README.read_text(encoding="utf-8")
        history = ARCHIVED_README.read_text(encoding="utf-8")
        self.assertTrue(current.startswith("# Imagine AI Agents\n"))
        self.assertLess(len(current), 8000)
        self.assertGreater(len(history), 80000)
        self.assertIn("docs/historico/README-ANTES-CONSOLIDACAO-2026-11-10.md", current)
        self.assertIn("EXPECTED_CASES=565", current)
        self.assertIn("NO-GO", current)
        self.assertIn("CRM PR #57", current)
        self.assertIn("EXPECTED_CASES=397", history)

    def test_roadmap_follows_real_gate_sequence_and_stays_readable(self):
        plan = ROADMAP.read_text(encoding="utf-8")
        self.assertTrue(plan.startswith("# Imagine AI Agents — roadmap operacional\n"))
        self.assertLess(len(plan), 12000)
        self.assertIn("historico/ROADMAP-ANTES-CONSOLIDACAO-2026-11-10.md", plan)
        self.assertIn("CRM PR #57", plan)
        self.assertLess(plan.index("## Fase B"), plan.index("## Fase C"))
        self.assertLess(plan.index("## Fase C"), plan.index("## Fase D"))
        self.assertIn("ABERTO / NO-GO", plan)
        self.assertIn("Não alterar", plan)


if __name__ == "__main__":
    unittest.main()
