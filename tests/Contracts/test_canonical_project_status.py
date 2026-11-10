"""Prevent project-status drift without discarding historic evidence.

Document gate only: it never authorizes an operational CRM integration.
"""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
STATUS = ROOT / "STATUS-ATUAL.md"
ROADMAP = ROOT / "docs/ROADMAP.md"
README = ROOT / "README.md"
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
        self.assertIn("458 testes PHP", current)
        self.assertIn("82 arquivos PHP", current)
        self.assertIn("NO-GO", current)
        self.assertIn("CRM PR #57", current)
        self.assertIn("OPEN/DRAFT", current)

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
        self.assertIn("EXPECTED_CASES=458", current)
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
