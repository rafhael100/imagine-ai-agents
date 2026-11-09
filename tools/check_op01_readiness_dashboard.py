#!/usr/bin/env python3
"""Offline dashboard integrity verification. A PASS never authorizes CRM use."""
from __future__ import annotations
import csv
import html
from html.parser import HTMLParser
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
GATE_CSV = ROOT / "docs/evidencias/aia-r01-gates-op01.csv"
CASE_CSV = ROOT / "docs/evidencias/op01-casos-homologacao.csv"
DASHBOARD = ROOT / "examples/r01-op01-readiness-offline.html"

class DashboardError(Exception):
    pass

class Reader(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.rows = []
        self.current = None
        self.in_cell = False
    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == "tr" and "data-id" in attrs:
            self.current = {"kind": attrs.get("data-kind"), "id": attrs["data-id"],
                            "state": attrs.get("data-state"), "cells": []}
        if tag == "td" and self.current is not None:
            self.in_cell = True
            self.current["cells"].append("")
    def handle_data(self, data):
        if self.current is not None and self.in_cell:
            self.current["cells"][-1] += data
    def handle_endtag(self, tag):
        if tag == "td":
            self.in_cell = False
        if tag == "tr" and self.current is not None:
            self.rows.append(self.current)
            self.current = None

def load_csv(path: Path):
    with path.open(encoding="utf-8", newline="") as stream:
        return list(csv.DictReader(stream))

def check(dashboard=DASHBOARD, gate_csv=GATE_CSV, case_csv=CASE_CSV):
    sys.path.insert(0, str(ROOT / "tools"))
    import check_r01_readiness
    state = check_r01_readiness.audit(gate_csv, case_csv)
    if state.get("pilot_decision") != "NO_GO":
        raise DashboardError("UNSAFE_INVENTORY_STATUS")
    gates, cases = load_csv(gate_csv), load_csv(case_csv)
    source = dashboard.read_text(encoding="utf-8")
    required = ("NO-GO", "Sem dados de clientes", "CRM PR #57",
                "connect-src 'none'", "default-src 'none'")
    if any(marker not in source for marker in required):
        raise DashboardError("DASHBOARD_GUARDS_MISSING")
    if re.search(r"<(?:script|link)[^>]+(?:src|href)\s*=", source, re.I):
        raise DashboardError("EXTERNAL_ASSET_DENIED")
    if re.search(r"\b(?:fetch|XMLHttpRequest|WebSocket|sendBeacon)\s*\(", source):
        raise DashboardError("NETWORK_CODE_DENIED")
    parsed = Reader()
    parsed.feed(source)
    records = {r["id"]: r for r in parsed.rows}
    expected_ids = [g["id"] for g in gates] + [c["id"] for c in cases]
    if len(parsed.rows) != 27 or len(records) != 27 or set(records) != set(expected_ids):
        raise DashboardError("ROW_COVERAGE_DRIFT")
    for row in gates:
        ui = records[row["id"]]
        text = " ".join(ui["cells"])
        if (ui["kind"] != "gate" or ui["state"] != row["estado"]
            or not all(value in text for value in
                       (row["id"], row["estado"], row["autoridade_sugerida"],
                        row["evidencia_pendente"]))):
            raise DashboardError("GATE_RENDER_DRIFT")
    for row in cases:
        ui = records[row["id"]]
        text = " ".join(ui["cells"])
        if (ui["kind"] != "case" or ui["state"] != row["estado_execucao"]
            or not all(value in text for value in
                       (row["id"], row["estado_execucao"], row["cenario"],
                        row["resultado_esperado"], row["evidencia_pendente"]))):
            raise DashboardError("CASE_RENDER_DRIFT")
    return {"dashboard_valid": True, "gates": len(gates), "cases": len(cases),
            "pilot_decision": "NO_GO", "external_calls": 0}

if __name__ == "__main__":
    try:
        import json
        print(json.dumps(check(), sort_keys=True))
    except (DashboardError, OSError, ValueError) as error:
        print("DASHBOARD_FAIL " + type(error).__name__ + ": " + str(error), file=sys.stderr)
        sys.exit(1)
