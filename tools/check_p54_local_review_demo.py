#!/usr/bin/env python3
"""P54: exercise the actual localhost-only PHP demo over HTTP in CI.

No browser SDK/network install, third-party assets, customer data or deploy.
"""
from __future__ import annotations

import socket
import subprocess
import time
import urllib.error
import urllib.request
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
SCRIPT=ROOT/"examples"/"p54-review-queue-local.php"

def request(url:str,method:str="GET")->tuple[int,bytes,dict]:
    req=urllib.request.Request(url,method=method)
    if method=="POST":
        req.data=b"approve=true"
    try:
        with urllib.request.urlopen(req,timeout=2) as response:
            return response.status,response.read(),dict(response.headers)
    except urllib.error.HTTPError as exc:
        return exc.code,exc.read(),dict(exc.headers)

def main()->None:
    assert SCRIPT.is_file()
    with socket.socket(socket.AF_INET,socket.SOCK_STREAM) as sock:
        sock.bind(("127.0.0.1",0))
        port=sock.getsockname()[1]
    addr=f"http://127.0.0.1:{port}/p54-review-queue-local.php"
    process=subprocess.Popen(
        ["php","-S",f"127.0.0.1:{port}","-t",str(ROOT/"examples")],
        cwd=ROOT,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL
    )
    checks=0
    try:
        for _ in range(35):
            if process.poll() is not None:
                raise AssertionError("P54_LOCAL_PHP_SERVER_EXITED")
            try:
                status,raw,headers=request(addr)
                break
            except (urllib.error.URLError,TimeoutError):
                time.sleep(.2)
        else:
            raise AssertionError("P54_LOOPBACK_UNAVAILABLE")
        html=raw.decode("utf-8")
        assert status==200, (status,html[:200])
        assert "Fila de propostas" in html and "NO-GO operacional." in html
        assert html.count('<article class="item">')==3
        assert '<button' not in html and '<form' not in html
        assert '<script' not in html and 'https://' not in html
        assert "NO-GO" in html
        assert "F9941D" in html
        assert headers.get("X-Frame-Options")=="DENY"
        assert headers.get("X-Content-Type-Options")=="nosniff"
        assert "no-store" in headers.get("Cache-Control","")
        assert "default-src 'none'" in headers.get("Content-Security-Policy","")
        checks+=1

        for option in ["human","task","notice"]:
            status,body,_=request(addr+f"?kind={option}")
            view=body.decode("utf-8")
            assert status==200 and view.count('<article class="item">')==1,option
            assert f'?kind={option}" aria-current="page"' in view,option
            checks+=1

        for suffix in ["?kind=send","?kind=all&approve=true",
                       "?kind[]=all","?kind=%3Cscript%3E"]:
            status,body,_=request(addr+suffix)
            assert status==400,suffix
            assert b"P54_LOCAL_DEMO_DENIED" in body
            assert b"<script" not in body
            checks+=1

        status,body,_=request(addr,"POST")
        assert status==405 and b"P54_LOCAL_DEMO_DENIED" in body
        checks+=1

        command=subprocess.run(
            ["php",str(SCRIPT)],cwd=ROOT,text=True,
            capture_output=True,timeout=3,check=False
        )
        assert command.returncode==0
        assert "P54_LOCAL_DEMO_DENIED" in command.stdout
        assert "<html" not in command.stdout
        checks+=1

        print(
            "AIA_P54_READONLY_BROWSER_DEMO_PASS "
            f"checks={checks} loopback_only=1 filters=3 unsafe_methods_denied=1 "
            "csp_no_external=1 real_approvals=0 no_ai=1 NO_GO"
        )
    finally:
        process.terminate()
        try:
            process.wait(timeout=4)
        except subprocess.TimeoutExpired:
            process.kill()
            process.wait(timeout=3)

if __name__=="__main__":
    main()
