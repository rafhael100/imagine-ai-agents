#!/usr/bin/env python3
"""CENTRAL-AUTO-P5: synthetic CRM state mTLS fixture server for CI only.

This server is not the CRM application, not a production API, not authority for
any business effect. Network listener is strictly 127.0.0.1 on a CI runner.
Mutual TLS is mandatory, as is a typed, time-bounded, MAC-bound request.
Replay prevention is atomic in a DISPOSABLE PostgreSQL CI database.
The role and company fixture are NOT operational identity management.
"""
from __future__ import annotations

import base64
import hashlib
import hmac
import json
import os
import re
import ssl
import sys
import subprocess
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

COMPANY_A="aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa"
SERVICE="portal-automation-admin"
AUDIENCE="crm-automation-state"
SECRET=b"SYNTHETIC_CENTRAL_AUTO_P5_ONLY_TEST_KEY_NOT_REAL"
PATH="/private/automation-state"
REF=re.compile(r"\ASYNTHETIC_[A-Z0-9_]{1,40}\Z")
FIELDS={"schema","issuer","audience","company_id","request_id","nonce","issued_at","mac"}


def canonical(data:dict)->bytes:
    return "|".join(str(data[k]) for k in (
        "schema","issuer","audience","company_id","request_id","nonce","issued_at"
    )).encode("ascii")


def deny_reason(body:object,certificate:dict)->str|None:
    if not isinstance(body,dict) or set(body)!=FIELDS:
        return "CLOSED_SCHEMA"
    if not all(isinstance(body.get(k),str) for k in FIELDS-{"issued_at"}):
        return "TYPE"
    if type(body.get("issued_at")) is not int:
        return "TIME_TYPE"
    if body["schema"]!="CENTRAL_AUTO_MTLS_REQUEST_V1" or body["issuer"]!=SERVICE:
        return "ISSUER"
    if body["audience"]!=AUDIENCE or body["company_id"]!=COMPANY_A:
        return "SCOPE"
    for k in ("request_id","nonce"):
        if not REF.fullmatch(body[k]):
            return "REFERENCE"
    now=int(time.time())
    if body["issued_at"]>now+5 or body["issued_at"]<now-60:
        return "EXPIRED"
    names={v for rdn in certificate.get("subject",()) for (kind,v) in rdn if kind=="commonName"}
    if names!={SERVICE}:
        return "CLIENT_CERT_CN"
    mac=body["mac"]
    if not re.fullmatch(r"[a-f0-9]{64}",mac):
        return "MAC_FORMAT"
    expected=hmac.new(SECRET,canonical(body),hashlib.sha256).hexdigest()
    if not hmac.compare_digest(mac,expected):
        return "MAC"
    return None


def admit_pg(body:dict)->str:
    """Write the already mTLS-verified request to a PostgreSQL *test* ledger.

    A restricted synthetic DB credential is required. Any database error
    denies the request. This is not real CRM/Portal identity management.
    """
    if os.environ.get("CENTRAL_AUTO_P6_PG_NONCE")!="true":
        return "LAB_DENY_STORAGE"
    issuer=body["issuer"];audience=body["audience"]
    company=body["company_id"];request=body["request_id"]
    nonce=body["nonce"];issued=body["issued_at"]
    digest=hashlib.sha256(canonical(body)+b"|"+body["mac"].encode()).hexdigest()
    # All values have been restricted to ASCII labels, UUID constants, and
    # server-validated integers before entering this SQL fixture command.
    args=[issuer,audience,company,request,nonce,digest]
    if not all(re.fullmatch(r"[A-Za-z0-9_-]{1,80}",v) for v in args):
        return "LAB_DENY_STORAGE"
    query="SELECT central_auto_lab.admit_nonce("+",".join(
        "'"+v+"'" for v in args
    )+","+str(int(issued))+");"
    try:
        result=subprocess.run([
            "psql","-X","-q","-A","-t","-w",
            "-h","127.0.0.1","-p","5432",
            "-U","ca_mtls_lab_service",
            "-d","imagine_agents_ci",
            "-v","ON_ERROR_STOP=1","-c",query,
        ],capture_output=True,text=True,timeout=5,check=False,
        env={**os.environ,
             "PGPASSWORD":"SYNTHETIC_CENTRAL_AUTO_P6_DB_LOGIN_ONLY"})
    except (OSError,subprocess.TimeoutExpired):
        return "LAB_DENY_STORAGE"
    if result.returncode!=0 or result.stderr.strip():
        return "LAB_DENY_STORAGE"
    response=result.stdout.strip()
    if response in ("LAB_ADMITTED_NO_DELIVERY","LAB_REPLAY_DENIED"):
        return response
    return "LAB_DENY_STORAGE"


