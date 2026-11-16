#!/usr/bin/env python3
"""P0 — offline and deterministic checks for the sales-agent navigable demo."""
from html.parser import HTMLParser
from pathlib import Path
import shutil
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
FILE = ROOT / "examples/p0-assistente-comercial-demo.html"

class HTMLCheck(HTMLParser):
    def __init__(self):
        super().__init__()
        self.ids = set()
        self.scripts = []
        self.in_script = False
        self.external = []
        self.csp = ""
        self.navs = set()
    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if "id" in attrs:
            if attrs["id"] in self.ids:
                raise AssertionError("duplicate HTML ID")
            self.ids.add(attrs["id"])
        if tag == "script":
            self.in_script = True
        if tag in ("script", "link", "iframe", "img") and (
            "src" in attrs or "href" in attrs
        ):
            self.external.append(tag)
        if tag == "meta" and attrs.get("http-equiv") == "Content-Security-Policy":
            self.csp = attrs.get("content", "")
        if "data-nav" in attrs:
            self.navs.add(attrs["data-nav"])
    def handle_endtag(self, tag):
        if tag == "script":
            self.in_script = False
    def handle_data(self, data):
        if self.in_script:
            self.scripts.append(data)

def main():
    source = FILE.read_text(encoding="utf-8")
    h = HTMLCheck()
    h.feed(source)
    assert not h.external, "External media/script is not permitted"
    for token in (
        "default-src 'none'",
        "connect-src 'none'",
        "form-action 'none'",
        "object-src 'none'",
        "base-uri 'none'",
    ):
        assert token in h.csp, "Missing restrictive CSP"
    required = {
        "page-overview", "page-agents", "page-simulate",
        "page-approvals", "page-integrations", "visits",
        "lead-rate", "close-rate", "funnel-visits", "funnel-leads",
        "funnel-clients", "lead-select", "run-demo", "run-result",
        "run-text", "approval-items", "audit-log",
    }
    assert required.issubset(h.ids), "Missing interactive control/target"
    assert h.navs == {"overview", "agents", "simulate", "approvals", "integrations"}
    assert len(h.scripts) == 1
    script = "".join(h.scripts)
    for forbidden in (
        "fetch(", "XMLHttpRequest", "WebSocket(", "sendBeacon(",
        "localStorage", "sessionStorage", "indexedDB", "document.cookie",
        "innerHTML", "eval(", "new Function(", "window.open(",
        "navigator.clipboard", "postMessage(", "api/agents/op01",
    ):
        assert forbidden not in script, "Unsafe browser side effect"
    for token in (
        "function pipeline(", "function advice(", "// UI BOOT",
        "textContent=", "replaceChildren()", "addEventListener",
        "NENHUM" if False else "NO-GO",
        "LEAD_TESTE_ALFA",
    ):
        assert token in script or token in source

    node = shutil.which("node")
    assert node, "Node is required to exercise the actual browser logic"
    js = r'''
const fs=require('fs'), vm=require('vm'), assert=require('assert');
const s=fs.readFileSync(0,'utf8');
new vm.Script(s, {filename:'p0-demo.js'});
const funcs=s.split('// UI BOOT')[0];
assert(funcs.length<s.length, 'UI boundary missing');
const context=vm.createContext(Object.create(null));
vm.runInContext(funcs,context,{timeout:500});
const p=context.pipeline, a=context.advice;
assert.equal(p(1000,5,10).leads,50);
assert.equal(p(1000,5,10).clients,5);
assert.equal(p(1000,5,10).visits,1000);
assert.equal(p(999,1,10).leads,9);
assert.equal(p(999,1,10).clients,0);
assert.equal(p(0,80,20).clients,0);
assert.equal(p(100000,100,100).clients,100000);
for(const input of [
  [-1,5,10],[100001,5,10],[1000,-.1,10],
  [1000,101,10],[1000,5,101],[NaN,5,10],
  [Infinity,5,10],[1000,5,-1]
]) assert.equal(p(...input),null);
assert.equal(a('X','next'),null);
assert.equal(a('A','unknown'),null);
assert.equal(a('__proto__','next'),null);
assert.ok(a('A','next').text.includes('abordagem inicial'));
assert.ok(a('B','next').text.includes('diagnóstico'));
assert.ok(a('C','next').text.includes('objeções'));
assert.ok(a('C','summary').text.includes('proposta'));
assert.equal(a('A','next').ref,'LEAD_TESTE_ALFA');
console.log('P0_SALES_DEMO_JS_PASS positive=12 negative=11');
'''
    done = subprocess.run(
        [node, "-e", js], input=script, text=True, cwd=ROOT,
        capture_output=True, timeout=10
    )
    assert done.returncode == 0, done.stdout + "\n" + done.stderr
    print(done.stdout.strip())
    print("P0_SALES_DEMO_HTML_PASS csp=closed external_calls=0 storage=none "
          "mvp_status=synthetic_only operational_decision=NO_GO")

if __name__ == "__main__":
    try:
        main()
    except Exception as ex:
        print("P0_SALES_DEMO_DENIED "+type(ex).__name__+" "+str(ex)[:160],
              file=sys.stderr)
        sys.exit(1)
