#!/usr/bin/env python3
"""AUTO-IA-03: offline synthetic durable budget lab. NOT permission to invoke AI.

SQLite here is a reproducible concurrency laboratory, not the production data store.
No external clients, provider SDK, paid calls, real credits or PII.
"""
from __future__ import annotations

import hashlib
import json
import os
import re
import sqlite3
from pathlib import Path

REF = re.compile(r"\ASYNTHETIC_[A-Z0-9_]{1,48}\Z")
DIGEST = re.compile(r"\A[a-f0-9]{64}\Z")
# Deliberately closed: parallel copy of the eight AI candidates in AUTO-IA-01.
# The test asserts exact parity against the PHP route matrix.
AI_CANDIDATES = {
    ("imagine-portal", "support.message.interpret"),
    ("imagine-crm", "lead.message.interpret"),
    ("imagine-sites", "site.copy.propose"),
    ("imagine-comercial", "campaign.copy.propose"),
    ("imagine-performance-ai", "ad.copy.propose"),
    ("imagine-universidade", "tutor.question.interpret"),
    ("imagine-seo-engine", "meta.copy.propose"),
    ("imagine-ai-agents", "document.summary.propose"),
}
SCHEMA = """
CREATE TABLE IF NOT EXISTS policies(
 tenant TEXT NOT NULL, product TEXT NOT NULL, policy TEXT NOT NULL,
 budget_minor INTEGER NOT NULL CHECK(budget_minor >= 0),
 token_limit INTEGER NOT NULL CHECK(token_limit >= 0),
 enabled INTEGER NOT NULL CHECK(enabled IN (0,1)),
 PRIMARY KEY(tenant, product, policy)
);
CREATE TABLE IF NOT EXISTS requests(
 tenant TEXT NOT NULL, product TEXT NOT NULL, event_ref TEXT NOT NULL,
 request_key TEXT NOT NULL, operation TEXT NOT NULL, policy TEXT NOT NULL,
 payload_digest TEXT NOT NULL,
 estimate_tokens INTEGER NOT NULL CHECK(estimate_tokens > 0),
 estimate_minor INTEGER NOT NULL CHECK(estimate_minor > 0),
 status TEXT NOT NULL CHECK(status IN ('RESERVED','UNKNOWN','SETTLED')),
 observed_tokens INTEGER, observed_minor INTEGER,
 proof_ref TEXT, proof_digest TEXT,
 PRIMARY KEY(tenant,product,event_ref),
 UNIQUE(tenant,product,request_key),
 FOREIGN KEY(tenant,product,policy) REFERENCES policies(tenant,product,policy),
 CHECK ((status='SETTLED' AND observed_tokens IS NOT NULL AND observed_minor IS NOT NULL
          AND proof_ref IS NOT NULL AND proof_digest IS NOT NULL)
       OR (status <> 'SETTLED' AND observed_tokens IS NULL AND observed_minor IS NULL
           AND proof_ref IS NULL AND proof_digest IS NULL))
);
CREATE INDEX IF NOT EXISTS idx_policy_exposure ON requests(tenant,product,policy,status);
"""

class LabDenied(Exception):
    """A denial with closed error code and no sensitive payload."""
    def __init__(self, code: str):
        self.code = code
        super().__init__(code)


def ref(value: str) -> str:
    if type(value) is not str or REF.fullmatch(value) is None:
        raise LabDenied("INVALID_SYNTHETIC_REFERENCE")
    return value


def natural(value: int, *, nonzero: bool = False) -> int:
    if type(value) is not int or not (int(nonzero) <= value <= 100_000_000):
        raise LabDenied("INVALID_INTEGER_AMOUNT")
    return value


def digest(value: str) -> str:
    if type(value) is not str or DIGEST.fullmatch(value) is None:
        raise LabDenied("INVALID_DIGEST")
    return value


def fake_proof_digest(tenant: str, product: str, event: str, request_key: str,
                      tokens: int, cost_minor: int, proof_ref: str) -> str:
    """Canonical *unsigned* test fixture. Never evidence of actual provider usage."""
    body = [tenant, product, event, request_key, tokens, cost_minor, proof_ref]
    return hashlib.sha256(json.dumps(body, separators=(",", ":")).encode()).hexdigest()


