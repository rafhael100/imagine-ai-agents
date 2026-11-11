/* F203: zero-network adversarial execution of the real F202 triage JS. */
"use strict";
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");
const assert = require("node:assert/strict");

const html = fs.readFileSync(
    path.resolve(__dirname, "../../examples/f108-laboratorio-navegavel.html"), "utf8");
const marker = '<script type="application/json" id="f202-triage-data">';
const start = html.indexOf(marker);
assert(start >= 0, "F202 JSON fixture exists");
const fixture = JSON.parse(html.slice(start + marker.length).split("</script>", 1)[0]);
const jsStart = html.indexOf("// F202: view-only replay of the six-stage PHP F201 assessment.");
const jsEnd = html.indexOf("// F184: local UI projection only", jsStart);
assert(jsStart > 0 && jsEnd > jsStart, "actual F202 validator is bounded");
const verifier = html.slice(jsStart, jsEnd);
assert(verifier.includes("triageClosed") && verifier.includes("F202_TRIAGE_COUNTER_DENIED"),
       "actual browser validator is exercised, not a copied substitute");
assert(!/(?:fetch|XMLHttpRequest|WebSocket|localStorage)\s*\(/.test(verifier),
       "triage verifier cannot access a service or persistent storage");

const clone = () => JSON.parse(JSON.stringify(fixture));

function execute(data, next = "resumed") {
    const elements = new Map();
    const get = id => {
        if (id === "f202-triage-data") return { textContent: JSON.stringify(data) };
        if (!elements.has(id)) {
            elements.set(id, {
                textContent: "INIT",
                attrs: new Map(),
                setAttribute(name, value) { this.attrs.set(name, value); }
            });
        }
        return elements.get(id);
    };
    get("triage-view-state");
    let failure = null;
    try {
        vm.runInNewContext(
            verifier + "\ndrawTriageStage();selectTriageStage(" + JSON.stringify(next) + ");",
            { get }, { timeout: 1200, filename: "f202-triage-adversarial.js" },
        );
    } catch (error) {
        failure = error;
    }
    return { elements, failure };
}

const valid = execute(clone());
assert.equal(valid.failure, null, "authentic synthetic fixture must pass");
assert.equal(valid.elements.get("triage-view-state").textContent, "REVIEW_REQUIRED");
assert.equal(valid.elements.get("triage-view-unknown").textContent, "1");
assert.equal(valid.elements.get("triage-view-unresolved").textContent, "1");
assert.equal(valid.elements.get("triage-view-completed").textContent, "1");
assert.equal(valid.elements.get("triage-stage-resumed").attrs.get("aria-pressed"), "true");

const attacks = [
    ["false operational GO", x => { x.operational_decision = "GO"; }],
    ["outer write call", x => { x.write_calls = 1; }],
    ["outer real authorization", x => { x.real_authorization = true; }],
    ["extra outer secret", x => { x.access_token = "SYNTHETIC_PRIVATE"; }],
    ["unknown contract", x => { x.contract = "SYNTHETIC_TRIAGE_V2"; }],
    ["missing stage", x => { delete x.stages.resumed; }],
    ["extra stage", x => { x.stages.new_stage = x.stages.empty; }],
    ["false GO inside stage", x => { x.stages.paused.operational_decision = "GO"; }],
    ["real auth inside stage", x => { x.stages.resumed.real_authorization = true; }],
    ["stage external call", x => { x.stages.leased.external_calls = 1; }],
    ["stage write call", x => { x.stages.uncertain.write_calls = 1; }],
    ["forged run ID", x => { x.stages.pending.run_ref = "SYNTHETIC_HIDDEN"; }],
    ["private reviewer", x => { x.stages.resumed.review.reviewer = "PRIVATE"; }],
    ["counter string injection", x => { x.stages.pending.queue.pending = "<img src=x>"; }],
    ["counter boolean", x => { x.stages.empty.queue.pending = false; }],
    ["negative count", x => { x.stages.pending.queue.pending = -1; }],
    ["fake success", x => { x.stages.resumed.queue.indeterminate = 0; }],
    ["pretend resolved", x => { x.stages.resumed.review.reviewed_unresolved = 0; }],
    ["wrong admission state", x => { x.stages.paused.admission.state = "OPEN"; }],
    ["wrong triage state", x => { x.stages.leased.state = "READY"; }],
    ["unsafe next step", x => { x.stages.pending.next_step = "RUN_WORKER"; }],
    ["unrecognized review tally", x => { x.stages.uncertain.review.conflict = 1; }],
    ["extra queue field", x => { x.stages.paused.queue.user = 1; }],
    ["missing flag", x => { delete x.stages.empty.write_calls; }],
];
for (const [label, mutate] of attacks) {
    const state = clone();
    mutate(state);
    const out = execute(state);
    assert(out.failure, label + " must fail closed");
    assert.match(String(out.failure.message), /F202_/, label + " error must be generic");
    assert.equal(out.elements.get("triage-view-state").textContent, "INIT",
                 label + " cannot render unverified data");
}
console.log("F202_TRIAGE_ADVERSARIAL_PASS attacks=" + attacks.length +
            " operational_decision=NO_GO");
