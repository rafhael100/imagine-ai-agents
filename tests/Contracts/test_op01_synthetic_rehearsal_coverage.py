"""F163 checks consumer-side rehearsal labels, never bilateral CRM approval.

The clinical/operational acceptance CSV is deliberately *not* changed by
passing PHP mocks. It remains NAO_EXECUTADO until CRM tests are actually run.
"""
from __future__ import annotations

import csv
import io
import re
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
REGISTER = ROOT / "docs/evidencias/op01-casos-homologacao.csv"
PHP_REHEARSAL = ROOT / "tests/Unit/op01_conformance.php"
SCENARIO = re.compile(r"^OP01-(?:N(?:0[1-9]|1[01])|P0[12])$")
REHEARSAL = re.compile(r"'U63-(OP01-[NP]\d{2})\s")


def parse_register(text: str) -> dict[str, dict[str, str]]:
    reader = csv.DictReader(io.StringIO(text))
    required = {
        "id", "tipo", "cenario", "resultado_esperado",
        "estado_execucao", "evidencia_pendente",
    }
    if reader.fieldnames is None or set(reader.fieldnames) != required:
        raise ValueError("INVALID_CASE_REGISTER_HEADER")
    result: dict[str, dict[str, str]] = {}
    for row in reader:
        case_id = row["id"]
        if (case_id is None or not SCENARIO.fullmatch(case_id)
            or case_id in result
            or row["estado_execucao"] != "NAO_EXECUTADO"
            or row["tipo"] != ("POSITIVO" if "-P" in case_id else "NEGATIVO")
            or row["resultado_esperado"] != (
                "LEITURA_MINIMA" if "-P" in case_id else "NEGAR"
            )
            or not row["evidencia_pendente"]
        ):
            raise ValueError("INVALID_CASE_REGISTER_ROW")
        result[case_id] = row
    if len(result) != 13:
        raise ValueError("INCOMPLETE_CASE_REGISTER")
    return result


class Op01SyntheticRehearsalCoverage(unittest.TestCase):
    def test_every_pending_bilateral_case_has_exactly_one_offline_rehearsal(self):
        inventory = parse_register(REGISTER.read_text(encoding="utf-8"))
        source = PHP_REHEARSAL.read_text(encoding="utf-8")
        names = REHEARSAL.findall(source)
        self.assertEqual(len(names), 13)
        self.assertEqual(len(set(names)), 13)
        self.assertEqual(set(names), set(inventory))
        self.assertEqual(sum(x.startswith("OP01-N") for x in names), 11)
        self.assertEqual(sum(x.startswith("OP01-P") for x in names), 2)
        self.assertIn("return $tests;", source)
        self.assertIn("do NOT change", source)

    def test_passing_fake_cases_cannot_mark_actual_crm_tests_executed(self):
        inventory = parse_register(REGISTER.read_text(encoding="utf-8"))
        self.assertEqual({row["estado_execucao"] for row in inventory.values()},
                         {"NAO_EXECUTADO"})
        self.assertEqual({row["resultado_esperado"] for row in inventory.values()},
                         {"NEGAR", "LEITURA_MINIMA"})

    def test_forged_executed_or_missing_or_duplicate_case_is_rejected(self):
        original = REGISTER.read_text(encoding="utf-8")
        for tampered in [
            original.replace("NAO_EXECUTADO", "PASS", 1),
            original.replace("OP01-N01", "OP01-P01", 1),
            "\n".join(original.splitlines()[:-1]) + "\n",
        ]:
            with self.subTest(change=tampered[:70]):
                with self.assertRaises(ValueError):
                    parse_register(tampered)


if __name__ == "__main__":
    unittest.main()
