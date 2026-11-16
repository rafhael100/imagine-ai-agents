#!/usr/bin/env python3
"""AUTO-IA-04: synthetic provider estimates vs separate commercial credit quotes."""
import hashlib
import tempfile
from pathlib import Path

from durable_synthetic_ledger import (
    DurableSyntheticLedger, LabDenied, fake_proof_digest,
)
from synthetic_reconciliation import SyntheticReconciliation

TOTAL = 0

def check(ok, name):
    global TOTAL
    TOTAL += 1
    if not ok:
        raise AssertionError(name)

def denied(code, fn):
    try:
        fn()
    except LabDenied as exc:
        check(exc.code == code, "unexpected " + exc.code)
        return
    raise AssertionError("expected " + code)

def base_args(tenant="SYNTHETIC_TENANT_A", event="SYNTHETIC_EVENT_11",
              key="SYNTHETIC_KEY_11"):
    return {
        "tenant": tenant, "product": "imagine-crm",
        "operation": "lead.message.interpret", "event": event, "request_key": key,
        "policy": "SYNTHETIC_POLICY_11",
        "payload_digest": hashlib.sha256(b"fake payload").hexdigest(),
        "estimated_tokens": 15, "estimated_minor": 7
    }

def quote_args(**changes):
    a = {
        "tenant": "SYNTHETIC_TENANT_A", "product": "imagine-crm",
        "event": "SYNTHETIC_EVENT_11", "request_key": "SYNTHETIC_KEY_11",
        "quote_ref": "SYNTHETIC_QUOTE_11", "proposed_credits": 4
    }
    a.update(changes)
    return a

with tempfile.TemporaryDirectory(prefix="auto-ia-04-") as tmp:
    file = str(Path(tmp) / "ledger.sqlite")
    l = DurableSyntheticLedger(file)
    l.add_policy("SYNTHETIC_TENANT_A","imagine-crm","SYNTHETIC_POLICY_11",30,40)
    l.add_policy("SYNTHETIC_TENANT_B","imagine-crm","SYNTHETIC_POLICY_11",30,40)
    l.reserve(**base_args())
    r = SyntheticReconciliation(l)
    pending = r.report(tenant="SYNTHETIC_TENANT_A", product="imagine-crm",
                       event="SYNTHETIC_EVENT_11",request_key="SYNTHETIC_KEY_11")
    check(pending["state"]=="RESERVED" and pending["commercial_credits_proposed"] is None,
          "no invented credit quote")
    check(pending["commercial_credits_debited"]==0 and pending["ai_provider_calls"]==0,
          "no side effects")
    check(r.quote(**quote_args())=="LAB_QUOTE_ONLY_NOT_A_DEBIT", "record hypothetical credits")
    check(r.quote(**quote_args())=="LAB_QUOTE_IDEMPOTENT", "idempotent quote")
    denied("CREDIT_QUOTE_CONFLICT", lambda:r.quote(**quote_args(proposed_credits=5)))
    denied("CREDIT_QUOTE_CONFLICT", lambda:r.quote(**quote_args(
        event="SYNTHETIC_EVENT_12",request_key="SYNTHETIC_KEY_12")))
    denied("REQUEST_NOT_FOUND_OR_SCOPE_DENIED", lambda:r.quote(**quote_args(
        tenant="SYNTHETIC_TENANT_B")))
    check(r.report(tenant="SYNTHETIC_TENANT_A",product="imagine-crm",
                   event="SYNTHETIC_EVENT_11",request_key="SYNTHETIC_KEY_11")[
                   "commercial_credits_proposed"]==4, "credits separate from provider estimate")
    l.mark_unknown("SYNTHETIC_TENANT_A","imagine-crm","SYNTHETIC_EVENT_11","SYNTHETIC_KEY_11")
    uncertain = r.report(tenant="SYNTHETIC_TENANT_A",product="imagine-crm",
                         event="SYNTHETIC_EVENT_11",request_key="SYNTHETIC_KEY_11")
    check(uncertain["decision"]=="REVIEW_REQUIRED_UNCERTAIN_SPEND"
          and uncertain["observed_provider_tokens"] is None
          and uncertain["commercial_credits_debited"]==0,
          "timeouts do not mean zero cost or debit")
    denied("UNKNOWN_USAGE_FREEZES_POLICY",lambda:l.reserve(**base_args(
        event="SYNTHETIC_EVENT_12",key="SYNTHETIC_KEY_12")))
    observed = {
        "tenant":"SYNTHETIC_TENANT_A", "product":"imagine-crm",
        "event":"SYNTHETIC_EVENT_11", "request_key":"SYNTHETIC_KEY_11",
        "proof_ref":"SYNTHETIC_PROOF_11", "observed_tokens":22, "observed_minor":13,
    }
    observed["proof_digest"]=fake_proof_digest(
        observed["tenant"],observed["product"],observed["event"],
        observed["request_key"],observed["observed_tokens"],
        observed["observed_minor"],observed["proof_ref"])
    l.observe(**observed)
    report = r.report(tenant="SYNTHETIC_TENANT_A",product="imagine-crm",
                      event="SYNTHETIC_EVENT_11",request_key="SYNTHETIC_KEY_11")
    check(report["over_estimate_tokens"]==7 and report["over_estimate_minor"]==6,
          "observe actual over estimate not hidden")
    check(report["estimated_provider_tokens"]==15 and report["observed_provider_tokens"]==22,
          "tokens remain distinct")
    check(report["estimated_provider_cost_minor"]==7 and
          report["observed_provider_cost_minor"]==13, "provider cost recorded independently")
    check(report["commercial_credits_proposed"]==4 and
          report["commercial_credits_debited"]==0, "credits not calculated from cost")
    check(report["actual_provider_bill_minor"] is None
          and report["live_billing_authorized"] is False
          and report["decision"]=="REVIEW_REQUIRED_CREDIT_POLICY",
          "unsigned proof never becomes invoice")
    l.close()
    l = DurableSyntheticLedger(file)
    r = SyntheticReconciliation(l)
    again = r.report(tenant="SYNTHETIC_TENANT_A",product="imagine-crm",
                     event="SYNTHETIC_EVENT_11",request_key="SYNTHETIC_KEY_11")
    check(report==again, "restart durability and identical DTO")
    check(not any(k in report for k in ["api_key","email","customer_id","provider_token"]),
          "report excludes credentials and PII")
    denied("REQUEST_NOT_FOUND_OR_SCOPE_DENIED",lambda:r.report(
        tenant="SYNTHETIC_TENANT_B",product="imagine-crm",
        event="SYNTHETIC_EVENT_11",request_key="SYNTHETIC_KEY_11"))
    l.close()
print(f"AUTO_IA_04_PASS checks={TOTAL} no_provider=1 no_credit_debit=1 no_live_billing=1")
