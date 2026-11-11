/* F198: adversarial, network-free execution of the production F196 JS contract. */
"use strict";
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");
const assert = require("node:assert/strict");

const html = fs.readFileSync(
  path.resolve(__dirname, "../../examples/f108-laboratorio-navegavel.html"), "utf8");
const marker = '<script type="application/json" id="f196-transition-data">';
const start = html.indexOf(marker);
assert(start >= 0, "F196 fixture exists");
const rest = html.slice(start + marker.length);
const jsonText = rest.split("</script>", 1)[0];
const fixture = JSON.parse(jsonText);
const jsStart = html.indexOf("// F196: a self-contained, strictly validated view");
const jsEnd = html.indexOf("// F202: view-only replay of the six-stage PHP F201 assessment.", jsStart);
assert(jsStart >= 0 && jsEnd > jsStart, "real browser verifier source located");
const productionVerifier = html.slice(jsStart, jsEnd);
assert(productionVerifier.includes("validateTransitionSnapshot"), "real validation reused");
assert(!/(?:fetch|XMLHttpRequest|WebSocket|localStorage)\s*\(/.test(productionVerifier),
       "no external/persistent calls");

const clone = () => JSON.parse(JSON.stringify(fixture));
function execute(payload, desiredState = "resumed") {
  const elements = new Map();
  const get = id => {
    if (id === "f196-transition-data") {
      return { textContent: JSON.stringify(payload) };
    }
    if (!elements.has(id)) {
      elements.set(id, {
        textContent: "INIT", attributes: new Map(),
        setAttribute(name, value) { this.attributes.set(name, value); }
      });
    }
    return elements.get(id);
  };
  get("queue-transition-state");
  let failure = null;
  try {
    const command = productionVerifier + "\ndrawTransition();\nselectTransition(" +
      JSON.stringify(desiredState) + ");";
    vm.runInNewContext(command, { get }, { timeout: 1200 });
  } catch (error) {
    failure = error;
  }
  return { elements, failure };
}
const good = execute(clone());
assert.equal(good.failure, null, "canonical fixture accepted");
assert.equal(good.elements.get("queue-transition-state").textContent,
             "RETOMADA · SIMULADA");
assert.equal(good.elements.get("queue-transition-unknown").textContent, "1");
assert.equal(good.elements.get("queue-delta-unknown").textContent, "0");
assert.equal(good.elements.get("queue-transition-resumed").attributes.get("aria-pressed"), "true");

const attacks = [
  ["unexpected outer key", x => { x.admin = true; }],
  ["false GO envelope", x => { x.operational_decision = "GO"; }],
  ["unknown contract", x => { x.contract = "UNKNOWN"; }],
  ["extra reviewer identity", x => { x.paused.review.reviewer = "private"; }],
  ["second review as resolved", x => { x.resumed.review.reviewed_unresolved = 0; }],
  ["unknown result silently removed", x => { x.resumed.queue.indeterminate = 0; }],
  ["HTML injected into pending count", x => { x.paused.queue.pending = "<script>alert(1)</script>"; }],
  ["boolean coerced as integer", x => { x.paused.queue.pending = true; }],
  ["unauthorized state", x => { x.paused.admission.state = "RUNNING"; }],
  ["another owner reference", x => { x.resumed.tenant_id = "SYNTHETIC_FOREIGN"; }],
  ["extra field in queue", x => { x.resumed.queue.secret = "SYNTHETIC_SECRET"; }],
  ["unexpected write", x => { x.paused.write_calls = 1; }],
  ["real authority asserted", x => { x.resumed.real_authorization = true; }],
  ["unknown provider calls", x => { x.external_calls = 1; }],
  ["review conflicts spoofed", x => { x.paused.review.conflict = 1; }],
  ["missing snapshot", x => { delete x.resumed; }],
];
for (const [label, attack] of attacks) {
  const bad = clone();
  attack(bad);
  const output = execute(bad);
  assert(output.failure, label + " must be rejected");
  assert.match(String(output.failure.message), /F196_/, label + " fail closed");
  assert.equal(output.elements.get("queue-transition-state").textContent,
               "INIT", label + " cannot render unverified state");
}
console.log("F196_ADVERSARIAL_PASS cases=" + attacks.length +
            " operational_decision=NO_GO");
