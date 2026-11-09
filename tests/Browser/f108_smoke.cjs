/* Offline dependency-free DOM smoke for examples/f108-laboratorio-navegavel.html.
 * Usage: node tests/Browser/f108_smoke.cjs
 * Does not call any network, browser extension, provider, or API.
 */
"use strict";
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");
const assert = require("node:assert/strict");
const html = fs.readFileSync(path.resolve(__dirname, "../../examples/f108-laboratorio-navegavel.html"), "utf8");
const beforeScript = html.split("<script>")[0];
const script = html.match(/<script>\s*([\s\S]*?)\s*<\/script>/)?.[1];
assert(script, "inline script exists");
assert(html.includes("connect-src 'none'"), "network blocked by CSP");
assert(!/\b(?:fetch|XMLHttpRequest|WebSocket|localStorage|sessionStorage)\s*\(/.test(script),
    "no network or persistent browser storage used");
assert(!/https?:\/\/|<iframe\b|<form\b|<script\s+src\b/i.test(html), "no external links, forms, iframes or scripts");

class Element {
    constructor(id = "", dataset = {}) {
        this.id = id; this.dataset = dataset; this.hidden = false; this.textContent = "";
        this.children = []; this.attributes = new Map(); this.handlers = {}; this.classes = new Set();
        this.classList = { toggle: (name, on) => on ? this.classes.add(name) : this.classes.delete(name) };
    }
    addEventListener(type, fn) { this.handlers[type] = fn; }
    click() { assert.equal(typeof this.handlers.click, "function", "bound click " + this.id); this.handlers.click(); }
    setAttribute(name, value) { this.attributes.set(name, value); }
    removeAttribute(name) { this.attributes.delete(name); }
    replaceChildren(...children) { this.children = children; }
    append(...children) { this.children.push(...children); }
}
const ids = [...beforeScript.matchAll(/\bid="([^"]+)"/g)].map(m => m[1]);
assert.equal(new Set(ids).size, ids.length, "unique IDs");
const elements = new Map(ids.map(id => [id, new Element(id)]));
const selectors = {
    "[data-page-button]": [], "[data-goto]": [], "[data-scenario]": [],
    "[data-agent-scenario]": []
};
for (const [attr, prop] of [
    ["data-page-button", "pageButton"], ["data-goto", "goto"],
    ["data-scenario", "scenario"], ["data-agent-scenario", "agentScenario"]
]) {
    for (const match of beforeScript.matchAll(new RegExp(attr + '="([^"]+)"', "g"))) {
        selectors["[" + attr + "]"].push(new Element("", { [prop]: match[1] }));
    }
}
const document = {
    getElementById(id) { assert(elements.has(id), "missing element: " + id); return elements.get(id); },
    querySelectorAll(selector) { assert(selectors[selector], "unexpected selector"); return selectors[selector]; },
    createElement(tag) { return new Element(tag); }
};
vm.runInNewContext(script, { document, window: { scrollTo() {} } },
    { timeout: 1000, filename: "f108-visual-laboratory-inline.js" });

const byId = id => elements.get(id);
assert.equal(byId("page-overview").hidden, false, "initial overview");
const expected = [
    ["allow", "READY"], ["tenant", "KNOWLEDGE_DENIED"],
    ["revoke", "KNOWLEDGE_DENIED"], ["conflict", "CONFLICT"],
    ["dynamic", "DYNAMIC_SOURCE_REQUIRED"], ["injection", "READY · UNTRUSTED_DATA"]
];
for (const [id, result] of expected) {
    selectors["[data-scenario]"].find(x => x.dataset.scenario === id).click();
    byId("run-button").click();
    assert.equal(byId("result").children[0].textContent, result, "case " + id);
}
selectors["[data-page-button]"].find(x => x.dataset.pageButton === "audit").click();
assert.equal(byId("page-audit").hidden, false, "audit navigation");
assert.equal(byId("audit-runs").textContent, "6", "six runs");
assert.equal(byId("audit-denials").textContent, "3", "three denials");
assert.equal(byId("audit-conflicts").textContent, "1", "one conflict");
assert.equal(byId("audit-list").children.length, 14, "14 synthetic events");
byId("reset-audit").click();
assert.equal(byId("audit-runs").textContent, "0", "reset runs");
assert.equal(byId("audit-list").children.length, 1, "reset trace");
selectors["[data-agent-scenario]"].find(x => x.dataset.agentScenario === "dynamic").click();
assert.equal(byId("page-lab").hidden, false, "agent opens lab");
assert.equal(byId("case-id").textContent, "U07-08", "agent selected correct case");
console.log("F108_SMOKE_PASS: 4 pages, 6 scenarios, 14 synthetic events, reset, agent shortcut, offline CSP");
