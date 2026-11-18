"""Closed native graphs and observed-output contracts; no execution authority.

The caller is a trusted, disposable CI process. These checks do not turn CLI
output or a synthetic signer into an independently attested n8n identity.
"""
from __future__ import annotations

import copy
import hashlib
import json
import re
from pathlib import Path

TEMPLATE_SHA256 = "85311c9621967bd3b31a0935a358bf9b61d51cd2266954293ef8c124096e41c8"
NAMES = ("Disparo manual", "Referencias tecnicas", "Projetar estado da execucao")
WORKFLOW_REF = "SYNTHETIC_P26_RUN_STATUS_V1"
LEAD_TEMPLATE_SHA256 = "8d3ee7e2abe2f852212ee10904fa20032265578358992e98abc99835fd5833a4"
LEAD_NAMES = ("Disparo manual", "Referencias do lead", "Preparar lead recebido")
LEAD_WORKFLOW_REF = "SYNTHETIC_CRM_LEAD_INGEST_V1"
LEAD_KEYS = ("contract", "event_ref", "tenant_ref", "company_id", "lead_id",
             "source_payload_hash", "kind", "occurred_at")
REF = re.compile(r"SYNTHETIC_[A-Z0-9_]{1,48}\Z")
COMPANY = re.compile(r"[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}\Z")
UUID = re.compile(r"[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\Z")
DIGEST = re.compile(r"[a-f0-9]{64}\Z")
WORKFLOWS = Path(__file__).resolve().parents[3] / "infra/n8n-private/workflows"


class ContractDenied(Exception):
    pass


def need(condition: bool, label: str) -> None:
    if not condition:
        raise ContractDenied(label)


def canonical(value: object) -> bytes:
    return json.dumps(value, ensure_ascii=False, separators=(",", ":"), allow_nan=False).encode()


def _event(context: dict) -> dict:
    raw = context.get("canonical_event")
    need(isinstance(raw, str) and len(raw.encode()) <= 512, "P26_CANONICAL_EVENT_SIZE")
    try:
        event = json.loads(raw)
        need(isinstance(event, dict) and canonical(event).decode() == raw, "P26_CANONICAL_EVENT")
    except (ValueError, TypeError) as error:
        raise ContractDenied("P26_CANONICAL_EVENT") from error
    return event


def _profile(event: dict) -> tuple[str, tuple[str, str, str], str, str, Path]:
    if (event.get("contract"), event.get("kind")) == ("SYNTHETIC_AIA_AUTOMATION_V1", "mock.run.completed"):
        return TEMPLATE_SHA256, NAMES, WORKFLOW_REF, "p26", WORKFLOWS / "native-run-status-noai.workflow.json"
    need((event.get("contract"), event.get("kind")) ==
         ("SYNTHETIC_CRM_LEAD_INGEST_V1", "crm.lead.ingest"), "P26_EVENT_OPERATION")
    return (LEAD_TEMPLATE_SHA256, LEAD_NAMES, LEAD_WORKFLOW_REF, "lead",
            WORKFLOWS / "native-crm-lead-noai.workflow.json")


def template(path: Path) -> dict:
    need(path.is_file() and not path.is_symlink(), "P26_TEMPLATE_REGULAR")
    raw = path.read_bytes()
    need(hashlib.sha256(raw).hexdigest() in (TEMPLATE_SHA256, LEAD_TEMPLATE_SHA256), "P26_TEMPLATE_HASH")
    data = json.loads(raw)
    need(data["active"] is False and data["pinData"] == {} and data["tags"] == [],
         "P26_TEMPLATE_INACTIVE")
    need([n["type"] for n in data["nodes"]] == ["n8n-nodes-base.manualTrigger",
         "n8n-nodes-base.set", "n8n-nodes-base.set"], "P26_NATIVE_NODES_ONLY")
    return data


