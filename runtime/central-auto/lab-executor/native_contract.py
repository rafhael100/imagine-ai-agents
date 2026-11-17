"""Closed P26 native graph and observed-output contract; no execution authority.

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
REF = re.compile(r"SYNTHETIC_[A-Z0-9_]{1,48}\Z")


class ContractDenied(Exception):
    pass


def need(condition: bool, label: str) -> None:
    if not condition:
        raise ContractDenied(label)


def canonical(value: object) -> bytes:
    return json.dumps(value, ensure_ascii=False, separators=(",", ":"), allow_nan=False).encode()


def template(path: Path) -> dict:
    need(path.is_file() and not path.is_symlink(), "P26_TEMPLATE_REGULAR")
    raw = path.read_bytes()
    need(hashlib.sha256(raw).hexdigest() == TEMPLATE_SHA256, "P26_TEMPLATE_HASH")
    data = json.loads(raw)
    need(data["active"] is False and data["pinData"] == {} and data["tags"] == [],
         "P26_TEMPLATE_INACTIVE")
    need([n["type"] for n in data["nodes"]] == ["n8n-nodes-base.manualTrigger",
         "n8n-nodes-base.set", "n8n-nodes-base.set"], "P26_NATIVE_NODES_ONLY")
    return data


def inputs(context: dict) -> dict:
    event = json.loads(context["canonical_event"])
    need(canonical(event).decode() == context["canonical_event"], "P26_CANONICAL_EVENT")
    need(event["contract"] == "SYNTHETIC_AIA_AUTOMATION_V1" and
         event["kind"] == "mock.run.completed", "P26_EVENT_OPERATION")
    for key in ("tenant_ref", "event_ref", "worker_ref"):
        need(isinstance(context[key], str) and REF.fullmatch(context[key]) is not None,
             "P26_REFERENCE")
    need(event["tenant_ref"] == context["tenant_ref"] and
         event["event_ref"] == context["event_ref"], "P26_TENANT_EVENT_BINDING")
    need(hashlib.sha256(context["canonical_event"].encode()).hexdigest() == context["payload_hash"],
         "P26_EVENT_HASH")
    need(type(context["attempt"]) is int and 1 <= context["attempt"] <= 3, "P26_ATTEMPT")
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
    return {"schema": "AIA_NATIVE_RUN_STATUS_V1", **inputs(context),
            "operation": "run.status.project", "run_status": "COMPLETED",
            "ai_api_calls": 0, "operational_authorization": False}


def materialize(source: dict, context: dict) -> tuple[dict, dict]:
    flow = copy.deepcopy(source)
    bound = inputs(context)
    for assignment in flow["nodes"][1]["parameters"]["assignments"]["assignments"]:
        assignment["value"] = bound[assignment["name"]]
    flow["id"] = "p26" + context["payload_hash"][:20]
    metadata = {**context, "template_sha256": TEMPLATE_SHA256, "workflow_id": flow["id"],
                "workflow_sha256": hashlib.sha256(canonical(flow)).hexdigest()}
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
    result = payload.get("data", {}).get("resultData", {})
    need(result.get("error") is None and result.get("lastNodeExecuted") == NAMES[2],
         "P26_EXECUTION_FAILED")
    runs = result.get("runData")
    need(isinstance(runs, dict) and set(runs) == set(NAMES), "P26_EXECUTED_GRAPH")
    for name in NAMES:
        records = runs[name]
        need(isinstance(records, list) and len(records) == 1 and
             records[0].get("executionStatus") == "success", "P26_NODE_FAILURE")
    for name, wanted in ((NAMES[1], inputs(context)), (NAMES[2], expected(context))):
        output = runs[name][0].get("data", {}).get("main")
        need(isinstance(output, list) and len(output) == 1 and isinstance(output[0], list)
             and len(output[0]) == 1 and same(output[0][0].get("json"), wanted),
             "P26_RESULT_BINDING")
    return expected(context)
