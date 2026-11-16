#!/usr/bin/env python3
"""CENTRAL-AUTO-P9: only n8n native Set nodes, offline, fail closed.

The script checks the source JSON, a real n8n exported workflow (roundtrip),
and the actual --rawOutput result. None of this authorizes deployment.
"""
from __future__ import annotations
import json
import re
import sys
from pathlib import Path

TRIGGER="Disparo manual"
INPUT="Entradas ficticias"
PROJECT="Projetar leads e clientes"
EXPECTED={
    "schema":"AUTO_IA_NATIVE_NO_AI_V1",
    "tenant_ref":"SYNTHETIC_TENANT_DEMO",
    "visits":120,
    "projected_leads":12,
    "projected_customers":2,
    "decision":"NO_AI",
    "ai_api_calls":0,
    "ai_tokens":0,
    "commercial_credits_debited":0,
    "operational_authorization":False,
    "mode":"SYNTHETIC_MANUAL_ONLY",
}
ORDER=[TRIGGER,INPUT,PROJECT]
FORBIDDEN=("http","webhook","langchain","code","openai","agent","shell","executecommand","ssh")

class Denied(Exception): pass

def parse_json(path:Path):
    if not path.is_file() or path.is_symlink():
        raise Denied("FILE_UNTRUSTED")
    raw=path.read_bytes()
    if not 10<=len(raw)<=100000:
        raise Denied("FILE_SIZE")
    def closed_pairs(pairs):
        d={}
        for k,v in pairs:
            if k in d:raise Denied("DUPLICATE_KEY")
            d[k]=v
        return d
    return json.loads(raw,object_pairs_hook=closed_pairs)

def source_ok(data):
    if not isinstance(data,dict) or data.get("active") is not False:
        raise Denied("SOURCE_ACTIVE")
    if data.get("pinData")!={} or data.get("tags")!=[]:
        raise Denied("PINNED_DATA_OR_TAGS")
    names=data.get("nodes",[])
    if len(names)!=3 or [n.get("name") for n in names]!=ORDER:
        raise Denied("NODE_NAMES")
    if [n.get("type") for n in names]!=[
      "n8n-nodes-base.manualTrigger","n8n-nodes-base.set","n8n-nodes-base.set"
    ]:
        raise Denied("NODE_ALLOWLIST")
    if any(x.get("credentials") or x.get("webhookId") or x.get("disabled") for x in names):
        raise Denied("NODE_SECURITY")
    if names[0]["parameters"]!={}:
        raise Denied("TRIGGER_PARAMETERS")
    conn={
      TRIGGER:{"main":[[{"node":INPUT,"type":"main","index":0}]]},
      INPUT:{"main":[[{"node":PROJECT,"type":"main","index":0}]]},
    }
    if data.get("connections")!=conn:
        raise Denied("GRAPH_CHANGED")
    for node in names[1:]:
        if node.get("typeVersion")!=3.4:
            raise Denied("NODE_VERSION")
        p=node.get("parameters",{})
        if set(p)!={"mode","duplicateItem","assignments","includeOtherFields","options"}:
            raise Denied("ASSIGNMENT_OPTIONS_CHANGED")
        if p["mode"]!="manual" or p["duplicateItem"] is not False or (
            p["includeOtherFields"] is not False or p["options"]!={}
        ):
            raise Denied("OPEN_ASSIGNMENT_MODE")
        assignments=p["assignments"].get("assignments")
        if not isinstance(assignments,list) or not assignments:
            raise Denied("NO_ASSIGNMENTS")
        for a in assignments:
            if set(a)!={"id","name","value","type"}:
                raise Denied("ASSIGNMENT_SCHEMA")
            if a["type"] not in ("string","number","boolean"):
                raise Denied("ASSIGNMENT_TYPE")
            if not isinstance(a["name"],str) or not re.fullmatch(r"[a-z_]+",a["name"]):
                raise Denied("FIELD_NAME")
            value=a["value"]
            if isinstance(value,str):
                if any(f in value.lower() for f in FORBIDDEN) or "http" in value.lower():
                    raise Denied("FORBIDDEN_FUNCTION")
                if len(value)>200:
                    raise Denied("UNREVIEWED_EXPRESSION")
    seed={a["name"]:a["value"] for a in names[1]["parameters"]["assignments"]["assignments"]}
    if seed!={"weekly_visits":120,"lead_rate_percent":10,"client_rate_percent":20,
              "tenant_ref":"SYNTHETIC_TENANT_DEMO"}:
        raise Denied("SEED_CHANGED")
    fields={a["name"]:a["value"] for a in names[2]["parameters"]["assignments"]["assignments"]}
    reviewed={
      "schema":"AUTO_IA_NATIVE_NO_AI_V1",
      "tenant_ref":"={{ $json.tenant_ref }}",
      "visits":"={{ $json.weekly_visits }}",
      "projected_leads":"={{ Math.round($json.weekly_visits * $json.lead_rate_percent / 100) }}",
      "projected_customers":"={{ Math.round(Math.round($json.weekly_visits * $json.lead_rate_percent / 100) * $json.client_rate_percent / 100) }}",
      "decision":"NO_AI",
      "ai_api_calls":0,
      "ai_tokens":0,
      "commercial_credits_debited":0,
      "operational_authorization":False,
      "mode":"SYNTHETIC_MANUAL_ONLY",
    }
    if fields!=reviewed:
        raise Denied("NATIVE_MATH_EXPRESSION_CHANGED")
    return data

