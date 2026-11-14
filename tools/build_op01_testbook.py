#!/usr/bin/env python3
"""F215 — closed, executable preflight for 13 OP-01 test specifications.

This compiles instructions, not an HTTP runner. Even when a negative
scenario has expected=DENY, its actual operational result is NOT_EXECUTED.
"""
from __future__ import annotations
import argparse
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "tools"))
import check_r01_readiness as readiness
import plan_op01_release as plan

# Lab-free test design; inputs are descriptive, never real requests or credentials.
DESIGNS = {
    "OP01-N01": ("Injetar ausência de autenticação ou uma chave de serviço revogada no ambiente isolado", "autenticacao-e-revogacao"),
    "OP01-N02": ("Apresentar identidade de emissor ou audience incompatível com o CRM de homologação", "issuer-audience-e-ambiente"),
    "OP01-N03": ("Suspender empresa ou integrante depois do vínculo inicial e antes da leitura", "estado-atual-de-empresa-e-principal"),
    "OP01-N04": ("Comparar recurso inexistente e recurso de outra empresa, exigindo resposta indistinguível", "acl-tenant-recurso-e-rls"),
    "OP01-N05": ("Requisitar campo pessoal/desconhecido e verificar projeção mínima exata", "campos-minimos-e-contrato"),
    "OP01-N06": ("Apresentar operação de escrita e versão desconhecida; confirmar rejeição uniforme", "allowlist-op01-e-versao"),
    "OP01-N07": ("Reutilizar prova entre instâncias e depois de reiniciar o autorizador", "nonce-duravel-entre-instancias"),
    "OP01-N08": ("Revogar autorização enquanto tarefa aguarda fila e revalidar antes da leitura", "revogacao-recheck-pos-espera"),
    "OP01-N09": ("Induzir indisponibilidade e timeout do CRM e do autorizador, observando fail closed", "timeout-e-indisponibilidade"),
    "OP01-N10": ("Verificar que logs/telemetria nunca incluem segredo, payload ou identificadores pessoais", "telemetria-minimizada"),
    "OP01-N11": ("Induzir falha de telemetria antes de parse e confirmar negativa segura sem vazamento", "falha-telemetria-fail-closed"),
    "OP01-P01": ("Com identidade e ACL verificadas, solicitar apenas os campos mínimos de lead próprio fictício", "acl-projecao-minima-positiva"),
    "OP01-P02": ("Repetir leitura fictícia autorizada com a ordem de campos permitidos invertida", "projecao-exata-ordenada"),
}


class TestbookError(Exception):
    """Only stable public codes may be returned to CLI callers."""


