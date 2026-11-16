#!/usr/bin/env python3
"""AUTO-IA-04: synthetic accounting projection, never billing or credit debit.

The quoting ledger is disjoint from technical token/provider cost evidence.
No currency conversion, provider API, checkout, credit balance or invoice.
"""
from __future__ import annotations

import sqlite3
from durable_synthetic_ledger import (
    DurableSyntheticLedger, LabDenied, ref, natural,
)

QUOTES = """
CREATE TABLE IF NOT EXISTS credit_quotes(
 tenant TEXT NOT NULL, product TEXT NOT NULL, event_ref TEXT NOT NULL,
 quote_ref TEXT NOT NULL, request_key TEXT NOT NULL,
 quoted_credits INTEGER NOT NULL CHECK(quoted_credits >= 0),
 PRIMARY KEY(tenant,product,event_ref),
 UNIQUE(tenant,product,quote_ref),
 FOREIGN KEY(tenant,product,event_ref) REFERENCES requests(tenant,product,event_ref)
);
"""


class SyntheticReconciliation:
    def __init__(self, ledger: DurableSyntheticLedger):
        self.ledger = ledger
        self.db = ledger.db
        self.db.executescript(QUOTES)

    def quote(self, *, tenant: str, product: str, event: str,
              request_key: str, quote_ref: str, proposed_credits: int) -> str:
        """A separate *illustrative* commercial quote, NEVER a customer charge."""
        ref(tenant); ref(event); ref(request_key); ref(quote_ref)
        natural(proposed_credits)
        try:
            self.ledger._begin()
            self.ledger._request(tenant, product, event, request_key)
            existing = self.db.execute(
                """SELECT * FROM credit_quotes
                   WHERE tenant=? AND product=? AND (event_ref=? OR quote_ref=?)""",
                (tenant, product, event, quote_ref),
            ).fetchall()
            if existing:
                if (len(existing) == 1
                    and existing[0]["event_ref"] == event
                    and existing[0]["quote_ref"] == quote_ref
                    and existing[0]["request_key"] == request_key
                    and existing[0]["quoted_credits"] == proposed_credits):
                    self.ledger._commit()
                    return "LAB_QUOTE_IDEMPOTENT"
                raise LabDenied("CREDIT_QUOTE_CONFLICT")
            self.db.execute(
                """INSERT INTO credit_quotes
                  (tenant,product,event_ref,quote_ref,request_key,quoted_credits)
                  VALUES (?,?,?,?,?,?)""",
                (tenant, product, event, quote_ref, request_key, proposed_credits),
            )
            self.ledger._commit()
            return "LAB_QUOTE_ONLY_NOT_A_DEBIT"
        except BaseException:
            self.ledger._rollback()
            raise

    def report(self, *, tenant: str, product: str,
               event: str, request_key: str) -> dict:
        """Closed DTO; never infers credits from provider tokens or cost.

        Currency minor-unit estimates in this lab are generic, not invoice values.
        """
        request = self.ledger._request(tenant, product, event, request_key)
        quote = self.db.execute(
            """SELECT quoted_credits FROM credit_quotes
               WHERE tenant=? AND product=? AND event_ref=? AND request_key=?""",
            (tenant, product, event, request_key),
        ).fetchone()
        state = request["status"]
        observed_tokens = request["observed_tokens"] if state == "SETTLED" else None
        observed_minor = request["observed_minor"] if state == "SETTLED" else None
        if state == "UNKNOWN":
            decision = "REVIEW_REQUIRED_UNCERTAIN_SPEND"
        elif state == "RESERVED":
            decision = "AWAITING_SYNTHETIC_PROVIDER_OBSERVATION"
        else:
            decision = "REVIEW_REQUIRED_CREDIT_POLICY"
        estimate_tokens = request["estimate_tokens"]
        estimate_minor = request["estimate_minor"]
        return {
            "schema": "AUTO_IA_RECONCILIATION_LAB_V1",
            "state": state,
            "decision": decision,
            "estimated_provider_tokens": estimate_tokens,
            "observed_provider_tokens": observed_tokens,
            "estimated_provider_cost_minor": estimate_minor,
            "observed_provider_cost_minor": observed_minor,
            "technical_exposure_tokens": observed_tokens if state == "SETTLED" else estimate_tokens,
            "technical_exposure_minor": observed_minor if state == "SETTLED" else estimate_minor,
            "over_estimate_tokens": (
                max(0, observed_tokens - estimate_tokens) if observed_tokens is not None else None
            ),
            "over_estimate_minor": (
                max(0, observed_minor - estimate_minor) if observed_minor is not None else None
            ),
            "commercial_credits_proposed": quote["quoted_credits"] if quote else None,
            "commercial_credits_debited": 0,
            "actual_provider_bill_minor": None,
            "ai_provider_calls": 0,
            "live_billing_authorized": False,
        }
