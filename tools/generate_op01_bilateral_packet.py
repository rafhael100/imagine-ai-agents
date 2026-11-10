#!/usr/bin/env python3
"""Compile a deterministic, strictly NO-GO OP-01 bilateral review packet.

Only local pinned documents and CSV inventories are read. This is a checklist
for CRM/Agents owners, not a GO decision, real IAM, API client or authorization.
"""
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "tools"))

import check_op01_contract_parity as parity
import check_r01_readiness as readiness

OUTPUT = ROOT / "docs/evidencias/op01-pacote-aceite-bilateral.json"

# Each review slot maps an explicit unresolved CRM PR #57 decision to the
# technical gates and non-executed homologation cases that require a human
# decision. Groupings may overlap but coverage must be exact.
REVIEW_SLOTS = (
    ("OP01-R01", "CRM AUT + SEG", ("R01-G01", "CRM-AUT"),
     ("OP01-N01", "OP01-N02", "OP01-N03")),
    ("OP01-R02", "CRM CORE + SEG", ("R01-G02", "CRM-CORE", "CRM-SEG"),
     ("OP01-N03", "OP01-N04", "OP01-N05", "OP01-P01", "OP01-P02")),
    ("OP01-R03", "CRM AUT + Infra", ("R01-G03", "CRM-AUT"),
     ("OP01-N07", "OP01-N08")),
    ("OP01-R04", "Privacidade + Jurídico", ("R01-G05", "R01-G09"),
     ("OP01-N05", "OP01-N10")),
    ("OP01-R05", "Arquitetura + CRM CORE", ("R01-G08", "CRM-CORE"),
     ("OP01-N06", "OP01-P02")),
    ("OP01-R06", "Segurança + Operações + QA", ("R01-G07", "R01-G10", "CRM-SEG"),
     ("OP01-N09", "OP01-N10", "OP01-N11")),
    ("OP01-R07", "Direção + Produto + Financeiro", ("R01-G04", "R01-G06", "R01-G11"),
     ("OP01-N08", "OP01-P01")),
)


class PacketError(Exception):
    pass


def compile_packet(
    ledger: Path = readiness.GATE_FILE,
    cases: Path = readiness.CASE_FILE,
    snapshot: Path = parity.SNAPSHOT,
    manifest_path: Path = parity.MANIFEST,
    schema: Path = parity.SCHEMA,
) -> dict[str, object]:
    # All sources must be independently valid BEFORE assembling a review.
    summary = readiness.audit(ledger, cases)
    parity.check(snapshot, manifest_path, schema)
    manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    gates = {r["id"]: r for r in readiness.rows_from_csv(ledger, readiness.GATE_FIELDS)}
    scenarios = {r["id"]: r for r in readiness.rows_from_csv(cases, readiness.CASE_FIELDS)}
    draft = snapshot.read_text(encoding="utf-8")

    all_gates: set[str] = set()
    all_scenarios: set[str] = set()
    decisions: list[dict[str, object]] = []
    for decision_id, owner, gate_ids, test_ids in REVIEW_SLOTS:
        if decision_id not in draft or not gate_ids or not test_ids:
            raise PacketError("BILATERAL_DECISION_SCOPE_INVALID")
        if set(gate_ids) - gates.keys() or set(test_ids) - scenarios.keys():
            raise PacketError("BILATERAL_REFERENCE_INVALID")
        all_gates.update(gate_ids)
        all_scenarios.update(test_ids)
        pending_gates = [gates[g]["evidencia_pendente"] for g in gate_ids]
        tests = [
            {
                "id": case_id,
                "cenario": scenarios[case_id]["cenario"],
                "esperado": scenarios[case_id]["resultado_esperado"],
                "estado": scenarios[case_id]["estado_execucao"],
            }
            for case_id in test_ids
        ]
        state = ("EM_REVISAO_SEM_ACEITE" if any(
            gates[g]["estado"] == "EM_REVISAO" for g in gate_ids
        ) else "ABERTO_SEM_ACEITE")
        decisions.append({
            "id": decision_id,
            "revisores_sugeridos": owner,
            "gates": list(gate_ids),
            "evidencias_pendentes": pending_gates,
            "cenarios_homologacao": tests,
            "estado": state,
            "aprovado": False,
        })
    if (all_gates != set(readiness.REQUIRED_GATES)
        or all_scenarios != set(readiness.REQUIRED_CASES)
        or len(decisions) != 7 or len({d["id"] for d in decisions}) != 7):
        raise PacketError("BILATERAL_COVERAGE_INCOMPLETE")

    return {
        "report": "AIA_OP01_BILATERAL_REVIEW_PACKET_V1",
        "fonte_crm": {
            "repositorio": manifest["source_repo"],
            "pr": manifest["source_pr"],
            "head_revisado": manifest["source_pr_head"],
            "blob_documental": manifest["source_blob_sha"],
            "estado": manifest["proposal_status"],
        },
        "contagens": {
            "gates": summary["gate_count"],
            "gates_abertos": summary["gates_open"],
            "gates_em_revisao": summary["gates_under_review"],
            "cenarios": summary["case_count"],
            "cenarios_nao_executados": summary["cases_not_executed"],
            "decisoes_pendentes": len(decisions),
        },
        "decisoes": decisions,
        "decisao_piloto": "NO_GO",
        "integracao_autorizada": False,
        "api_crm_verificada": False,
        "permite_credenciais": False,
        "permite_deploy": False,
        "chamadas_externas": 0,
        "observacao": "Inventario e proposta em rascunho; somente parecer humano independente e homologacao podem mudar os gates.",
    }


