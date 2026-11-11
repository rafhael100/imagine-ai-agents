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
const f196 = beforeScript.match(/<script type="application\\/json" id="f196-transition-data">([^<]+)<\\/script>/);
assert(f196, "embedded F196 synthetic PHP fixture exists");
elements.get("f196-transition-data").textContent = f196[1];
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
assert.equal(byId("supervision-admission").textContent, "ABERTA · SIMULADA",
 "F189 open admission shown as offline fixture, not as actual availability");
// F196 transition selection is visual only and leaves F182 and F184 untouched.
assert.equal(byId("queue-transition-state").textContent, "PAUSADA · SIMULADA");
assert.equal(byId("queue-transition-pending").textContent, "1");
assert.equal(byId("queue-transition-completed").textContent, "0");
assert.equal(byId("queue-transition-unknown").textContent, "1");
assert.equal(byId("queue-transition-unresolved").textContent, "1");
assert.equal(byId("queue-transition-paused").attributes.get("aria-pressed"), "true");
byId("queue-transition-resumed").click();
assert.equal(byId("queue-transition-state").textContent, "RETOMADA · SIMULADA");
assert.equal(byId("queue-transition-pending").textContent, "0");
assert.equal(byId("queue-transition-completed").textContent, "1");
assert.equal(byId("queue-transition-unknown").textContent, "1");
assert.equal(byId("queue-transition-unresolved").textContent, "1");
assert.equal(byId("queue-transition-paused").attributes.get("aria-pressed"), "false");
assert.equal(byId("queue-transition-resumed").attributes.get("aria-pressed"), "true");
assert.equal(byId("supervision-indeterminate").textContent, "3", "F182 immutable");
assert.equal(byId("supervision-decision").textContent, "NO-GO");
byId("queue-transition-paused").click();
assert.equal(byId("queue-transition-completed").textContent, "0");
assert.equal(byId("queue-transition-pending").textContent, "1");
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
// F185: create a *local draft*, never a live agent, and inspect only a
// pre-existing closed scenario. Unknown template and hostile names fail closed.
selectors["[data-page-button]"].find(x => x.dataset.pageButton === "agents").click();
assert.equal(byId("page-agents").hidden, false, "agent catalog opened");
assert.equal(byId("agent-draft-test").disabled, true, "no scenario without a draft");
byId("agent-draft-template").value="crm";
byId("agent-draft-name").value="Agente de Apoio";
byId("agent-draft-name").handlers.input();
byId("agent-draft-generate").click();
assert.equal(byId("agent-draft-preview-name").textContent, "Agente de Apoio");
assert.equal(byId("agent-draft-preview-template").textContent, "Qualificação CRM");
assert.equal(byId("agent-draft-preview-state").textContent, "RASCUNHO LOCAL · SEM APROVAÇÃO");
assert.equal(byId("agent-draft-test").disabled, false, "valid draft permits offline navigation");
assert.equal(byId("audit-runs").textContent, "0", "drafting is not an execution");
byId("agent-draft-test").click();
assert.equal(byId("page-lab").hidden, false, "draft opens existing offline scenario");
assert.equal(byId("case-id").textContent, "U07-01", "crm template chooses fixed allow scenario");
assert.equal(byId("audit-runs").textContent, "0", "opening scenario does not execute it");

selectors["[data-page-button]"].find(x => x.dataset.pageButton === "agents").click();
byId("agent-draft-template").value="performance";
byId("agent-draft-template").handlers.change();
assert.equal(byId("agent-draft-test").disabled, true, "changed template invalidates old draft");
byId("agent-draft-name").value="Análise de Campanhas";
byId("agent-draft-generate").click();
assert.equal(byId("agent-draft-preview-template").textContent, "Análise de Performance");
byId("agent-draft-test").click();
assert.equal(byId("case-id").textContent, "U07-08", "performance requires dynamic authoritative source");

