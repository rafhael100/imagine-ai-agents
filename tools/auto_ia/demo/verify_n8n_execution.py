#!/usr/bin/env python3
"""Check real n8n CLI execution JSON; fail closed on unexpected execution/AI usage.
Offline verifier only. Never permits paid calls, deployment or production.
"""
import copy
import json
import re
import sys
from pathlib import Path

TRIGGER="Disparo manual — somente laboratório"
CALCULATE="Calcular funil sem IA"
EXPECTED={
 "schema":"AUTO_IA_DEMO_RESULT_V1","decision":"NO_AI",
 "tenant_ref":"SYNTHETIC_TENANT_DEMO","event_ref":"SYNTHETIC_EVENT_DEMO",
 "visits":120,"projected_leads":12,"projected_customers":2,
 "ai_api_calls":0,"ai_tokens":0,"commercial_credits_debited":0,
 "operational_authorization":False,"mode":"SYNTHETIC_MANUAL_ONLY"
}

class Denied(Exception): pass

def parse(raw):
    if not isinstance(raw,bytes) or not 20<=len(raw)<=2_000_000:
        raise Denied("INVALID_OUTPUT_SIZE")
    try: s=raw.decode("utf-8")
    except UnicodeDecodeError as ex: raise Denied("INVALID_UTF8") from ex
    decoder=json.JSONDecoder()
    results=[]
    for m in re.finditer(r"(?m)^\{",s):
        try: obj,end=decoder.raw_decode(s[m.start():])
        except ValueError: continue
        if not isinstance(obj,dict) or not isinstance(obj.get("data"),dict):
            continue
        if not isinstance(obj["data"].get("resultData"),dict): continue
        if s[m.start()+end:].strip(): raise Denied("TRAILING_OUTPUT")
        results.append(obj)
    if len(results)!=1: raise Denied("REQUIRE_EXACTLY_ONE_CLI_RESULT")
    return results[0]

def verify(obj):
    if not isinstance(obj,dict) or not isinstance(obj.get("data"),dict):
        raise Denied("MISSING_DATA")
    result=obj["data"].get("resultData")
    if not isinstance(result,dict) or result.get("error") is not None:
        raise Denied("ERROR_OR_NO_RESULT")
    if result.get("lastNodeExecuted")!=CALCULATE:
        raise Denied("WRONG_LAST_NODE")
    runs=result.get("runData")
    if not isinstance(runs,dict) or set(runs)!={TRIGGER,CALCULATE}:
        raise Denied("UNEXPECTED_NODE_EXECUTED")
    values={}
    for name in (TRIGGER,CALCULATE):
        record=runs[name]
        if not isinstance(record,list) or len(record)!=1:
            raise Denied("UNEXPECTED_NODE_RUN_COUNT")
        step=record[0]
        if not isinstance(step,dict) or step.get("executionStatus")!="success":
            raise Denied("FAILED_NODE_EXECUTION")
        output=step.get("data",{}).get("main")
        if not isinstance(output,list) or len(output)!=1 or (
           not isinstance(output[0],list) or len(output[0])!=1):
            raise Denied("UNEXPECTED_RESULT_ITEM_COUNT")
        item=output[0][0]
        if not isinstance(item,dict) or not isinstance(item.get("json"),dict):
            raise Denied("MISSING_JSON_ITEM")
        values[name]=item["json"]
    if values[TRIGGER]!={}:
        raise Denied("TRIGGER_DATA_CONTAMINATION")
    actual=values[CALCULATE]
    if set(actual)!=set(EXPECTED): raise Denied("EXTRA_OR_MISSING_RESULT_KEYS")
    for key,value in EXPECTED.items():
        if type(actual[key]) is not type(value) or actual[key]!=value:
            raise Denied("INVALID_VALUE_"+key)
    return {
      "schema":"AUTO_IA_N8N_RUNTIME_PROOF_V1",
      "source":"OFFICIAL_N8N_RUNTIME","visits":120,"leads":12,"customers":2,
      "ai_provider_calls":0,"ai_tokens":0,"commercial_credits_debited":0,
      "production_decision":"NO_GO"
    }

def synthetic_record():
    def step(data):
        return [{"executionStatus":"success","data":{"main":[[{"json":data}]]}}]
    return {"data":{"resultData":{"lastNodeExecuted":CALCULATE,
       "runData":{TRIGGER:step({}),CALCULATE:step(dict(EXPECTED))}}}}

def self_test():
    clean=synthetic_record()
    assert verify(clean)["customers"]==2
    count=0
    def negative(label,fn):
        nonlocal count
        o=copy.deepcopy(clean)
        fn(o)
        try: verify(o)
        except Denied: count+=1; return
        raise AssertionError("AUTO_IA_15_UNDETECTED_"+label)
    def result(o): return o["data"]["resultData"]
    def value(o):
        return result(o)["runData"][CALCULATE][0]["data"]["main"][0][0]["json"]
    negative("paid_call",lambda o:value(o).update(ai_api_calls=1))
    negative("tokens",lambda o:value(o).update(ai_tokens=2))
    negative("commercial_debit",lambda o:value(o).update(commercial_credits_debited=1))
    negative("bool_disguised_as_zero",lambda o:value(o).update(ai_tokens=False))
    negative("wrong_clients",lambda o:value(o).update(projected_customers=3))
    negative("new_tenant",lambda o:value(o).update(tenant_ref="LIVE_CUSTOMER"))
    negative("extra_property",lambda o:value(o).update(extra="secret"))
    negative("extra_node",lambda o:result(o)["runData"].update({"Webhook":[]}))
    negative("execution_error",lambda o:result(o).update(error={"message":"bad"}))
    negative("wrong_last_node",lambda o:result(o).update(lastNodeExecuted=TRIGGER))
    negative("failed_node",lambda o:result(o)["runData"][CALCULATE][0].update(executionStatus="error"))
    negative("trigger_payload",lambda o:result(o)["runData"][TRIGGER][0]["data"]["main"][0][0].update(json={"extra":"bad"}))
    raw=json.dumps(clean,ensure_ascii=False,indent=2).encode()
    assert verify(parse(b"n8n CLI output:\n"+raw))["leads"]==12
    for bad in (b"",b"not json",raw+b"\nmore",raw+b"\n"+raw):
        try: parse(bad)
        except Denied: count+=1; continue
        raise AssertionError("AUTO_IA_15_UNDETECTED_BAD_OUTPUT")
    print("AUTO_IA_15_NEGATIVE_PASS tests="+str(count)+" production=NO_GO")

def main():
    if len(sys.argv)==2 and sys.argv[1]=="--self-test":
        self_test()
    elif len(sys.argv)==2:
        p=Path(sys.argv[1])
        if not p.is_file() or p.is_symlink(): raise Denied("OUTPUT_FILE_NOT_TRUSTED")
        print(json.dumps(verify(parse(p.read_bytes())),sort_keys=True))
    else:
        raise Denied("REQUIRES_RAW_OUTPUT_FILE_OR_SELF_TEST")

if __name__=="__main__":
    try: main()
    except Denied as exc:
        print("AUTO_IA_15_DENY_"+str(exc),file=sys.stderr)
        raise SystemExit(1)
