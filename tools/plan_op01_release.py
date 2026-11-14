#!/usr/bin/env python3
"""F212: deterministic next-step checklist for OP-01. Never an operational approval.

Reads only the existing, separately validated review packet, gates and cases.
Does not connect to CRM, schedule work, run real scenarios or grant GO.
"""
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "tools"))

import check_r01_readiness as readiness
import generate_op01_bilateral_packet as packet

# Order defines execution priority, not authorization or a calendar deadline.
# Every real gate and case is covered at least once. Overlap is intentional.
WORKSTREAMS = (
    ("W01", "IDENTIDADE_E_REPLAY", "CRM AUT + SEG",
     (), ("R01-G01", "R01-G03", "CRM-AUT"),
     ("OP01-N01", "OP01-N02", "OP01-N07", "OP01-N08")),
    ("W02", "ACL_RECURSO_E_EMPRESA", "CRM CORE + SEG",
     ("W01",), ("R01-G02", "CRM-CORE", "CRM-SEG"),
     ("OP01-N03", "OP01-N04", "OP01-N05", "OP01-P01", "OP01-P02")),
    ("W03", "CONTRATO_E_PRIVACIDADE", "Arquitetura + Privacidade + Jurídico",
     ("W01", "W02"), ("R01-G05", "R01-G08", "R01-G09"),
     ("OP01-N05", "OP01-N06", "OP01-N10", "OP01-P02")),
    ("W04", "INFRA_E_TESTES_REAIS", "Infra + SEG + QA",
     ("W01", "W02", "W03"), ("R01-G07", "R01-G10"),
     ("OP01-N09", "OP01-N10", "OP01-N11")),
    ("W05", "PILOTO_SEM_PRODUCAO", "Direção + Produto + Financeiro",
     ("W01", "W02", "W03", "W04"), ("R01-G04", "R01-G06", "R01-G11"),
     ("OP01-N08", "OP01-P01")),
)


class PlanError(Exception):
    pass


def compile_plan(
    ledger: Path = readiness.GATE_FILE,
    cases: Path = readiness.CASE_FILE,
    packet_file: Path = packet.OUTPUT,
) -> dict[str, object]:
    # Recompute first; do not trust a committed file, a comment or a CI badge.
    expected = packet.compile_packet(ledger=ledger, cases=cases)
    if packet_file.read_bytes() != packet.canonical_bytes(expected):
        raise PlanError("REVIEW_PACKET_OUT_OF_SYNC")
    if (expected["decisao_piloto"] != "NO_GO"
        or expected["integracao_autorizada"] is not False
        or expected["permite_credenciais"] is not False
        or expected["permite_deploy"] is not False
        or expected["api_crm_verificada"] is not False
        or expected["chamadas_externas"] != 0):
        raise PlanError("INVALID_RELEASE_AUTHORITY")

    summary = expected["contagens"]
    if (summary["gates"] != 14 or summary["gates_abertos"] != 14
        or summary["gates_em_revisao"] != 0
        or summary["cenarios"] != 13
        or summary["cenarios_nao_executados"] != 13
        or summary["decisoes_pendentes"] != 7):
        raise PlanError("REVIEW_STATE_CHANGED_REQUIRES_INDEPENDENT_REVIEW")

    gate_rows = readiness.rows_from_csv(ledger, readiness.GATE_FIELDS)
    case_rows = readiness.rows_from_csv(cases, readiness.CASE_FIELDS)
    gates = {row["id"]: row for row in gate_rows}
    scenarios = {row["id"]: row for row in case_rows}
    decision_rows = expected["decisoes"]
    if (len(decision_rows) != 7
        or any(d["aprovado"] is not False
               or d["estado"] != "ABERTO_SEM_ACEITE" for d in decision_rows)):
        raise PlanError("UNVERIFIED_DECISION")

    stages: list[dict[str, object]] = []
    prior: set[str] = set()
    seen_gates: set[str] = set()
    seen_cases: set[str] = set()
    seen_decisions: set[str] = set()
    for stage_id, title, owner, dependencies, stage_gates, stage_cases in WORKSTREAMS:
        if not set(dependencies).issubset(prior):
            raise PlanError("DEPENDENCY_CYCLE_OR_FORWARD_REFERENCE")
        if (not stage_gates or not stage_cases
            or set(stage_gates) - gates.keys()
            or set(stage_cases) - scenarios.keys()):
            raise PlanError("UNSUPPORTED_STAGE_REFERENCE")
        decisions = [
            entry["id"] for entry in decision_rows
            if set(stage_gates).intersection(entry["gates"])
            or set(stage_cases).intersection(
                row["id"] for row in entry["cenarios_homologacao"])
        ]
        if not decisions:
            raise PlanError("STAGE_WITHOUT_REVIEWER")
        seen_gates.update(stage_gates)
        seen_cases.update(stage_cases)
        seen_decisions.update(decisions)
        prior.add(stage_id)
        stages.append({
            "id": stage_id,
            "title": title,
            "reviewer_group": owner,
            "depends_on": list(dependencies),
            "gates": list(stage_gates),
            "test_cases": list(stage_cases),
            "review_slots": decisions,
            "required_evidence": [gates[g]["evidencia_pendente"] for g in stage_gates],
            "required_tests": [scenarios[c]["evidencia_pendente"] for c in stage_cases],
            "status": "BLOCKED_UNTIL_INDEPENDENT_REVIEW",
            "real_tests_executed": 0,
            "authorizes_access": False,
        })

    if (seen_gates != set(readiness.REQUIRED_GATES)
        or seen_cases != set(readiness.REQUIRED_CASES)
        or seen_decisions != {row["id"] for row in decision_rows}
        or len(stages) != 5):
        raise PlanError("WORKSTREAM_COVERAGE_INCOMPLETE")

    return {
        "contract": "AIA_OP01_RELEASE_CRITICAL_PATH_V1",
        "source": "CRM_PR_57_DRAFT_AND_OP01_REVIEW_PACKET",
        "phase": "B_REVIEW_NOT_APPROVED",
        "operational_decision": "NO_GO",
        "integration_enabled": False,
        "can_deploy": False,
        "can_use_credentials": False,
        "external_calls": 0,
        "real_tests_executed": 0,
        "open_gates": 14,
        "pending_tests": 13,
        "pending_reviews": 7,
        "workstreams": stages,
    }


