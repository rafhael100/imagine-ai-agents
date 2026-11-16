#!/usr/bin/env python3
"""CENTRAL-AUTO HOM01: assess redacted inventory, never grant production GO.

No Docker/SSH/network calls or server mutations. The input is an untrusted
snapshot, not proof of Leaseweb host identity or deployment authorization.
"""
from __future__ import annotations
import json,re,sys
from pathlib import Path

PORTS={"17990","18081","18090"}
STATES={"occupied","observed_free_not_reserved","unverified"}
class InvalidInventory(ValueError):pass

def valid_number(n,maximum):
    return type(n) is int and 0<=n<=maximum

def analyze(d):
    fields={"schema","status","host_identity","changes","cpu_count",
            "memory_available_bytes","disk_free_bytes","docker",
            "ports","port_17990","gates"}
    if not isinstance(d,dict) or set(d)!=fields:
        raise InvalidInventory("UNEXPECTED_FIELDS")
    if (d["schema"]!="N8N_HOST_INVENTORY_V2" or d["status"]!="blocked"
        or d["host_identity"]!="unverified" or d["changes"]!=0):
        raise InvalidInventory("INPUT_MUST_STAY_BLOCKED")
    if not isinstance(d["ports"],dict) or set(d["ports"])!=PORTS:
        raise InvalidInventory("PORTS_CLOSED_SET")
    if any(v not in STATES for v in d["ports"].values()) or (
        d["port_17990"]!=d["ports"]["17990"]
    ):
        raise InvalidInventory("PORT_STATES_INVALID")
    if not isinstance(d["gates"],list) or not d["gates"] or any(
        not isinstance(v,str) or not re.fullmatch(r"[a-z0-9_]{4,72}",v)
        for v in d["gates"]
    ):
        raise InvalidInventory("GATES_INVALID")
    if not valid_number(d["disk_free_bytes"],10**16):
        raise InvalidInventory("DISK_INVALID")
    mem=d["memory_available_bytes"];cpu=d["cpu_count"]
    if (mem is not None and not valid_number(mem,10**16)) or (
        cpu is not None and not valid_number(cpu,2048)
    ):
        raise InvalidInventory("CAPACITY_INVALID")
    docker=d["docker"]
    if not isinstance(docker,dict) or type(docker.get("available")) is not bool:
        raise InvalidInventory("DOCKER_INVALID")
    if docker["available"]:
        if set(docker)!={"available","containers","networks","volumes",
                          "container_refs","server_version","compose_version"}:
            raise InvalidInventory("DOCKER_EXTRA_FIELDS")
        if any(not valid_number(docker[k],10**6)
               for k in ("containers","networks","volumes")):
            raise InvalidInventory("COUNTS_INVALID")
        refs=docker["container_refs"]
        if (not isinstance(refs,list) or len(refs)>128 or
            any(not isinstance(x,str) or not re.fullmatch(r"[a-f0-9]{16}",x)
                for x in refs) or len(refs)>docker["containers"]):
            raise InvalidInventory("CONTAINER_REFS_INVALID")
        for key in ("server_version","compose_version"):
            value=docker[key]
            if value is not None and (not isinstance(value,str) or
                not re.fullmatch(r"[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,4}",value)):
                raise InvalidInventory("VERSION_INVALID")
    elif set(docker)!={"available","server_version","compose_version"} or (
        docker["server_version"] is not None or
        docker["compose_version"] is not None
    ):
        raise InvalidInventory("DOCKER_UNAVAILABLE_DATA")
    risk=[]
    if not docker["available"]:risk.append("DOCKER_UNVERIFIED")
    elif not docker["server_version"] or not docker["compose_version"]:
        risk.append("DOCKER_VERSION_UNVERIFIED")
    if cpu is None or cpu<4:risk.append("CPU_HEADROOM_NOT_PROVEN")
    if mem is None or mem<4*1024**3:risk.append("RAM_HEADROOM_NOT_PROVEN")
    if d["disk_free_bytes"]<30*1024**3:risk.append("DISK_HEADROOM_NOT_PROVEN")
    if d["ports"]["17990"]=="occupied":
        risk.append("CANDIDATE_PORT_OCCUPIED")
    elif d["ports"]["17990"]=="unverified":
        risk.append("CANDIDATE_PORT_UNVERIFIED")
    for port in ("18081","18090"):
        if d["ports"][port]=="unverified":
            risk.append("EXISTING_PORT_"+port+"_UNVERIFIED")
    return {
      "schema":"N8N_HOST_READINESS_REPORT_V1",
      "result":"NO_GO",
      "source_claim":"UNAUTHENTICATED_SNAPSHOT",
      "host_identity_verified":False,
      "provisional_capacity":"not_disqualified_by_thresholds" if (
        cpu is not None and cpu>=4 and mem is not None and
        mem>=4*1024**3 and d["disk_free_bytes"]>=30*1024**3
      ) else "insufficient_or_unknown",
      "port_observation":dict(d["ports"]),
      "risk_flags":risk,
      "pending":["CONFIRM_LEASEWEB_HOST_IDENTITY",
                 "VERIFY_CRM_SITES_PORT_OWNERSHIP",
                 "REVIEW_N8N_LICENSE","SECURE_HTTPS_MFA_INGRESS",
                 "OPERATIONAL_SERVICE_IAM","SECRET_CUSTODY",
                 "FULL_VOLUME_AND_KEYS_RESTORE",
                 "INDEPENDENT_APPSEC_DBA_LGPD","HUMAN_STAGE_APPROVAL"],
      "mutations_performed":0,
      "production_authorization":False
    }

def main():
    if len(sys.argv)!=2:raise InvalidInventory("REQUIRE_INPUT_JSON_FILE")
    path=Path(sys.argv[1])
    if not path.is_file() or path.is_symlink() or path.stat().st_size>65536:
        raise InvalidInventory("UNTRUSTED_INPUT_FILE")
    def closed(pairs):
        d={}
        for k,v in pairs:
            if k in d:raise InvalidInventory("DUPLICATE_JSON_KEY")
            d[k]=v
        return d
    data=json.loads(path.read_bytes(),object_pairs_hook=closed)
    print(json.dumps(analyze(data),sort_keys=True))

if __name__=="__main__":
    try:main()
    except (InvalidInventory,OSError,UnicodeError,ValueError) as e:
        print("CENTRAL_AUTO_HOM01_DENIED_"+str(e),file=sys.stderr)
        raise SystemExit(1)
