#!/usr/bin/env python3
"""F213: verify the static public-facing F212 plan against verified OP-01 CSV.

Does not start a server or authorize operations. No external I/O or network.
"""
from __future__ import annotations
import re
import sys
from html import escape
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "tools"))
import plan_op01_release as plan

FILE = ROOT / "examples/f213-op01-plano.html"


class ViewError(Exception):
    pass


def validate_html(document: str) -> dict[str, int]:
    if not isinstance(document, str) or len(document) > 50000:
        raise ViewError("F213_HTML_SIZE_OR_TYPE_DENIED")
    p = plan.compile_plan()
    mandatory = (
        "<!doctype html>", 'lang="pt-BR"',
        "Imagine AI Agents", "NO-GO", "NAO_EXECUTADO", "OPEN/DRAFT",
        "14", "13", "7", "0", "#f9941d", 'src="assets/logo-imagine.png"',
        "connect-src 'none'", "script-src 'none'", "form-action 'none'",
        "@media(max-width:700px)", "prefers-reduced-motion",
        'aria-label="Navegação da homologação"',
        'aria-label="Etapas para homologação"',
        'id="conteudo"', '<footer class="footer">',
    )
    if any(token not in document for token in mandatory):
        raise ViewError("F213_MISSING_SAFETY_OR_LAYOUT")
    forbidden = (
        "http://", "https://", "<script", "<iframe", "<form", "localStorage",
        "sessionStorage", "XMLHttpRequest", "WebSocket", "onclick=",
        "onload=", "onerror=", "data:application", "contenteditable",
        'operational_decision="GO"', "Produção: GO",
    )
    if any(token in document for token in forbidden):
        raise ViewError("F213_UNSAFE_DEPENDENCY_OR_ACTION")
    stages = re.findall(r'<article class="workstream" id="(W\d{2})" data-stage="(W\d{2})">', document)
    expected = [w["id"] for w in p["workstreams"]]
    if stages != [(name, name) for name in expected]:
        raise ViewError("F213_STAGE_COVERAGE_OR_ORDER_DENIED")
    gates = re.findall(r'data-gate="([^"]+)"', document)
    cases = re.findall(r'data-case="([^"]+)"', document)
    if (set(gates) != set(plan.readiness.REQUIRED_GATES)
        or len(gates) != 14
        or set(cases) != set(plan.readiness.REQUIRED_CASES)):
        raise ViewError("F213_GATE_OR_CASE_DRIFT")
    for i, wave in enumerate(p["workstreams"]):
        start = document.index(f'<article class="workstream" id="{wave["id"]}"')
        end = (document.find('<article class="workstream"', start + 1)
               if i < len(expected) - 1 else document.find('</div>\n </section>', start))
        if end < start:
            raise ViewError("F213_CARD_SECTION_DENIED")
        card = document[start:end]
        for marker in [wave["reviewer_group"], *wave["gates"], *wave["test_cases"],
                       *wave["required_evidence"], *wave["depends_on"]]:
            if escape(marker, quote=True) not in card:
                raise ViewError("F213_WAVE_DETAILS_OUT_OF_SYNC")
        if "Pendente · NO-GO" not in card:
            raise ViewError("F213_FAKE_APPROVAL")
        if card.count('class="evidence"') != len(wave["required_evidence"]):
            raise ViewError("F213_EVIDENCE_SCOPE_DENIED")
    return {"stages": 5, "gates": 14, "cases": 13, "pilot_go": 0}


def main() -> int:
    try:
        check = validate_html(FILE.read_text(encoding="utf-8"))
    except (OSError, UnicodeError, ValueError, ViewError,
            plan.PlanError, plan.readiness.InventoryError):
        print("F213_VIEW_DENIED operational_decision=NO_GO", file=sys.stderr)
        return 1
    print("F213_VIEW_PASS operational_decision=NO_GO "
          f"stages={check['stages']} gates={check['gates']} "
          f"cases={check['cases']} pilot_go=0")
    return 0


if __name__ == "__main__":
    sys.exit(main())
