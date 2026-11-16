#!/usr/bin/env python3
"""AIA-P54: exercise the localhost-only, read-only demonstration in CI.

No browser, external network, real tenants, CRM, n8n or approvals.
The PHP subprocess is bound to a fresh 127.0.0.1 port and stopped in finally.
"""
from __future__ import annotations

from html.parser import HTMLParser
from pathlib import Path
import re
import socket
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT=Path(__file__).resolve().parents[1]
SCRIPT=ROOT/"examples/p54-review-queue-local.php"

class InspectHTML(HTMLParser):
    def __init__(self)->None:
        super().__init__()
        self.articles=0
        self.forms=0
        self.buttons=0
        self.scripts=0
        self.external_sources=[]
        self.links=[]
        self.text=[]
    def handle_starttag(self,tag:str,attrs:list[tuple[str,str|None]])->None:
        d=dict(attrs)
        self.articles+=int(tag=="article")
        self.forms+=int(tag=="form")
        self.buttons+=int(tag=="button")
        self.scripts+=int(tag=="script")
        if tag in ("iframe","object","embed","img","script","link"):
            val=d.get("src") or d.get("href") or ""
            if val.startswith(("https://","http://","//")):
                self.external_sources.append(val)
        if tag=="a":
            self.links.append(d.get("href",""))
    def handle_data(self,data:str)->None:
        self.text.append(data)

def request_page(base:str,path:str,method:str="GET")->tuple[int,str,dict]:
    req=urllib.request.Request(base+path,method=method)
    try:
        with urllib.request.urlopen(req,timeout=5) as resp:
            return resp.status,resp.read().decode("utf-8"),dict(resp.headers)
    except urllib.error.HTTPError as exc:
        return exc.code,exc.read().decode("utf-8"),dict(exc.headers)

def main()->None:
    assert SCRIPT.is_file()
    with socket.socket() as s:
        s.bind(("127.0.0.1",0))
        port=s.getsockname()[1]
    p=subprocess.Popen(
        ["php","-S",f"127.0.0.1:{port}","-t",str(SCRIPT.parent)],
        cwd=ROOT,stdin=subprocess.DEVNULL,
        stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL
    )
    count=0
    try:
        base=f"http://127.0.0.1:{port}"
        for _ in range(60):
            try:
                status,html,headers=request_page(
                    base,"/p54-review-queue-local.php"
                )
                break
            except (ConnectionError,TimeoutError,OSError,
                    urllib.error.URLError):
                if p.poll() is not None:
                    raise RuntimeError("P54_SERVER_PROCESS_FAILED")
                time.sleep(0.08)
        else:
            raise RuntimeError("P54_LOOPBACK_NOT_READY")
        assert status==200,("P54_UNEXPECTED_STATUS",status,html[:200])
        assert headers["Cache-Control"].startswith("no-store")
        assert headers["X-Content-Type-Options"]=="nosniff"
        assert headers["X-Frame-Options"]=="DENY"
        assert "default-src 'none'" in headers["Content-Security-Policy"]
        assert "frame-ancestors 'none'" in headers["Content-Security-Policy"]
        page=InspectHTML()
        page.feed(html)
        assert page.articles==3
        assert page.forms==page.buttons==page.scripts==0
        assert page.external_sources==[]
        assert set(page.links)=={
            "?kind=all","?kind=human","?kind=task","?kind=notice"
        }
        assert "#F9941D" in html
        assert "NO-GO operacional" in html
        assert "Nenhuma proposta pode ser aprovada ou executada" in html
        assert "noindex,nofollow,noarchive" in html
        assert "SYNTHETIC_REQUEST_P54" in html
        assert "SYNTHETIC_PROPOSAL_" not in html or page.articles==3
        count+=1

        for key,expected in [("all",3),("human",1),("task",1),("notice",1)]:
            code,body,_=request_page(
                base,"/p54-review-queue-local.php?kind="+key
            )
            sample=InspectHTML()
            sample.feed(body)
            assert code==200 and sample.articles==expected,(key,code,sample.articles)
            assert sample.forms==sample.buttons==sample.scripts==0
            count+=1

        for path,method,code in [
            ("/p54-review-queue-local.php?kind=unknown","GET",400),
            ("/p54-review-queue-local.php?kind=all&tenant=OTHER","GET",400),
            ("/p54-review-queue-local.php?company=SYNTHETIC_TENANT_B","GET",400),
            ("/p54-review-queue-local.php","POST",405),
            ("/p54-review-queue-local.php","PUT",405),
            ("/p54-review-queue-local.php","DELETE",405),
        ]:
            result,body,_=request_page(base,path,method)
            assert result==code and body=="P54_LOCAL_DEMO_DENIED_NO_OPERATIONAL_ACCESS",(path,method,result)
            count+=1

        cli=subprocess.run(
            ["php",str(SCRIPT)],cwd=ROOT,text=True,
            capture_output=True,timeout=5,check=False
        )
        assert cli.returncode==0
        assert cli.stdout=="P54_LOCAL_DEMO_DENIED_NO_OPERATIONAL_ACCESS"
        assert "SYNTHETIC" not in cli.stdout
        assert not cli.stderr
        count+=1

        assert "http://" not in html and "https://" not in html
        assert not re.search(r"<\s*(?:form|script|button|iframe)\b",html,re.I)
        count+=1

        print(
            f"AIA_P54_LOCAL_READONLY_DEMO_PASS checks={count} "
            "3_local_drafts=1 filters=4 method_denial=1 "
            "no_external_assets=1 no_approval=1 no_actions=1 NO_GO"
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
