/* Dependency-free DOM evaluation smoke for the fully synthetic F1-09 HTML.
   Run: node tests/Browser/f109_smoke.cjs. No real agents or network. */
"use strict";
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");
const assert = require("node:assert/strict");
const html = fs.readFileSync(path.resolve(__dirname, "../../examples/f109-comparador-versoes-offline.html"), "utf8");
const script = html.match(/<script>\s*([\s\S]*?)\s*<\/script>/)?.[1];
assert(script, "single inline evaluator exists");
assert(html.includes("connect-src 'none'"), "CSP blocks network");
assert(!/\b(?:fetch|XMLHttpRequest|WebSocket|localStorage|sessionStorage)\s*\(/.test(html),
    "no browser network or storage API");
assert(!/https?:\/\/|<iframe\b|<form\b|<script\s+src\b/i.test(html),
    "no remote dependencies, active forms or iframes");
const markup = html.split("<script>")[0];
class Element {
    constructor(id = "", dataset = {}) {
        this.id = id;
        this.dataset = dataset;
        this.hidden = false;
        this.textContent = "";
        this.className = "";
        this.children = [];
        this.handlers = {};
        this.attrs = new Map();
    }
    addEventListener(type, fn) { this.handlers[type] = fn; }
    click() { assert.equal(typeof this.handlers.click, "function", "bound event"); this.handlers.click(); }
    setAttribute(k, v) { this.attrs.set(k, v); }
    removeAttribute(k) { this.attrs.delete(k); }
    replaceChildren(...nodes) { this.children = nodes; }
    append(...nodes) { this.children.push(...nodes); }
}
const ids = [...markup.matchAll(/\bid="([^"]+)"/g)].map(m => m[1]);
assert.equal(ids.length, new Set(ids).size, "unique IDs");
const elements = new Map(ids.map(id => [id, new Element(id)]));
const selectors = { "[data-page]":[], "[data-go]":[], "[data-candidate]":[] };
for (const [name, key] of [["data-page","page"],["data-go","go"],["data-candidate","candidate"]]) {
    for (const match of markup.matchAll(new RegExp(name + '="([^"]+)"', "g"))) {
        selectors["[" + name + "]"].push(new Element("", { [key]: match[1] }));
    }
}
const document = {
    getElementById(id) { assert(elements.has(id), "missing ID " + id); return elements.get(id); },
    querySelectorAll(sel) { assert(selectors[sel], "unsupported selector"); return selectors[sel]; },
    createElement(tag) { return new Element(tag); }
};
vm.runInNewContext(script, { document, window:{ scrollTo(){} } },
    { timeout:1000, filename:"f109-visual-laboratory-inline.js" });
const get = id => elements.get(id);
const button = (key, value) => selectors["[" + key + "]"].find(el => Object.values(el.dataset)[0] === value);
assert.equal(get("page-overview").hidden, false);
button("data-go","compare").click();
assert.equal(get("page-compare").hidden, false);
const expectations = [
    ["safe", "REVISÃO HUMANA NECESSÁRIA", "6/6", "0", "0"],
    ["regression", "BLOQUEADO · regressão crítica", "4/6", "2", "0"],
    ["incomplete", "INCONCLUSIVO · caso não executado", "5/6", "0", "1"]
];
for (const [candidate, title, passed, critical, pending] of expectations) {
    button("data-candidate",candidate).click();
    assert.equal(button("data-candidate",candidate).attrs.get("aria-pressed"), "true");
    get("compare-run").click();
    assert.equal(get("stat-pass").textContent, passed, candidate + " passed");
    assert.equal(get("stat-critical").textContent, critical, candidate + " critical");
    assert.equal(get("stat-pending").textContent, pending, candidate + " pending");
    assert.equal(get("compare-gate").children[0].textContent, title, candidate + " gate");
    assert.equal(get("compare-rows").children.length, 6, "six cases for " + candidate);
}
button("data-page","history").click();
assert.equal(get("page-history").hidden, false);
assert.equal(get("history-list").children.length, 3);
assert.equal(get("history-count").textContent, "3 execuções");
get("history-reset").click();
assert.equal(get("history-count").textContent, "0 execuções");
assert.equal(get("history-list").children.length, 1);
button("data-page","compare").click();
get("compare-clear").click();
assert.equal(get("compare-rows").children.length, 1);
assert.equal(get("stat-pass").textContent, "—");
console.log("F109_SMOKE_PASS: 3 pages; 3 candidate gates; 6 cases each; human review; history/reset; CSP offline");
