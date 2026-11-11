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
const fixture = beforeScript.match(/<script type="application\/json" id="f183-supervision-data">([^<]+)<\/script>/);
assert(fixture, "embedded F183 static JSON exists");
elements.get("f183-supervision-data").textContent = fixture[1];
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
assert.equal(selectors["[data-scenario]"].find(x => x.dataset.scenario === "allow").attributes.get("aria-pressed"), "true", "default scenario accessible state");
const expected = [
    ["allow", "READY"], ["tenant", "KNOWLEDGE_DENIED"],
    ["revoke", "KNOWLEDGE_DENIED"], ["conflict", "CONFLICT"],
    ["dynamic", "DYNAMIC_SOURCE_REQUIRED"], ["injection", "READY · UNTRUSTED_DATA"]
];
for (const [id, result] of expected) {
    selectors["[data-scenario]"].find(x => x.dataset.scenario === id).click();
    byId("run-button").click();
    assert.equal(byId("result").children[0].textContent, result, "case " + id);
    assert.equal(selectors["[data-scenario]"].find(x => x.dataset.scenario === id).attributes.get("aria-pressed"), "true", "selected aria-pressed " + id);
}
selectors["[data-page-button]"].find(x => x.dataset.pageButton === "supervision").click();
assert.equal(byId("page-supervision").hidden, false, "new supervision tab is navigable");
assert.equal(byId("page-overview").hidden, true, "overview hidden under supervision");
assert.equal(byId("supervision-completed").textContent, "1", "completed count from fixture");
assert.equal(byId("supervision-indeterminate").textContent, "3", "unknown outcome remains visible");
assert.equal(byId("supervision-pending-review").textContent, "1", "second review still pending");
assert.equal(byId("supervision-conflict").textContent, "1", "conflict visible");
assert.equal(byId("supervision-unresolved").textContent, "1", "matching votes do not resolve uncertainty");
assert.equal(byId("supervision-decision").textContent, "NO-GO", "never authorized");
// F184: reviewing is a disposable browser-only *hypothesis*, not a vote
// recorded by the PHP notebook or a change to the official F182 snapshot.
assert.equal(byId("review-sim-second").textContent, "1", "initial second review pending");
assert.equal(byId("review-sim-indeterminate").textContent, "3", "initial unknown outcomes");
assert.equal(byId("review-sim-agree").disabled, false, "initial action enabled");
byId("review-sim-agree").click();
assert.equal(byId("review-sim-second").textContent, "0", "simulated second vote consumed");
assert.equal(byId("review-sim-unresolved").textContent, "2", "agreement stays unresolved");
assert.equal(byId("review-sim-conflict").textContent, "1", "conflict does not disappear");
assert.equal(byId("review-sim-indeterminate").textContent, "3", "unknown outcome preserved");
assert.equal(byId("review-sim-agree").disabled, true, "no double submit");
assert.equal(byId("review-sim-disagree").disabled, true, "no conflicting second vote");
byId("review-sim-disagree").click();
assert.equal(byId("review-sim-conflict").textContent, "1", "duplicate blocked even via synthetic event stub");
assert.equal(byId("supervision-second").textContent, "1", "original F182 snapshot unchanged");
assert.equal(byId("supervision-unresolved").textContent, "1", "original F182 review tally unchanged");
assert.equal(byId("supervision-decision").textContent, "NO-GO", "review does not grant production");
assert.equal(byId("audit-runs").textContent, "6", "review does not forge historical execution");
byId("review-sim-reset").click();
assert.equal(byId("review-sim-second").textContent, "1", "reset restores second review");
assert.equal(byId("review-sim-unresolved").textContent, "1", "reset restores reviewed count");
assert.equal(byId("review-sim-agree").disabled, false, "action reset");
byId("review-sim-disagree").click();
assert.equal(byId("review-sim-second").textContent, "0", "disagreement closes hypothesis");
assert.equal(byId("review-sim-conflict").textContent, "2", "conflict rises");
assert.equal(byId("review-sim-unresolved").textContent, "1", "disagreement does not invent agreement");
assert.equal(byId("review-sim-indeterminate").textContent, "3", "still unknown");
byId("review-sim-reset").click();
assert.equal(byId("review-sim-conflict").textContent, "1", "reset clears disagreement");
assert.equal(byId("review-sim-events").textContent, "0 observações fictícias adicionadas nesta sessão.", "reset clears transient event count");
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
console.log("F108_SMOKE_PASS: 5 pages, 6 scenarios, 14 synthetic events, review sandbox, reset, agent shortcut, offline CSP");