def verify_export(source,exported):
    if isinstance(exported,list):
        found=[x for x in exported if isinstance(x,dict) and x.get("name")==source.get("name")]
        if len(found)!=1:raise Denied("NATIVE_EXPORT_NOT_UNIQUE")
        exported=found[0]
    if exported.get("active") is not False:
        raise Denied("EXPORTED_ACTIVE")
    if exported.get("connections")!=source.get("connections"):
        raise Denied("EXPORT_CONNECTIONS_CHANGED")
    if exported.get("nodes")!=source.get("nodes"):
        raise Denied("NATIVE_NODE_OR_CODE_CHANGED")
    return "NATIVE_EXPORT_BYTE_CONTRACT_PASS"

def parse_output(raw):
    s=raw.decode("utf-8")
    decoder=json.JSONDecoder()
    hits=[]
    for match in re.finditer(r"(?m)^\{",s):
        try:
            value,end=decoder.raw_decode(s[match.start():])
        except ValueError:
            continue
        if isinstance(value,dict) and isinstance(value.get("data"),dict) and (
           isinstance(value["data"].get("resultData"),dict)
        ):
            if s[match.start()+end:].strip():
                raise Denied("TRAILING_OUTPUT")
            hits.append(value)
    if len(hits)!=1:raise Denied("EXPECTED_ONE_EXECUTION")
    return hits[0]

def verify_result(payload):
    result=payload["data"]["resultData"]
    if result.get("error") is not None or result.get("lastNodeExecuted")!=PROJECT:
        raise Denied("N8N_EXECUTION_FAILED")
    runs=result.get("runData")
    if not isinstance(runs,dict) or set(runs)!=set(ORDER):
        raise Denied("UNEXPECTED_N8N_NODES")
    for name in ORDER:
        events=runs[name]
        if not isinstance(events,list) or len(events)!=1 or events[0].get("executionStatus")!="success":
            raise Denied("N8N_NODE_NOT_SUCCESS")
    output=runs[PROJECT][0].get("data",{}).get("main",[])
    if len(output)!=1 or len(output[0])!=1:
        raise Denied("N8N_OUTPUT_COUNT")
    actual=output[0][0].get("json")
    if not isinstance(actual,dict) or set(actual)!=set(EXPECTED):
        raise Denied("N8N_SCHEMA_DRIFT")
    for k,v in EXPECTED.items():
        if type(actual[k]) is not type(v) or actual[k]!=v:
            raise Denied("N8N_WRONG_"+k)
    return {
       "schema":"CENTRAL_AUTO_P9_N8N_NATIVE_RESULT_V1",
       "native_nodes_only":3,
       "visits":120,"leads":12,"customers":2,
       "ai_api_calls":0,"ai_tokens":0,"credits_debited":0,
       "real_provider_observability":False,
       "operational_authorization":False,"release":"NO_GO",
    }

def main():
    if len(sys.argv)<3:raise Denied("USAGE static|export|result SOURCE [PATH]")
    mode=sys.argv[1]
    source=source_ok(parse_json(Path(sys.argv[2])))
    if mode=="static" and len(sys.argv)==3:
        print("CENTRAL_AUTO_P9_STATIC_PASS no_code=1 no_credential=1 no_provider=1")
        return
    if len(sys.argv)!=4:raise Denied("MISSING_OUTPUT")
    other=Path(sys.argv[3])
    if mode=="export":
        print(verify_export(source,parse_json(other)))
    elif mode=="result":
        print(json.dumps(verify_result(parse_output(other.read_bytes())),sort_keys=True))
    else:
        raise Denied("UNKNOWN_MODE")

if __name__=="__main__":
    try:main()
    except (Denied,ValueError,KeyError,TypeError,UnicodeError) as exc:
        print("CENTRAL_AUTO_P9_DENIED_"+str(exc),file=sys.stderr)
        raise SystemExit(1)
