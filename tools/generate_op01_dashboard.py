#!/usr/bin/env python3
"""Deterministic offline OP-01 dashboard compiler. This is NOT CRM authorization.

The CSV ledgers are the source of truth for the static review page. All
generated strings are HTML-escaped. No network, third-party libraries or
credentials. A successful --check NEVER means a real pilot is ready.
"""
from __future__ import annotations

import argparse
import csv
import html
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
TEMPLATE = ROOT / "examples/r01-op01-readiness-offline.template.html"
OUTPUT = ROOT / "examples/r01-op01-readiness-offline.html"
GATES = ROOT / "docs/evidencias/aia-r01-gates-op01.csv"
CASES = ROOT / "docs/evidencias/op01-casos-homologacao.csv"
GATE_MARKER = "{{GATE_ROWS}}"
CASE_MARKER = "{{CASE_ROWS}}"

import check_r01_readiness  # same tools directory


class RenderError(Exception):
    pass


def read_rows(path: Path) -> list[dict[str, str]]:
    with path.open(encoding="utf-8", newline="") as stream:
        return list(csv.DictReader(stream, strict=True))


def esc(value: str) -> str:
    return html.escape(value, quote=True)


def gate_row(row: dict[str, str]) -> str:
    return (
        f'<tr data-kind="gate" data-id="{esc(row["id"])}" '
        f'data-state="{esc(row["estado"])}"><td><strong>{esc(row["id"])}</strong>'
        f'<small>{esc(row["tipo"].upper())}</small></td>'
        f'<td><span class="tag">{esc(row["estado"])}</span></td>'
        f'<td>{esc(row["autoridade_sugerida"])}</td>'
        f'<td>{esc(row["evidencia_pendente"])}</td></tr>'
    )


def case_row(row: dict[str, str]) -> str:
    return (
        f'<tr data-kind="case" data-id="{esc(row["id"])}" '
        f'data-state="{esc(row["estado_execucao"])}"><td>'
        f'<strong>{esc(row["id"])}</strong><small>{esc(row["tipo"])}</small></td>'
        f'<td><span class="tag">{esc(row["estado_execucao"])}</span></td>'
        f'<td>{esc(row["cenario"])}</td>'
        f'<td>{esc(row["evidencia_pendente"])} '
        f'<span class="expected">{esc(row["resultado_esperado"])}</span></td></tr>'
    )


def render(template: Path = TEMPLATE, gates: Path = GATES, cases: Path = CASES) -> str:
    state = check_r01_readiness.audit(gates, cases)
    if (state.get("pilot_decision") != "NO_GO"
        or state.get("gate_count") != 14 or state.get("case_count") != 13):
        raise RenderError("INVENTORY_NOT_SAFE")
    page = template.read_text(encoding="utf-8")
    if page.count(GATE_MARKER) != 1 or page.count(CASE_MARKER) != 1:
        raise RenderError("TEMPLATE_MARKER_INVALID")
    return (
        page.replace(GATE_MARKER, "\n".join(map(gate_row, read_rows(gates))))
            .replace(CASE_MARKER, "\n".join(map(case_row, read_rows(cases))))
    )


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    group = parser.add_mutually_exclusive_group()
    group.add_argument("--check", action="store_true", help="verify saved HTML bytes (default)")
    group.add_argument("--write", action="store_true", help="rewrite ONLY the local static HTML")
    parser.add_argument("--template", type=Path, default=TEMPLATE)
    parser.add_argument("--gates", type=Path, default=GATES)
    parser.add_argument("--cases", type=Path, default=CASES)
    parser.add_argument("--output", type=Path, default=OUTPUT)
    args = parser.parse_args(argv)
    outcome: dict[str, object] = {
        "report": "AIA_R01_OP01_DASHBOARD_COMPILER_V1",
        "pilot_decision": "NO_GO",
        "authorizes_deploy": False,
        "authorizes_credentials": False,
        "external_calls": 0,
    }
    try:
        expected = render(args.template, args.gates, args.cases)
        if args.write:
            args.output.write_text(expected, encoding="utf-8", newline="")
        else:
            try:
                actual = args.output.read_bytes()
            except OSError as error:
                raise RenderError("OUTPUT_UNAVAILABLE") from error
            if actual != expected.encode("utf-8"):
                raise RenderError("DASHBOARD_OUT_OF_SYNC")
        outcome["generated_snapshot_valid"] = True
        outcome["records"] = 27
        code = 0
    except (OSError, UnicodeError, csv.Error, RenderError, check_r01_readiness.InventoryError) as error:
        outcome["generated_snapshot_valid"] = False
        outcome["error_code"] = (
            str(error) if isinstance(error, RenderError)
            else "INPUT_OR_INVENTORY_INVALID"
        )
        code = 1
    print(json.dumps(outcome, ensure_ascii=False, sort_keys=True))
    return code


if __name__ == "__main__":
    sys.exit(main())