selectors["[data-page-button]"].find(x => x.dataset.pageButton === "agents").click();
byId("agent-draft-name").value="<img src=x onerror=alert(1)>";
byId("agent-draft-generate").click();
assert.equal(byId("agent-draft-test").disabled, true, "hostile name cannot produce draft");
assert.equal(byId("agent-draft-preview-name").textContent, "—", "hostile input is not rendered");
assert.equal(byId("agent-draft-status").textContent.includes("<img"), false, "no untrusted error echo");
byId("agent-draft-name").value="A".repeat(100000);
byId("agent-draft-generate").click();
assert.equal(byId("agent-draft-test").disabled, true, "oversized input fails before regex");
byId("agent-draft-template").value="__proto__";
byId("agent-draft-name").value="Modelo de Ataque";
byId("agent-draft-generate").click();
assert.equal(byId("agent-draft-test").disabled, true, "unknown prototype property cannot select a template");
byId("agent-draft-template").value="universidade";
byId("agent-draft-name").value="Tutor de Estudos";
byId("agent-draft-generate").click();
assert.equal(byId("agent-draft-test").disabled, false, "third allowed model works");
byId("agent-draft-test").click();
assert.equal(byId("case-id").textContent, "U07-09", "university template selects untrusted document scenario");
selectors["[data-page-button]"].find(x => x.dataset.pageButton === "agents").click();
byId("agent-draft-reset").click();
assert.equal(byId("agent-draft-test").disabled, true, "reset revokes local scenario link");
assert.equal(byId("agent-draft-name").value, "", "reset clears user name");
assert.equal(byId("agent-draft-template").value, "crm", "reset restores safe template");
assert.equal(byId("audit-runs").textContent, "0", "no accidental test execution");
// F186: a bounded, deduplicated collection lives in memory only.
assert.equal(byId("agent-draft-count").textContent, "0 / 5", "initial collection empty");
assert.equal(byId("agent-draft-save").disabled, true, "cannot save unvalidated preview");
byId("agent-draft-template").value="crm";
byId("agent-draft-name").value="Agente Um";
byId("agent-draft-generate").click();
byId("agent-draft-save").click();
assert.equal(byId("agent-draft-count").textContent, "1 / 5", "first saved in session");
assert.equal(byId("agent-draft-collection").children.length, 1, "one row only");
byId("agent-draft-save").click();
assert.equal(byId("agent-draft-count").textContent, "1 / 5", "exact duplicate idempotent");
byId("agent-draft-template").value="performance";
byId("agent-draft-name").value="AGENTE UM";
byId("agent-draft-generate").click();
byId("agent-draft-save").click();
assert.equal(byId("agent-draft-count").textContent, "1 / 5", "conflicting case-folded name denied");
assert.equal(byId("agent-draft-collection-status").textContent.includes("Nome já utilizado"), true, "conflict visible");
for(const [name,template] of [
 ["Agente Dois","performance"],["Agente Três","universidade"],
 ["Agente Quatro","crm"],["Agente Cinco","performance"]
]){
 byId("agent-draft-template").value=template;
 byId("agent-draft-name").value=name;
 byId("agent-draft-generate").click();
 byId("agent-draft-save").click();
}
assert.equal(byId("agent-draft-count").textContent, "5 / 5", "capacity limited to five");
assert.equal(byId("agent-draft-collection").children.length, 5, "five rows");
byId("agent-draft-template").value="crm";
byId("agent-draft-name").value="Agente Seis";
byId("agent-draft-generate").click();
byId("agent-draft-save").click();
assert.equal(byId("agent-draft-count").textContent, "5 / 5", "sixth denied with no eviction");
assert.equal(byId("agent-draft-collection-status").textContent.includes("Limite local"), true);
byId("agent-draft-name").value="Nome Alterado Sem Evento";
byId("agent-draft-save").click();
assert.equal(byId("agent-draft-count").textContent, "5 / 5", "silent field mutation not saved");
assert.equal(byId("agent-draft-test").disabled, true, "invalidated stale preview");
const savedFirst=byId("agent-draft-collection").children[0];
assert.equal(savedFirst.children[0].textContent, "Agente Um · Qualificação CRM");
savedFirst.children[1].click();
assert.equal(byId("agent-draft-preview-name").textContent, "Agente Um", "restore a bounded existing draft");
assert.equal(byId("agent-draft-test").disabled, false, "restored preview can open closed scenario");
savedFirst.children[2].click();
assert.equal(byId("agent-draft-count").textContent, "4 / 5", "deleting frees a slot");
assert.equal(byId("agent-draft-test").disabled, true, "deleting active draft clears preview");
savedFirst.children[1].click();
assert.equal(byId("agent-draft-test").disabled, true, "stale detached row cannot resurrect deleted draft");
byId("agent-draft-name").value="Agente Seis";
byId("agent-draft-template").value="crm";
byId("agent-draft-generate").click();
byId("agent-draft-save").click();
assert.equal(byId("agent-draft-count").textContent, "5 / 5", "released slot reusable");
byId("agent-draft-clear-all").click();
assert.equal(byId("agent-draft-count").textContent, "0 / 5", "clear all destroys transient data");
assert.equal(byId("agent-draft-collection").children.length, 1, "empty placeholder only");
assert.equal(byId("agent-draft-test").disabled, true, "clear all revokes current preview");
assert.equal(byId("audit-runs").textContent, "0", "draft collection did not execute scenarios");
// F187: transfer packages are explicit, local-only and atomically validated.
byId("agent-draft-template").value="crm";
byId("agent-draft-name").value="Agente Alfa";
byId("agent-draft-generate").click();
byId("agent-draft-save").click();
byId("agent-draft-template").value="universidade";
byId("agent-draft-name").value="Tutor Beta";
byId("agent-draft-generate").click();
byId("agent-draft-save").click();
assert.equal(byId("agent-draft-count").textContent, "2 / 5", "fixture for export");
byId("agent-draft-export").click();
const transferPackage=byId("agent-draft-transfer-json").value;
assert.deepEqual(JSON.parse(transferPackage),{
 version:"SYNTHETIC_AGENT_DRAFTS_V1",
 drafts:[{name:"Agente Alfa",template:"crm"},{name:"Tutor Beta",template:"universidade"}]
}, "export closed deterministic schema");
byId("agent-draft-clear-json").click();
assert.equal(byId("agent-draft-transfer-json").value, "", "only JSON text cleared");
assert.equal(byId("agent-draft-count").textContent, "2 / 5", "clear JSON preserves drafts");
byId("agent-draft-clear-all").click();
assert.equal(byId("agent-draft-count").textContent, "0 / 5", "local list cleared");
byId("agent-draft-transfer-json").value=transferPackage;
byId("agent-draft-import").click();
assert.equal(byId("agent-draft-count").textContent, "2 / 5", "roundtrip restored list");
assert.equal(byId("agent-draft-collection").children[1].children[0].textContent,
 "Tutor Beta · Tutor de Conhecimento", "roundtrip model preserved");