def compile_testbook(ledger: Path = readiness.GATE_FILE,
                     cases: Path = readiness.CASE_FILE,
                     packet_file: Path | None = None) -> dict[str, object]:
    critical = plan.compile_plan(
        ledger=ledger,
        cases=cases,
        packet_file=packet_file if packet_file is not None else plan.packet.OUTPUT)
    rows = readiness.rows_from_csv(cases, readiness.CASE_FIELDS)
    expected_ids = tuple(readiness.REQUIRED_CASES)
    if (len(rows) != 13 or len({row["id"] for row in rows}) != 13
        or set(DESIGNS) != set(expected_ids)
        or {row["id"] for row in rows} != set(expected_ids)):
        raise TestbookError("OP01_CASE_COVERAGE_INVALID")

    stages = critical["workstreams"]
    specs: list[dict[str, object]] = []
    for case_id in sorted(expected_ids):
        row = next(row for row in rows if row["id"] == case_id)
        stage = next((w for w in stages if case_id in w["test_cases"]), None)
        if stage is None:
            raise TestbookError("CASE_HAS_NO_REVIEW_STAGE")
        expected = "DENIED_OR_NOT_FOUND" if row["tipo"] == "NEGATIVO" else "READ_MINIMAL_ONLY"
        if (row["estado_execucao"] != "NAO_EXECUTADO"
            or row["resultado_esperado"] != (
                "NEGAR" if row["tipo"] == "NEGATIVO" else "LEITURA_MINIMA")
            or row["tipo"] not in ("NEGATIVO", "POSITIVO")):
            raise TestbookError("UNVERIFIED_TEST_STATUS")
        spec = {
            "id": case_id,
            "label": row["cenario"],
            "primary_workstream": stage["id"],
            "suggested_reviewers": stage["reviewer_group"],
            "preconditions": [
                "STAGING_ISOLATED_WITH_SYNTHETIC_FIXTURES",
                "SERVICE_IDENTITY_AND_CRM_ACL_INDEPENDENTLY_REVIEWED",
                "PRIVACY_AND_AUDIT_EVIDENCE_APPROVED",
            ],
            "design": DESIGNS[case_id][0],
            "evidence_kind": DESIGNS[case_id][1],
            "evidence_pending": row["evidencia_pendente"],
            "expected_synthetic_behavior": expected,
            "real_execution_status": "NAO_EXECUTADO",
            "real_observation": None,
            "approved": False,
            "permits_customer_data": False,
            "permits_deployment": False,
        }
        specs.append(spec)

    if (len(specs) != 13
        or sum(s["expected_synthetic_behavior"] == "DENIED_OR_NOT_FOUND"
               for s in specs) != 11
        or sum(s["expected_synthetic_behavior"] == "READ_MINIMAL_ONLY"
               for s in specs) != 2):
        raise TestbookError("UNEXPECTED_OP01_EXPECTATIONS")
    return {
        "contract": "AIA_OP01_REAL_HOMOLOGATION_TESTBOOK_V1",
        "source": "VERIFIED_OP01_BILATERAL_PACKET",
        "scope": "INSTRUCTIONS_ONLY_NO_EXECUTION",
        "operational_decision": "NO_GO",
        "real_scenarios_executed": 0,
        "real_scenarios_pending": 13,
        "open_gates": 14,
        "independent_reviews_pending": 7,
        "external_calls": 0,
        "permits_credentials": False,
        "permits_customer_data": False,
        "permits_deploy": False,
        "scenarios": specs,
    }


def markdown(report: dict[str, object]) -> str:
    lines = [
        "# OP-01 — caderno para homologação bilateral",
        "",
        "**NO-GO.** Este roteiro não executa testes nem autoriza o serviço. "
        "13 cenários reais NAO_EXECUTADO; 14 gates e 7 decisões pendentes.",
        "",
    ]
    for spec in report["scenarios"]:
        lines.extend([
            f"## {spec['id']} — {spec['label']}",
            f"Etapa: {spec['primary_workstream']}. "
            f"Revisores: {spec['suggested_reviewers']}.",
            f"Ensaio planejado: {spec['design']}.",
            f"Resultado esperado (não observado): "
            f"{spec['expected_synthetic_behavior']}.",
            f"Evidência pendente: {spec['evidence_pending']}. "
            f"Estado real: NAO_EXECUTADO.",
            "",
        ])
    lines.append("**Executar somente após os aceites independentes e em staging isolado.**")
    lines.append("")
    return "\n".join(lines)


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--check", action="store_true")
    parser.add_argument("--format", choices=("json", "markdown"), default="json")
    args = parser.parse_args(argv)
    try:
        book = compile_testbook()
        if args.check:
            print("F215_TESTBOOK_PASS operational_decision=NO_GO "
                  "negative=11 positive=2 real_executed=0")
        elif args.format == "markdown":
            print(markdown(book), end="")
        else:
            print(json.dumps(book, ensure_ascii=False, sort_keys=True))
        return 0
    except (TestbookError, plan.PlanError, readiness.InventoryError,
            plan.packet.PacketError, OSError, UnicodeError, ValueError):
        print("F215_TESTBOOK_DENIED operational_decision=NO_GO", file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