class DurableSyntheticLedger:
    def __init__(self, filename: str):
        path = Path(filename)
        if not path.is_absolute() or path.is_symlink():
            raise LabDenied("LOCAL_ABSOLUTE_DB_REQUIRED")
        if not path.exists():
            fd = os.open(str(path), os.O_CREAT | os.O_EXCL | os.O_WRONLY, 0o600)
            os.close(fd)
        if path.is_symlink():
            raise LabDenied("SYMLINK_FORBIDDEN")
        self.db = sqlite3.connect(str(path), timeout=15, isolation_level=None)
        self.db.row_factory = sqlite3.Row
        self.db.execute("PRAGMA foreign_keys=ON")
        self.db.execute("PRAGMA busy_timeout=15000")
        self.db.execute("PRAGMA synchronous=FULL")
        self.db.executescript(SCHEMA)

    def close(self) -> None:
        self.db.close()

    def _begin(self) -> None:
        self.db.execute("BEGIN IMMEDIATE")

    def _commit(self) -> None:
        self.db.execute("COMMIT")

    def _rollback(self) -> None:
        if self.db.in_transaction:
            self.db.execute("ROLLBACK")

    def add_policy(self, tenant: str, product: str, policy: str,
                   budget_minor: int, token_limit: int) -> None:
        ref(tenant)
        ref(policy)
        if product not in {p for p, _ in AI_CANDIDATES}:
            raise LabDenied("PRODUCT_UNKNOWN")
        natural(budget_minor)
        natural(token_limit)
        try:
            self._begin()
            self.db.execute(
                "INSERT INTO policies VALUES (?,?,?,?,?,1)",
                (tenant, product, policy, budget_minor, token_limit),
            )
            self._commit()
        except sqlite3.IntegrityError:
            self._rollback()
            raise LabDenied("POLICY_EXISTS") from None
        except BaseException:
            self._rollback()
            raise

    def disable_policy(self, tenant: str, product: str, policy: str) -> None:
        try:
            self._begin()
            row = self.db.execute(
                "UPDATE policies SET enabled=0 WHERE tenant=? AND product=? AND policy=?",
                (ref(tenant), product, ref(policy)),
            )
            if row.rowcount != 1:
                raise LabDenied("POLICY_UNKNOWN")
            self._commit()
        except BaseException:
            self._rollback()
            raise

    def exposure(self, tenant: str, product: str, policy: str) -> dict:
        row = self.db.execute(
            """SELECT
             COALESCE(SUM(CASE WHEN status='SETTLED' THEN observed_minor ELSE estimate_minor END),0) AS minor,
             COALESCE(SUM(CASE WHEN status='SETTLED' THEN observed_tokens ELSE estimate_tokens END),0) AS tokens,
             COALESCE(SUM(CASE WHEN status='UNKNOWN' THEN 1 ELSE 0 END),0) AS unknown,
             COUNT(*) AS events
             FROM requests WHERE tenant=? AND product=? AND policy=?""",
            (ref(tenant), product, ref(policy)),
        ).fetchone()
        return dict(row)

    def reserve(self, *, tenant: str, product: str, operation: str, event: str,
                request_key: str, policy: str, payload_digest: str,
                estimated_tokens: int, estimated_minor: int) -> str:
        ref(tenant); ref(event); ref(request_key); ref(policy); digest(payload_digest)
        natural(estimated_tokens, nonzero=True)
        natural(estimated_minor, nonzero=True)
        if (product, operation) not in AI_CANDIDATES:
            raise LabDenied("NO_AI_OR_UNRECOGNIZED_OPERATION")
        try:
            self._begin()
            existing = self.db.execute(
                "SELECT * FROM requests WHERE tenant=? AND product=? AND (event_ref=? OR request_key=?)",
                (tenant, product, event, request_key),
            ).fetchall()
            if existing:
                if len(existing) == 1 and all([
                    existing[0]["event_ref"] == event,
                    existing[0]["request_key"] == request_key,
                    existing[0]["operation"] == operation,
                    existing[0]["policy"] == policy,
                    existing[0]["payload_digest"] == payload_digest,
                    existing[0]["estimate_tokens"] == estimated_tokens,
                    existing[0]["estimate_minor"] == estimated_minor,
                ]):
                    self._commit()
                    return "LAB_IDEMPOTENT_REPLAY_NO_AUTHORIZATION"
                raise LabDenied("REQUEST_IDENTITY_CONFLICT")
            row = self.db.execute(
                "SELECT * FROM policies WHERE tenant=? AND product=? AND policy=?",
                (tenant, product, policy),
            ).fetchone()
            if row is None or row["enabled"] != 1:
                raise LabDenied("POLICY_NOT_ACTIVE")
            amount = self.exposure(tenant, product, policy)
            if amount["unknown"] != 0:
                raise LabDenied("UNKNOWN_USAGE_FREEZES_POLICY")
            if (amount["minor"] + estimated_minor > row["budget_minor"]
                or amount["tokens"] + estimated_tokens > row["token_limit"]):
                raise LabDenied("BUDGET_EXCEEDED")
            self.db.execute(
                """INSERT INTO requests
                  (tenant,product,event_ref,request_key,operation,policy,payload_digest,
                   estimate_tokens,estimate_minor,status)
                   VALUES (?,?,?,?,?,?,?,?,?,'RESERVED')""",
                (tenant, product, event, request_key, operation, policy,
                 payload_digest, estimated_tokens, estimated_minor),
            )
            self._commit()
            return "LAB_RESERVED_NO_AUTHORIZATION"
        except BaseException:
            self._rollback()
            raise

    def mark_unknown(self, tenant: str, product: str, event: str,
                     request_key: str) -> str:
        try:
            self._begin()
            row = self._request(tenant, product, event, request_key)
            if row["status"] == "SETTLED":
                raise LabDenied("ALREADY_SETTLED")
            if row["status"] == "UNKNOWN":
                self._commit()
                return "LAB_UNKNOWN_REPLAY"
            self.db.execute(
                "UPDATE requests SET status='UNKNOWN' WHERE tenant=? AND product=? AND event_ref=?",
                (tenant, product, event),
            )
            self._commit()
            return "LAB_UNKNOWN_HELD_FOR_REVIEW"
        except BaseException:
            self._rollback()
            raise

    def observe(self, *, tenant: str, product: str, event: str, request_key: str,
                proof_ref: str, proof_digest: str, observed_tokens: int,
                observed_minor: int) -> str:
        ref(proof_ref); digest(proof_digest)
        natural(observed_tokens)
        natural(observed_minor)
        if proof_digest != fake_proof_digest(
            tenant, product, event, request_key, observed_tokens, observed_minor, proof_ref
        ):
            raise LabDenied("SYNTHETIC_PROOF_MISMATCH")
        try:
            self._begin()
            row = self._request(tenant, product, event, request_key)
            if row["status"] == "SETTLED":
                if (row["proof_ref"] == proof_ref and row["proof_digest"] == proof_digest
                    and row["observed_tokens"] == observed_tokens
                    and row["observed_minor"] == observed_minor):
                    self._commit()
                    return "LAB_OBSERVATION_IDEMPOTENT"
                raise LabDenied("CONFLICTING_OBSERVATION")
            self.db.execute(
                """UPDATE requests SET status='SETTLED', observed_tokens=?,
                   observed_minor=?, proof_ref=?, proof_digest=?
                   WHERE tenant=? AND product=? AND event_ref=?""",
                (observed_tokens, observed_minor, proof_ref, proof_digest,
                 tenant, product, event),
            )
            self._commit()
            return "LAB_OBSERVED_UNSIGNED_FIXTURE_ONLY"
        except BaseException:
            self._rollback()
            raise

    def _request(self, tenant: str, product: str, event: str,
                 request_key: str) -> sqlite3.Row:
        row = self.db.execute(
            "SELECT * FROM requests WHERE tenant=? AND product=? AND event_ref=?",
            (ref(tenant), product, ref(event)),
        ).fetchone()
        if row is None or row["request_key"] != ref(request_key):
            raise LabDenied("REQUEST_NOT_FOUND_OR_SCOPE_DENIED")
        return row

    def status(self, tenant: str, product: str, event: str, request_key: str) -> str:
        return str(self._request(tenant, product, event, request_key)["status"])