def inputs(context: dict) -> dict:
    event = _event(context)
    digest, _, workflow_ref, prefix, _ = _profile(event)
    for key in ("tenant_ref", "event_ref", "worker_ref"):
        need(isinstance(context[key], str) and REF.fullmatch(context[key]) is not None,
             "P26_REFERENCE")
    need(event.get("tenant_ref") == context["tenant_ref"] and
         event.get("event_ref") == context["event_ref"], "P26_TENANT_EVENT_BINDING")
    need(hashlib.sha256(context["canonical_event"].encode()).hexdigest() == context["payload_hash"],
         "P26_EVENT_HASH")
    need(type(context["attempt"]) is int and 1 <= context["attempt"] <= 3, "P26_ATTEMPT")
    for name, value in (("template_sha256", digest), ("workflow_id", prefix + context["payload_hash"][:20])):
        need(name not in context or context[name] == value, "P26_TEMPLATE_CONTEXT")
    if event["contract"] == "SYNTHETIC_CRM_LEAD_INGEST_V1":
        need(tuple(event) == LEAD_KEYS, "LEAD_TASK_CLOSED_FIELDS")
        for name, pattern in (("company_id", COMPANY), ("lead_id", UUID), ("source_payload_hash", DIGEST)):
            need(isinstance(event[name], str) and pattern.fullmatch(event[name]) is not None,
                 "LEAD_TASK_IDENTITY_OR_SOURCE")
        need(context.get("company_id") == event["company_id"], "LEAD_COMPANY_BINDING")
        need(type(event["occurred_at"]) is int and 0 <= event["occurred_at"] <= 4102444800,
             "LEAD_TASK_TIMESTAMP")
        return {
            "company_id": event["company_id"], "tenant_ref": context["tenant_ref"],
            "event_ref": context["event_ref"], "lead_id": event["lead_id"],
            "source_payload_hash": event["source_payload_hash"], "payload_hash": context["payload_hash"],
            "worker_ref": context["worker_ref"], "attempt": context["attempt"],
            "template_sha256": digest, "workflow_ref": workflow_ref,
        }
    for key in ("run_ref", "reference_ref"):
        need(isinstance(event[key], str) and REF.fullmatch(event[key]) is not None,
             "P26_TECHNICAL_REFERENCE")
    return {
        "tenant_ref": context["tenant_ref"], "event_ref": context["event_ref"],
        "run_ref": event["run_ref"], "reference_ref": event["reference_ref"],
        "payload_hash": context["payload_hash"], "worker_ref": context["worker_ref"],
        "attempt": context["attempt"], "template_sha256": TEMPLATE_SHA256,
        "workflow_ref": WORKFLOW_REF,
    }


def expected(context: dict) -> dict:
    if _event(context).get("contract") == "SYNTHETIC_CRM_LEAD_INGEST_V1":
        return {"schema": "AIA_NATIVE_LEAD_INGEST_V1", **inputs(context),
                "operation": "lead.ingest", "lead_status": "PREPARED", "crm_mutated": False,
                "ai_api_calls": 0, "operational_authorization": False}
    return {"schema": "AIA_NATIVE_RUN_STATUS_V1", **inputs(context),
            "operation": "run.status.project", "run_status": "COMPLETED",
            "ai_api_calls": 0, "operational_authorization": False}


def materialize(source: dict, context: dict) -> tuple[dict, dict]:
    bound = inputs(context)
    digest, _, _, prefix, path = _profile(_event(context))
    # A valid template for another operation cannot be substituted. Recheck the
    # exact pinned source here even if a caller mutates it after template().
    need(canonical(source) == canonical(template(path)), "P26_TEMPLATE_OPERATION_BINDING")
    flow = copy.deepcopy(source)
    for assignment in flow["nodes"][1]["parameters"]["assignments"]["assignments"]:
        assignment["value"] = bound[assignment["name"]]
    flow["id"] = prefix + context["payload_hash"][:20]
    graph_hash = hashlib.sha256(canonical(flow)).hexdigest()
    need("workflow_sha256" not in context or context["workflow_sha256"] == graph_hash,
         "P26_MATERIALIZED_GRAPH_HASH")
    metadata = {**context, "template_sha256": digest, "workflow_id": flow["id"],
                "workflow_sha256": graph_hash}
    return flow, metadata


def verify_export(flow: dict, exported: object) -> dict:
    need(isinstance(exported, list), "P26_EXPORT_ARRAY")
    found = [w for w in exported if isinstance(w, dict) and w.get("id") == flow["id"]]
    need(len(found) == 1, "P26_EXPORT_ID_UNIQUE")
    actual = found[0]
    # n8n owns creation/version metadata. Every execution-relevant field is exact.
    for field in ("name", "nodes", "connections", "settings", "active", "pinData"):
        need(actual.get(field) == flow[field], "P26_EXPORTED_GRAPH_CHANGED")
    need(not actual.get("staticData"), "P26_EXPORTED_STATIC_DATA")
    return actual


def same(actual: object, wanted: dict) -> bool:
    return (isinstance(actual, dict) and set(actual) == set(wanted) and
            all(type(actual[k]) is type(v) and actual[k] == v for k, v in wanted.items()))


def verify_result(payload: dict, context: dict) -> dict:
    need(len(canonical(payload)) <= 262144, "P26_RESULT_SIZE")
    _, names, _, _, path = _profile(_event(context))
    materialize(template(path), context)
    result = payload.get("data", {}).get("resultData", {})
    need(result.get("error") is None and result.get("lastNodeExecuted") == names[2],
         "P26_EXECUTION_FAILED")
    runs = result.get("runData")
    need(isinstance(runs, dict) and set(runs) == set(names), "P26_EXECUTED_GRAPH")
    for name in names:
        records = runs[name]
        need(isinstance(records, list) and len(records) == 1 and
             records[0].get("executionStatus") == "success", "P26_NODE_FAILURE")
    for name, wanted in ((names[1], inputs(context)), (names[2], expected(context))):
        output = runs[name][0].get("data", {}).get("main")
        need(isinstance(output, list) and len(output) == 1 and isinstance(output[0], list)
             and len(output[0]) == 1 and same(output[0][0].get("json"), wanted),
             "P26_RESULT_BINDING")
    return expected(context)