class Handler(BaseHTTPRequestHandler):
    server_version="CENTRAL_AUTO_P5_SYNTHETIC"
    sys_version=""
    def log_message(self, *_args)->None:
        pass
    def respond(self, status:int, value:dict)->None:
        payload=json.dumps(value,sort_keys=True,separators=(",",":")).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type","application/json")
        self.send_header("Cache-Control","no-store")
        self.send_header("Content-Length",str(len(payload)))
        self.end_headers()
        self.wfile.write(payload)
    def do_GET(self)->None:
        self.respond(405,{"error":"LAB_METHOD_DISABLED"})
    def do_POST(self)->None:
        if self.path!=PATH or self.headers.get("Content-Type")!="application/json":
            self.respond(404,{"error":"LAB_NOT_FOUND"})
            return
        try:
            length=int(self.headers.get("Content-Length","-1"))
        except ValueError:
            length=-1
        if not 2<=length<=4096:
            self.respond(413,{"error":"LAB_INVALID_SIZE"})
            return
        try:
            raw=self.rfile.read(length)
            body=json.loads(raw,object_pairs_hook=closed_pairs)
        except (ValueError,UnicodeDecodeError):
            self.respond(403,{"error":"LAB_DENIED"})
            return
        reason=deny_reason(body,self.connection.getpeercert())
        if reason is not None:
            self.respond(403,{"error":"LAB_DENIED"})
            return
        admission=admit_pg(body)
        if admission=="LAB_REPLAY_DENIED":
            self.respond(409,{"error":"LAB_REPLAY"})
            return
        if admission!="LAB_ADMITTED_NO_DELIVERY":
            self.respond(503,{"error":"LAB_STORAGE_DENIED"})
            return
        state=self.server.synthetic_state
        # Transport returns a closed, signed response wrapper. Portal's real
        # AutomationStateReader checks the inner schema and authorization.
        body64=base64.b64encode(
            json.dumps(state,sort_keys=True,separators=(",",":")).encode()
        ).decode("ascii")
        ack={
          "schema":"CENTRAL_AUTO_MTLS_RESPONSE_V1",
          "request_id":body["request_id"],
          "nonce":body["nonce"],
          "company_id":body["company_id"],
          "body_b64":body64,
        }
        ack["mac"]=hmac.new(SECRET,(
            "|".join(ack[k] for k in (
                "schema","request_id","nonce","company_id","body_b64"
            ))
        ).encode("ascii"),hashlib.sha256).hexdigest()
        self.respond(200,ack)


def closed_pairs(pairs):
    result={}
    for k,v in pairs:
        if k in result:
            raise ValueError("duplicate key")
        result[k]=v
    return result


def main()->None:
    if os.environ.get("GITHUB_ACTIONS")!="true" or (
        os.environ.get("GITHUB_REPOSITORY")!="rafhael100/imagine-ai-agents"
    ):
        raise SystemExit("CI_ONLY")
    root=Path(os.environ["CENTRAL_AUTO_P5_FIXTURES"])
    keydir=Path(os.environ["CENTRAL_AUTO_P5_CERT_DIR"])
    state=json.loads(root.read_text())
    if not isinstance(state,dict) or state.get("company_id")!=COMPANY_A or (
        state.get("operational_authorization") is not False
        or state.get("ai_provider_calls")!=0
        or state.get("source_kind")!="synthetic_private_database"
    ):
        raise SystemExit("UNTRUSTED_FIXTURE")
    server=ThreadingHTTPServer(("127.0.0.1",18787),Handler)
    server.synthetic_state=state
    ctx=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
    ctx.minimum_version=ssl.TLSVersion.TLSv1_2
    ctx.verify_mode=ssl.CERT_REQUIRED
    ctx.load_cert_chain(str(keydir/"server.crt"),str(keydir/"server.key"))
    ctx.load_verify_locations(str(keydir/"ca.crt"))
    server.socket=ctx.wrap_socket(server.socket,server_side=True)
    print("CENTRAL_AUTO_P5_MTLS_LAB_READY localhost_only=1",flush=True)
    try:
        server.serve_forever(poll_interval=0.1)
    finally:
        server.server_close()


if __name__=="__main__":
    main()
