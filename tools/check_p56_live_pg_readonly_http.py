#!/usr/bin/env python3
"""AIA-P56 adversarial real-PG/CI-only localhost HTTP browser exercise.

Only synthetic PostgreSQL16 and ephemeral role. No user credentials,
CRM, n8n, paid AI, mail, approvals or externally bound HTTP sockets.
"""
from __future__ import annotations
import json
import os
from html.parser import HTMLParser
from pathlib import Path
import socket
import subprocess
import time
import urllib.error
import urllib.request

ROOT=Path(__file__).resolve().parents[1]
PAGE="p56-live-pg-review-local.php"

class Scan(HTMLParser):
    def __init__(self)->None:
        super().__init__()
        self.articles=0
        self.links=[]
        self.actions=[]
        self.remotes=[]
    def handle_starttag(self,tag:str,attrs:list[tuple[str,str|None]])->None:
        values=dict(attrs)
        self.articles+=int(tag=="article")
        if tag in {"button","form","script","iframe"}:
            self.actions.append(tag)
        if tag=="a":
            self.links.append(values.get("href",""))
        for prop in ("src","href"):
            val=values.get(prop,"") or ""
            if val.startswith(("http://","https://","//")):
                self.remotes.append(val)

def fetch(base:str,path:str,method:str="GET",host:str|None=None)->tuple[int,str,dict]:
    hdr={}
    if host is not None:
        hdr["Host"]=host
    req=urllib.request.Request(base+path,method=method,headers=hdr)
    try:
        with urllib.request.urlopen(req,timeout=4) as resp:
            return resp.status,resp.read().decode("utf-8"),dict(resp.headers)
    except urllib.error.HTTPError as exc:
        return exc.code,exc.read().decode("utf-8"),dict(exc.headers)

def modify(sql:str)->None:
    assert ";" not in sql
    script=(
        "$pdo=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',"
        "'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);"
        "$pdo->exec("+json.dumps(sql)+");"
    )
    subprocess.run(["php","-r",script],cwd=ROOT,check=True,timeout=5,
                   stdin=subprocess.DEVNULL,capture_output=True)

def main()->None:
    assert os.environ.get("GITHUB_ACTIONS")=="true"
    assert os.environ.get("AIA_P55_SCOPED_LAB")=="true"
    assert os.environ.get("AIA_P56_BROWSER_LAB")=="true"
    pw=Path(os.environ["AIA_P56_READER_PASSWORD_FILE"])
    assert pw.is_file()
    with socket.socket(socket.AF_INET,socket.SOCK_STREAM) as sock:
        sock.bind(("127.0.0.1",0))
        port=sock.getsockname()[1]
    p=subprocess.Popen(
        ["php","-S",f"127.0.0.1:{port}","-t",str(ROOT/"examples")],
        cwd=ROOT,env=os.environ.copy(),stdin=subprocess.DEVNULL,
        stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL
    )
    checks=0
    try:
        base=f"http://127.0.0.1:{port}"
        for _ in range(60):
            if p.poll() is not None:
                raise RuntimeError("P56_SERVER_EXITED")
            try:
                status,html,headers=fetch(base,"/"+PAGE)
                break
            except (urllib.error.URLError,TimeoutError,OSError):
                time.sleep(.08)
        else:
            raise RuntimeError("P56_SERVER_NOT_READY")
        assert status==200,(status,html[:150])
        assert "BANCO PG16" in html and "#F9941D" in html
        assert "SYNTHETIC_P56_REQUEST_A" in html
        assert "SYNTHETIC_P56_REQUEST_B" not in html
        assert "NO-GO operacional" in html
        assert "default-src 'none'" in headers["Content-Security-Policy"]
        assert headers["X-Frame-Options"]=="DENY"
        assert headers["X-Content-Type-Options"]=="nosniff"
        assert headers["Cache-Control"].startswith("no-store")
        parsed=Scan()
        parsed.feed(html)
        assert parsed.articles==2
        assert parsed.actions==[] and parsed.remotes==[]
        assert set(parsed.links)=={
            "?kind=all","?kind=human","?kind=task","?kind=notice"
        }
        checks+=1

        for kind,expected in (("all",2),("human",1),("task",1),("notice",1)):
            code,body,_=fetch(base,f"/{PAGE}?kind={kind}")
            parsed=Scan()
            parsed.feed(body)
            assert code==200 and parsed.articles==expected,kind
            assert parsed.actions==[] and parsed.remotes==[]
            assert "SYNTHETIC_P56_REQUEST_B" not in body
            if kind=="notice":
                assert "Nenhuma proposta sintética nessa categoria" in body
            checks+=1

        for method,query,expected in [
            ("POST","",405),("PUT","",405),("DELETE","",405),
            ("GET","?kind=unknown",400),
            ("GET","?kind=all&tenant=SYNTHETIC_TENANT_B",400),
            ("GET","?tenant=SYNTHETIC_TENANT_B",400),
        ]:
            status,body,_=fetch(base,"/"+PAGE+query,method)
            assert status==expected and body=="P56_READONLY_LAB_DENIED",(
                method,query,status,body[:120]
            )
            checks+=1
        code,body,_=fetch(base,"/"+PAGE,"GET","evil.example")
        assert code==403 and body=="P56_READONLY_LAB_DENIED"
        checks+=1

        direct=subprocess.run(
            ["php",str(ROOT/"examples"/PAGE)],cwd=ROOT,
            text=True,capture_output=True,timeout=4,check=False
        )
        assert "P56_READONLY_LAB_DENIED" in direct.stdout
        assert "SYNTHETIC_" not in direct.stdout
        checks+=1

        # Modify only disposable fixture data through a separate CI
        # superuser connection, never via HTTP or the readonly role.
        modify(
            "UPDATE aia_p51_lab.review_drafts SET observed=31 "
            "WHERE tenant_ref='SYNTHETIC_TENANT_A' "
            "AND rule_ref='SYNTHETIC_P56_LEADS'"
        )
        code,body,_=fetch(base,"/"+PAGE)
        assert code==503 and body=="P56_READONLY_LAB_DENIED"
        assert "SYNTHETIC_P56_REQUEST_A" not in body
        checks+=1
        modify(
            "UPDATE aia_p51_lab.review_drafts SET observed=12 "
            "WHERE tenant_ref='SYNTHETIC_TENANT_A' "
            "AND rule_ref='SYNTHETIC_P56_LEADS'"
        )
        code,html,_=fetch(base,"/"+PAGE)
        assert code==200 and "SYNTHETIC_P56_REQUEST_A" in html
        checks+=1
        modify(
            "DELETE FROM aia_p51_lab.review_drafts "
            "WHERE tenant_ref='SYNTHETIC_TENANT_A' "
            "AND rule_ref='SYNTHETIC_P56_CUSTOMERS'"
        )
        code,body,_=fetch(base,"/"+PAGE)
        assert code==503 and body=="P56_READONLY_LAB_DENIED"
        checks+=1

        print(
            "AIA_P56_LIVE_PG_READONLY_HTTP_PASS "
            f"checks={checks} true_pg16=1 role_rls=1 localhost_only=1 "
            "tamper_503=1 missing_503=1 customer_data=0 approvals=0 NO_GO"
        )
    finally:
        p.terminate()
        try:
            p.wait(timeout=4)
        except subprocess.TimeoutExpired:
            p.kill()
            p.wait(timeout=4)

if __name__=="__main__":
    main()
