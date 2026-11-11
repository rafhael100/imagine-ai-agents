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
        self.assertIn("629 testes PHP", current)
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
        self.assertIn("42902464064", (ROOT / "docs/AIA-R01-EVID-28-SEC43-CI-POSTMERGE.md").read_text(encoding="utf-8"))
        self.assertIn("42899017010", (ROOT / "docs/AIA-R01-EVID-27-SEC41-42-POSTMERGE.md").read_text(encoding="utf-8"))
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
        self.assertIn("EVID-28", current)
        self.assertIn("42899017010", historic)
        self.assertIn("629 testes PHP", readme)
        self.assertIn("NAO_EXECUTADO", current)
        self.assertIn("NO-GO", current)

    def test_sec43_offline_recheck_preserves_no_go_and_reviewer_gate(self):
        current = STATUS.read_text(encoding="utf-8")
        rules = AGENT_RULES.read_text(encoding="utf-8")
        self.assertIn("SEC-43 integrada à dev (PR #216)", current)
        self.assertIn("565 testes PHP", (ROOT / "docs/AIA-R01-EVID-28-SEC43-CI-POSTMERGE.md").read_text(encoding="utf-8"))
        self.assertIn("83 arquivos PHP", current)
        self.assertTrue((ROOT / "docs/AIA-FT01-SEC-43-REVALIDACAO-POS-LEITURA.md").is_file())
        self.assertIn("EVID-28", rules)
        self.assertIn("EVID-28", current)
        self.assertIn("42902464064", (ROOT / "docs/AIA-R01-EVID-28-SEC43-CI-POSTMERGE.md").read_text(encoding="utf-8"))
        self.assertIn("42902464064", (ROOT / "docs/AIA-R01-EVID-28-SEC43-CI-POSTMERGE.md").read_text(encoding="utf-8"))
        self.assertIn("NAO_EXECUTADO", current)
        self.assertIn("NO-GO", current)

    def test_sec44_read_tool_uses_shared_invariant_and_no_go(self):
        current = STATUS.read_text(encoding="utf-8")
        rules = AGENT_RULES.read_text(encoding="utf-8")
        self.assertIn("SEC-44 integrada à dev (PR #218", current)
        self.assertIn("575 testes PHP", (ROOT / "docs/AIA-R01-EVID-29-SEC44-CI-POSTMERGE.md").read_text(encoding="utf-8"))
        self.assertIn("AIA-FT01-SEC-44-FERRAMENTA-PROJECAO-COMUM.md", rules)
        self.assertIn("EVID-29", current)
        self.assertIn("42906719897", current)
        self.assertIn("42906719897", (ROOT / "docs/AIA-R01-EVID-29-SEC44-CI-POSTMERGE.md").read_text(encoding="utf-8"))
        self.assertIn("NAO_EXECUTADO", current)
        self.assertIn("NO-GO", current)
        self.assertIn("CRM PR #57", current)

    def test_sec45_final_draft_recheck_is_offline_no_go(self):
        current = STATUS.read_text(encoding="utf-8")
        rules = AGENT_RULES.read_text(encoding="utf-8")
        self.assertIn("SEC-45 integrada à dev (PR #220)", current)
        self.assertIn("585 testes PHP", (ROOT / "docs/AIA-R01-EVID-30-SEC45-CI-POSTMERGE.md").read_text(encoding="utf-8"))
        self.assertIn("SEC-45", rules)
        self.assertIn("AIA-FT01-SEC-45-REVALIDACAO-POS-SUGESTAO.md", rules)
        self.assertIn("EVID-30", current)
        self.assertIn("42913437800", current)
        self.assertIn("42913437800", (ROOT / "docs/AIA-R01-EVID-30-SEC45-CI-POSTMERGE.md").read_text(encoding="utf-8"))
        self.assertIn("NAO_EXECUTADO", current)
        self.assertIn("NO-GO", current)
        self.assertIn("CRM PR #57", current)

    def test_sec46_read_receipt_denies_aba_and_preserves_crm_no_go(self):
        current = STATUS.read_text(encoding="utf-8")
        readme = README.read_text(encoding="utf-8")
        rules = AGENT_RULES.read_text(encoding="utf-8")
        self.assertIn("SEC-46 integrada à dev (PR #222)", current)
        historic = (ROOT / "docs/AIA-R01-EVID-31-SEC46-CI-POSTMERGE.md").read_text(encoding="utf-8")
        self.assertIn("597 testes PHP", historic)
        self.assertIn("629 testes PHP", current)
        self.assertIn("629 testes PHP", readme)
        self.assertIn("AIA-FT01-SEC-46-RECIBO-REVISAO-OWNER-ABA.md", rules)
        self.assertIn("EVID-31", current)
        self.assertIn("42918687251", current)
        self.assertIn("42918687251", (ROOT / "docs/AIA-R01-EVID-31-SEC46-CI-POSTMERGE.md").read_text(encoding="utf-8"))
        self.assertIn("NAO_EXECUTADO", current)
        self.assertIn("NO-GO", current)
        self.assertIn("CRM PR #57", current)

    def test_sec47_recheck_clock_is_monotonic_and_offline_only(self):
        current = STATUS.read_text(encoding="utf-8")
        readme = README.read_text(encoding="utf-8")
        rules = AGENT_RULES.read_text(encoding="utf-8")
        self.assertIn("SEC-47 integrada à dev (PR #224)", current)
        self.assertIn("605 testes PHP", (ROOT / "docs/AIA-R01-EVID-32-SEC47-CI-POSTMERGE.md").read_text(encoding="utf-8"))
        self.assertIn("629 testes PHP", current)
        self.assertIn("629 testes PHP", readme)
        self.assertIn("AIA-FT01-SEC-47-RECHECK-TEMPO-MONOTONICO.md", rules)
        self.assertIn("EVID-32", current)
        self.assertIn("42923158871", current)
        self.assertIn("42923158871", (ROOT / "docs/AIA-R01-EVID-32-SEC47-CI-POSTMERGE.md").read_text(encoding="utf-8"))
        self.assertIn("NAO_EXECUTADO", current)
        self.assertIn("NO-GO", current)
        self.assertIn("CRM PR #57", current)

    def test_sec48_reentrant_reader_denied_before_audit_success(self):
        current = STATUS.read_text(encoding="utf-8")
        readme = README.read_text(encoding="utf-8")
        rules = AGENT_RULES.read_text(encoding="utf-8")
        self.assertIn("SEC-48 integrada à dev (PR #226)", current)
        self.assertIn("629 testes PHP", current)
        self.assertIn("629 testes PHP", readme)
        self.assertIn("AIA-FT01-SEC-48-REENTRADA-LEITURA-OP01.md", rules)
        self.assertIn("EVID-33", current)
        self.assertIn("42924834951", current)
        self.assertIn("42924834951", (ROOT / "docs/AIA-R01-EVID-33-SEC48-CI-POSTMERGE.md").read_text(encoding="utf-8"))
        self.assertIn("NAO_EXECUTADO", current)
        self.assertIn("NO-GO", current)
        self.assertIn("CRM PR #57", current)

    def test_sec49_shared_trust_epoch_blocks_recovered_outage_offline(self):
        current = STATUS.read_text(encoding="utf-8")
        readme = README.read_text(encoding="utf-8")
        rules = AGENT_RULES.read_text(encoding="utf-8")
        self.assertIn("SEC-49 integrada à dev (PR #228)", current)
        self.assertIn("629 testes PHP", current)
        self.assertIn("629 testes PHP", readme)
        self.assertIn("AIA-FT01-SEC-49-REVISAO-TRUST-COMPARTILHADO.md", rules)
        self.assertIn("EVID-34", current)
        self.assertIn("42933553917", (ROOT / "docs/AIA-R01-EVID-34-SEC49-CI-POSTMERGE.md").read_text(encoding="utf-8"))
        self.assertIn("42933553917", (ROOT / "docs/AIA-R01-EVID-34-SEC49-CI-POSTMERGE.md").read_text(encoding="utf-8"))
        self.assertIn("NAO_EXECUTADO", current)
        self.assertIn("NO-GO", current)
        self.assertIn("CRM PR #57", current)

    def test_sec50_revoked_tool_grant_is_rechecked_before_reply(self):
        current = STATUS.read_text(encoding="utf-8")
        readme = README.read_text(encoding="utf-8")
        rules = AGENT_RULES.read_text(encoding="utf-8")
        self.assertIn("SEC-50 integrada à dev (PR #230)", current)
        self.assertIn("42938703131", current)
        self.assertIn("EVID-35", current)
        self.assertIn("42938703131", (ROOT / "docs/AIA-R01-EVID-35-SEC50-CI-POSTMERGE.md").read_text(encoding="utf-8"))
        self.assertIn("629 testes PHP", current)
        self.assertIn("629 testes PHP", readme)
        self.assertIn("AIA-FT01-SEC-50-CAPABILITY-RECHECK-POS-FERRAMENTA.md", rules)
        self.assertIn("NO-GO", current)
        self.assertIn("NAO_EXECUTADO", current)
        self.assertIn("CRM PR #57", current)

    def test_sec51_callback_reentry_is_postmerge_verified_but_offline(self):
        current = STATUS.read_text(encoding="utf-8")
        readme = README.read_text(encoding="utf-8")
        evidence = (ROOT / "docs/AIA-R01-EVID-36-SEC51-CI-POSTMERGE.md").read_text(encoding="utf-8")
        self.assertIn("SEC-51 integrada à dev (PR #233)", current)
        self.assertIn("633 testes PHP", current)
        self.assertIn("42940604581", current)
        self.assertIn("42940604581", evidence)
        self.assertIn("872597580bf93261cbb858a8c9eb6f2fe342e9d7", evidence)
        self.assertIn("633 testes PHP", readme)
        # Historic SEC-50 evidence must remain visible after advancing to SEC-51.
        self.assertIn("629 testes PHP", current)
        self.assertIn("42938703131", current)
        self.assertIn("NO-GO", current)
        self.assertIn("NAO_EXECUTADO", evidence)

    def test_sec52_cross_dispatcher_callback_guard_is_postmerge_offline(self):
        current = STATUS.read_text(encoding="utf-8")
        readme = README.read_text(encoding="utf-8")
        evidence = (ROOT / "docs/AIA-R01-EVID-37-SEC52-CI-POSTMERGE.md").read_text(encoding="utf-8")
        self.assertIn("SEC-52 integrada à dev (PR #235)", current)
        self.assertIn("640 testes PHP", current)
        self.assertIn("42943113650", current)
        self.assertIn("42943113650", evidence)
        self.assertIn("c74eabb82c0e69b9a1bbd5ed320a8ac5f247e7e0", evidence)
        self.assertIn("640 testes PHP", readme)
        self.assertIn("633 testes PHP", current)
        self.assertIn("629 testes PHP", current)
        self.assertIn("NO-GO", current)
        self.assertIn("NAO_EXECUTADO", evidence)

    def test_sec53_run_lifecycle_guard_is_postmerge_verified_offline(self):
        current = STATUS.read_text(encoding="utf-8")
        readme = README.read_text(encoding="utf-8")
        rules = AGENT_RULES.read_text(encoding="utf-8")
        evidence = (ROOT / "docs/AIA-R01-EVID-38-SEC53-CI-POSTMERGE.md").read_text(encoding="utf-8")
        self.assertIn("SEC-53 integrada à dev (PR #237)", current)
        self.assertIn("648 testes PHP", current)
        self.assertIn("42945854088", current)
        self.assertIn("42945854088", evidence)
        self.assertIn("ad7fc7b718235e0b5e6befaaaf7976adca15bc2a", evidence)
        self.assertIn("648 testes PHP", readme)
        self.assertIn("AIA-FT01-SEC-53-RUN-LIFECYCLE-READONLY-CALLBACK.md", rules)
        self.assertIn("640 testes PHP", current)
        self.assertIn("633 testes PHP", current)
        self.assertIn("629 testes PHP", current)
        self.assertIn("EXPECTED_CASES=810", current)
        self.assertIn("NO-GO", current)
        self.assertIn("NAO_EXECUTADO", evidence)

    def test_evid39_four_op01_deliveries_are_postmerge_offline(self):
        current = STATUS.read_text(encoding="utf-8")
        readme = README.read_text(encoding="utf-8")
        rules = AGENT_RULES.read_text(encoding="utf-8")
        evidence = (ROOT / "docs/AIA-R01-EVID-39-OP01-QUEUE-PILOT-POSTMERGE.md").read_text(encoding="utf-8")
        for pr, ci in ((239, 42949459592), (240, 42949959755),
                       (241, 42950862393), (242, 42951498940)):
            self.assertIn(f"#{pr}", evidence)
            self.assertIn(str(ci), evidence)
            self.assertIn(str(ci), current)
        self.assertIn("699 testes PHP", current)
        self.assertIn("699 testes PHP", readme)
        self.assertIn("699 PHP / lint 87", rules)
        self.assertIn("f453ebbb99a45416398e0cebed0b404bc00f2dc7", evidence)
        self.assertIn("NO-GO", current)
        self.assertIn("OPEN/DRAFT", evidence)
        self.assertIn("NAO_EXECUTADO", evidence)
        for old in ("648 testes PHP", "640 testes PHP", "633 testes PHP",
                    "629 testes PHP"):
            self.assertIn(old, current)

    def test_evid41_five_deliveries_are_postmerge_verified_offline(self):
        status = STATUS.read_text(encoding="utf-8")
        readme = README.read_text(encoding="utf-8")
        rules = AGENT_RULES.read_text(encoding="utf-8")
        evidence = (ROOT / "docs/AIA-R01-EVID-41-F184-F186-SEC58-59-CI-POSTMERGE.md").read_text(encoding="utf-8")
        for pr, ci in ((255, 43009103944), (256, 43010448505),
                       (257, 43011660965), (258, 43012704139),
                       (259, 43013609438)):
            self.assertIn(f"#{pr}", evidence)
            self.assertIn(str(ci), evidence)
        self.assertIn("08b17e5139ab1d638410100771a424d83ad76481", evidence)
        self.assertIn("43013609438", status)
        self.assertIn("800 testes PHP", status)
        self.assertIn("lint 99 + Python/DOM", status)
        self.assertIn("800 PHP / lint 99", evidence)
        self.assertIn("EVID-41", status)
        self.assertIn("EVID-41", readme)
        self.assertIn("EVID-41", rules)
        self.assertIn("OPEN/DRAFT", evidence)
        self.assertIn("NAO_EXECUTADO", evidence)
        self.assertIn("NO-GO", evidence)
        self.assertIn("699 testes PHP", status)
        self.assertIn("776 testes PHP", status)

    def test_evid40_four_deliveries_and_f183_ui_are_postmerge_offline(self):
        current = STATUS.read_text(encoding="utf-8")
        readme = README.read_text(encoding="utf-8")
        rules = AGENT_RULES.read_text(encoding="utf-8")
        evidence = (ROOT / "docs/AIA-R01-EVID-40-SEC57-REV02-F183-POSTMERGE.md").read_text(encoding="utf-8")
        for pr, ci in ((250, 42980115062), (251, 42980489349),
                       (252, 42981149687), (253, 42981975813)):
            self.assertIn(f"#{pr}", evidence)
            self.assertIn(str(ci), current)
            self.assertIn(str(ci), evidence)
        self.assertIn("776 testes PHP", current)
        self.assertIn("776 PHP / lint 97", evidence)
        self.assertIn("776 PHP / lint 97", evidence)
        self.assertIn("F108_SMOKE_PASS", evidence)
        self.assertIn("EVID-40", readme)
        self.assertIn("EVID-40", rules)
        self.assertIn("0a40ab2f3ba2fe24f0c449484fbd2280396f82ca", evidence)
        self.assertIn("NO-GO", current)
        self.assertIn("OPEN/DRAFT", evidence)
        self.assertIn("NAO_EXECUTADO", evidence)
        for old in ("699 testes PHP", "648 testes PHP", "640 testes PHP",
                    "633 testes PHP", "629 testes PHP"):
            self.assertIn(old, current)

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
        self.assertIn("EXPECTED_CASES=810", current)
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