const expectedList=[
 byId("agent-draft-collection").children[0].children[0].textContent,
 byId("agent-draft-collection").children[1].children[0].textContent
];
function rejectedImport(packet,description){
 byId("agent-draft-transfer-json").value=packet;
 byId("agent-draft-import").click();
 assert.equal(byId("agent-draft-count").textContent, "2 / 5", description+" count unchanged");
 assert.deepEqual(byId("agent-draft-collection").children.map(x=>x.children[0].textContent),
  expectedList, description+" list unchanged");
 assert.equal(byId("agent-draft-transfer-status").textContent.includes("não foi alterada"), true,
  description+" fail closed");
}
rejectedImport('not json', "malformed JSON");
rejectedImport(JSON.stringify({version:"UNKNOWN",drafts:[]}), "unknown version");
rejectedImport(JSON.stringify({version:"SYNTHETIC_AGENT_DRAFTS_V1",drafts:[],token:"SYNTHETIC_SECRET"}), "extra top-level key");
rejectedImport(JSON.stringify({version:"SYNTHETIC_AGENT_DRAFTS_V1",drafts:[{name:"Agente Malicioso",template:"__proto__"}]}), "prototype model");
rejectedImport(JSON.stringify({version:"SYNTHETIC_AGENT_DRAFTS_V1",drafts:[{name:"Agente Alfa",template:"crm",role:"admin"}]}), "unexpected role");
rejectedImport(JSON.stringify({version:"SYNTHETIC_AGENT_DRAFTS_V1",drafts:[
 {name:"Agente Alfa",template:"crm"},{name:"AGENTE ALFA",template:"performance"}]}), "casefold duplicates");
rejectedImport(JSON.stringify({version:"SYNTHETIC_AGENT_DRAFTS_V1",drafts:[
 {name:"Agente Válido",template:"crm"},{name:"<script>alert(1)</script>",template:"universidade"}]}), "atomic rejection of unsafe second item");
rejectedImport(JSON.stringify({version:"SYNTHETIC_AGENT_DRAFTS_V1",
 drafts:Array.from({length:6},(_,i)=>({name:"Agente "+i,template:"crm"}))}), "sixth item denied");
rejectedImport("X".repeat(100000), "oversized input");
byId("agent-draft-transfer-json").value=JSON.stringify({
 version:"SYNTHETIC_AGENT_DRAFTS_V1",drafts:[]
});
byId("agent-draft-import").click();
assert.equal(byId("agent-draft-count").textContent, "0 / 5", "explicit empty packet clears drafts");
assert.equal(byId("agent-draft-test").disabled, true, "successful import invalidates old preview");
assert.equal(byId("audit-runs").textContent, "0", "transfer never executes scenarios");
console.log("F108_SMOKE_PASS: 5 pages, 6 scenarios, 14 synthetic events, review sandbox, reset, agent shortcut, agent drafts, bounded draft collection, atomic JSON transfer, offline CSP");
