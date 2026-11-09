#!/usr/bin/env python3
"""Offline inventory linter for CRM/AIA OP-01. It NEVER authorizes a pilot.

A green result means that the *register* is structurally sound, not that an
identity, CRM API, data policy or reviewer approval has been verified.
"""
from __future__ import annotations

import argparse
import csv
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
GATE_FILE = ROOT / "docs/evidencias/aia-r01-gates-op01.csv"
CASE_FILE = ROOT / "docs/evidencias/op01-casos-homologacao.csv"
GATE_FIELDS = ["id", "tipo", "autoridade_sugerida", "estado", "evidencia_pendente"]
CASE_FIELDS = ["id", "tipo", "cenario", "resultado_esperado", "estado_execucao", "evidencia_pendente"]
REQUIRED_GATES = {**{f"R01-G{i:02}": "gate" for i in range(1, 12)},
                  **{name: "crm" for name in ("CRM-CORE", "CRM-SEG", "CRM-AUT")}}
REQUIRED_CASES = {**{f"OP01-N{i:02}": ("NEGATIVO", "NEGAR") for i in range(1, 12)},
                  **{f"OP01-P{i:02}": ("POSITIVO", "LEITURA_MINIMA") for i in range(1, 3)}}
SAFE_GATE_STATES = {"ABERTO", "EM_REVISAO"}


class InventoryError(Exception):
    def __init__(self, code: str) -> None:
        self.code = code
        super().__init__(code)


def rows_from_csv(path: Path, header: list[str]) -> list[dict[str, str]]:
    try:
        with path.open("r", encoding="utf-8", newline="") as stream:
            reader = csv.DictReader(stream, strict=True)
            if reader.fieldnames != header:
                raise InventoryError("HEADER_INVALID")
            rows = list(reader)
    except (OSError, UnicodeError, csv.Error):
        raise InventoryError("CSV_UNREADABLE") from None
    if len(rows) == 0 or len(rows) > 64:
        raise InventoryError("ROW_COUNT_INVALID")
    for row in rows:
        if set(row) != set(header):
            raise InventoryError("ROW_SCHEMA_INVALID")
        for field in header:
            value = row[field]
            if (not isinstance(value, str) or not value or value != value.strip()
                    or len(value) > 400 or value.startswith(("=", "+", "@", "-"))
                    or any(char in value for char in "\x00\r\n")):
                raise InventoryError("CELL_INVALID")
    return rows


def validate_gates(rows: list[dict[str, str]]) -> dict[str, int]:
    seen: set[str] = set()
    states: dict[str, int] = {state: 0 for state in SAFE_GATE_STATES}
    for row in rows:
        key = row["id"]
        if key in seen:
            raise InventoryError("GATE_DUPLICATE")
        seen.add(key)
        if key not in REQUIRED_GATES or row["tipo"] != REQUIRED_GATES[key]:
            raise InventoryError("GATE_SCOPE_INVALID")
        # Closed or approved gates require an independent governance process.
        # This static linter must NEVER infer approval from a CSV cell.
        if row["estado"] not in SAFE_GATE_STATES:
            raise InventoryError("UNVERIFIED_GATE_CLOSURE")
        states[row["estado"]] += 1
    if seen != set(REQUIRED_GATES):
        raise InventoryError("GATE_COVERAGE_INCOMPLETE")
    return states


def validate_cases(rows: list[dict[str, str]]) -> None:
    seen: set[str] = set()
    for row in rows:
        key = row["id"]
        if key in seen:
            raise InventoryError("CASE_DUPLICATE")
        seen.add(key)
        if key not in REQUIRED_CASES:
            raise InventoryError("CASE_SCOPE_INVALID")
        expected_type, expected_outcome = REQUIRED_CASES[key]
        if row["tipo"] != expected_type or row["resultado_esperado"] != expected_outcome:
            raise InventoryError("CASE_EXPECTATION_INVALID")
        # No real bilateral test has been executed. An edit to CSV is not proof.
        if row["estado_execucao"] != "NAO_EXECUTADO":
            raise InventoryError("UNVERIFIED_CASE_EXECUTION")
    if seen != set(REQUIRED_CASES):
        raise InventoryError("CASE_COVERAGE_INCOMPLETE")


def audit(ledger: Path, cases: Path) -> dict[str, object]:
    gate_rows = rows_from_csv(ledger, GATE_FIELDS)
    case_rows = rows_from_csv(cases, CASE_FIELDS)
    states = validate_gates(gate_rows)
    validate_cases(case_rows)
    return {
        "report": "AIA_R01_OP01_OFFLINE_INVENTORY_V1",
        "inventory_valid": True,
        "pilot_decision": "NO_GO",
        "authorizes_deploy": False,
        "authorizes_credentials": False,
        "external_calls": 0,
        "gate_count": len(gate_rows),
        "gates_open": states["ABERTO"],
        "gates_under_review": states["EM_REVISAO"],
        "case_count": len(case_rows),
        "cases_not_executed": len(case_rows),
        "note": "Structural register checks only; human bilateral acceptances remain necessary.",
    }


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--ledger", type=Path, default=GATE_FILE)
    parser.add_argument("--cases", type=Path, default=CASE_FILE)
    args = parser.parse_args(argv)
    try:
        outcome = audit(args.ledger, args.cases)
        code = 0  # inventory valid ONLY; never operational GO
    except InventoryError as error:
        outcome = {
            "report": "AIA_R01_OP01_OFFLINE_INVENTORY_V1",
            "inventory_valid": False,
            "pilot_decision": "NO_GO",
            "authorizes_deploy": False,
            "authorizes_credentials": False,
            "external_calls": 0,
            "error_code": error.code,
        }
        code = 1
    print(json.dumps(outcome, sort_keys=True, ensure_ascii=False))
    return code


if __name__ == "__main__":
    sys.exit(main())