def render_markdown(plan: dict[str, object]) -> str:
    lines = [
        "# OP-01 — plano de desbloqueio (não é autorização)",
        "",
        "**Decisão operacional: NO-GO.** 14 gates, 13 cenários reais e "
        "7 revisões independentes ainda pendentes. Todas as etapas abaixo "
        "são pendências, não aprovações.",
        "",
    ]
    for stage in plan["workstreams"]:
        lines.extend([
            f"## {stage['id']} — {stage['title']}",
            f"Revisores: {stage['reviewer_group']}. "
            f"Dependências: {', '.join(stage['depends_on']) or 'nenhuma'}.",
            f"Gates: {', '.join(stage['gates'])}. "
            f"Casos reais não executados: {', '.join(stage['test_cases'])}.",
            "Evidências exigidas: " + "; ".join(stage["required_evidence"]) + ".",
            "",
        ])
    lines += ["**Não implantar, não usar credenciais nem dados de clientes.**", ""]
    return "\n".join(lines)


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--format", choices=("json", "markdown"), default="json")
    parser.add_argument("--check", action="store_true", help="validate only, do not authorize")
    args = parser.parse_args(argv)
    try:
        result = compile_plan()
        if args.check:
            print("F212_CRITICAL_PATH_PASS operational_decision=NO_GO "
                  "open_gates=14 pending_tests=13 pending_reviews=7")
        elif args.format == "markdown":
            print(render_markdown(result), end="")
        else:
            print(json.dumps(result, sort_keys=True, ensure_ascii=False))
        return 0
    except (PlanError, readiness.InventoryError, packet.PacketError,
            OSError, UnicodeError, ValueError) as error:
        # Only a stable class of error, never a raw path, payload or secret.
        code = (error.args[0] if isinstance(error, PlanError)
                else "REVIEW_EVIDENCE_UNAVAILABLE_OR_INVALID")
        print(json.dumps({"contract": "AIA_OP01_RELEASE_CRITICAL_PATH_V1",
                          "operational_decision": "NO_GO", "can_deploy": False,
                          "can_use_credentials": False, "error_code": code}),
              file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