def canonical_bytes(report: dict[str, object]) -> bytes:
    return (json.dumps(report, ensure_ascii=False, sort_keys=True, indent=2)
            + "\n").encode("utf-8")


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    mode = parser.add_mutually_exclusive_group()
    mode.add_argument("--check", action="store_true", help="compare committed JSON to source inputs")
    mode.add_argument("--write", action="store_true", help="regenerate ONLY the local JSON packet")
    parser.add_argument("--ledger", type=Path, default=readiness.GATE_FILE)
    parser.add_argument("--cases", type=Path, default=readiness.CASE_FILE)
    parser.add_argument("--snapshot", type=Path, default=parity.SNAPSHOT)
    parser.add_argument("--manifest", type=Path, default=parity.MANIFEST)
    parser.add_argument("--schema", type=Path, default=parity.SCHEMA)
    parser.add_argument("--output", type=Path, default=OUTPUT)
    args = parser.parse_args(argv)
    result: dict[str, object] = {
        "report": "AIA_OP01_BILATERAL_REVIEW_COMPILER_V1",
        "decisao_piloto": "NO_GO", "permite_credenciais": False,
        "permite_deploy": False, "chamadas_externas": 0,
    }
    try:
        packet = compile_packet(
            args.ledger, args.cases, args.snapshot, args.manifest, args.schema)
        expected = canonical_bytes(packet)
        if args.write:
            args.output.write_bytes(expected)
        elif args.output.read_bytes() != expected:
            raise PacketError("REVIEW_PACKET_OUT_OF_SYNC")
        result.update({"pacote_valido": True, "decisoes_pendentes": 7,
                       "gates": 14, "cenarios": 13})
        code = 0
    except (readiness.InventoryError, parity.ParityError, PacketError,
            OSError, UnicodeError, ValueError) as error:
        code = 1
        # Only stable class names, never path or raw input from exceptions.
        if isinstance(error, PacketError):
            reason = str(error)
        elif isinstance(error, parity.ParityError):
            reason = "CRM_PROPOSAL_INVALID"
        elif isinstance(error, readiness.InventoryError):
            reason = "GATE_OR_CASE_REGISTER_INVALID"
        else:
            reason = "REVIEW_SOURCE_UNAVAILABLE"
        result.update({"pacote_valido": False, "codigo": reason})
    print(json.dumps(result, ensure_ascii=False, sort_keys=True))
    return code


if __name__ == "__main__":
    sys.exit(main())
