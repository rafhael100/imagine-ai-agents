#!/usr/bin/env python3
"""F224: verify *both* SHA-pinned sources agree on a reviewed synthetic baseline.

Offline and read-only. An immutable reciprocal source check, NOT a deployment
or operational CRM/API security approval. This runs AFTER CRM checkout.
"""
from __future__ import annotations

import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parents[1]
AGENTS_WORKFLOW = ROOT / ".github/workflows/r01-dev-integrity.yml"
CRM_ROOT = ROOT / "_synthetic_crm_fixture"
CRM_WORKFLOW = CRM_ROOT / ".github/workflows/ci-crm-aia-synthetic-parity.yml"
CRM_CHECK = CRM_ROOT / "scripts/ci/check-aia-op01-json-exchange.php"
CRM_OWNER = CRM_ROOT / "app/Support/Synthetic/AiaReadOnlyMockBoundary.php"
CRM_HMAC = CRM_ROOT / "app/Support/Synthetic/AiaHmacFixturePreflight.php"
CRM_NONCES = CRM_ROOT / "app/Support/Synthetic/AiaFixtureNonceLedger.php"
CRM_PG_NONCE = CRM_ROOT / "app/Support/Synthetic/AiaPgNonceCandidateLedger.php"
CRM_PG_BRIDGE = CRM_ROOT / "app/Support/Synthetic/AiaPgHmacLabBridge.php"
CRM_PG_TEST = CRM_ROOT / "tests/Feature/AiaPostgresAtomicNonceCandidateTest.php"

PINNED_CRM = "d7c7aacc766c7ade4c65dcd211ed4569442144a9"
REVIEWED_AGENTS_BASE = "5a2c8af18448aeebfe8eda0d7570f34ffbe24611"


class ReciprocalPinError(Exception):
    pass


def check() -> None:
    try:
        agents = AGENTS_WORKFLOW.read_text(encoding="utf-8")
        crm = CRM_WORKFLOW.read_text(encoding="utf-8")
        check_script = CRM_CHECK.read_text(encoding="utf-8")
        owner = CRM_OWNER.read_text(encoding="utf-8")
        hmac = CRM_HMAC.read_text(encoding="utf-8")
        nonces = CRM_NONCES.read_text(encoding="utf-8")
        pg_nonce = CRM_PG_NONCE.read_text(encoding="utf-8")
        pg_bridge = CRM_PG_BRIDGE.read_text(encoding="utf-8")
        pg_test = CRM_PG_TEST.read_text(encoding="utf-8")
    except (OSError, UnicodeError) as exc:
        raise ReciprocalPinError("SOURCE_UNAVAILABLE") from exc

    # Exactly one CRM source ref; equality is verified by git checkout step.
    crm_refs = re.findall(
        r"(?m)^\s+ref:\s+([0-9a-f]{40})\s*$", agents
    )
    if crm_refs != [PINNED_CRM]:
        raise ReciprocalPinError("AGENTS_CRM_PIN_MISMATCH")
    if ("repository: rafhael100/imagine-crm" not in agents
        or 'git -C _synthetic_crm_fixture rev-parse HEAD' not in agents
        or f'"{PINNED_CRM}"' not in agents):
        raise ReciprocalPinError("AGENTS_CRM_CHECKOUT_UNVERIFIED")

    # CRM independently exercises F221 from the reviewed AIA baseline.
    agent_refs = re.findall(
        r"(?m)^\s+ref:\s+([0-9a-f]{40})\s*$", crm
    )
    if agent_refs != [REVIEWED_AGENTS_BASE]:
        raise ReciprocalPinError("CRM_AGENTS_PIN_MISMATCH")
    if ("repository: rafhael100/imagine-ai-agents" not in crm
        or f'"{REVIEWED_AGENTS_BASE}"' not in crm
        or "php scripts/ci/check-aia-op01-json-exchange.php" not in crm
        or "php: ['8.3', '8.4']" not in crm):
        raise ReciprocalPinError("CRM_RECIPROCAL_CI_NOT_VERIFIED")

    for token in ("exchangeCanonicalJson(", "AiaReadOnlyMockBoundary::inspect(",
                  "AiaReadOnlyMockBoundary::hasCanonicalFixtureShape(",
                  "AiaReadOnlyMockBoundary::inspectCanonicalJson(",
                  "AgentsReadResponseInspector", "real_authorization=false",
                  "F3_AIA_F221"):
        if token not in check_script:
            raise ReciprocalPinError("CRM_RECIPROCAL_SCRIPT_DRIFT")

    # The pinned CRM source stays deny-only despite syntactic compatibility.
    if ("return self::denied();" not in owner
        or "hasCanonicalFixtureShape(string $bytes)" not in owner
        or "inspectCanonicalJson(string $bytes)" not in owner
        or "hash_equals($canonical, $bytes)" not in owner
        or "strlen($bytes) > 1024" not in owner
        or "real_authorization' => false" not in owner
        or "DENIED_OR_NOT_FOUND" not in owner):
        raise ReciprocalPinError("CRM_OWNER_BOUNDARY_DRIFT")

    # Even the exact source must remain synthetic-only and deny-only.
    if (not all(token in hmac for token in (
        "SYNTHETIC_PUBLIC_TEST_ONLY_", "hash_hmac(", "hash_equals(",
        "AiaReadOnlyMockBoundary::inspectCanonicalJson($json)",
        "AiaFixtureNonceLedger", "return false;",
    )) or not all(token in nonces for token in (
        "private array $consumed", "count($this->consumed)",
        "return false;",
    ))):
        raise ReciprocalPinError("CRM_HMAC_LAB_ONLY_DRIFT")

    # F228: the original CRM PHP candidate must remain CI-only. These
    # source checks complement -- they never replace -- runtime PostgreSQL CI.
    if (not all(marker in pg_nonce for marker in (
        "get" + "env('GITHUB_ACTIONS')", "imagine_crm_ci",
        "ON CONFLICT (issuer,audience,nonce) DO NOTHING",
        "getDatabaseName()", "getConfig('host')", "return false;",
    )) or not all(marker in pg_bridge for marker in (
        "AiaHmacFixturePreflight::matchesFixture(",
        "$ledger->claim(", "AiaReadOnlyMockBoundary::inspectCanonicalJson($canonicalBody)",
        "return false;",
    )) or not all(marker in pg_test for marker in (
        "test_concurrent_insert_only_one_commits_across_two_live_sessions",
        "test_concurrent_insert_can_recover_after_first_transaction_rolls_back",
        "test_signed_mock_proof_claims_once_across_two_pg_connections",
        "test_invalid_mock_signature_never_claims_pg_nonce",
        "test_signed_lab_proof_fails_closed_when_pg_backend_table_absent",
    ))):
        raise ReciprocalPinError("CRM_PG_LAB_ONLY_SOURCE_DRIFT")

    if ("contents: read" not in agents or "contents: read" not in crm
        or agents.count("persist-credentials: false") != 2
        or crm.count("persist-credentials: false") != 2):
        raise ReciprocalPinError("CROSS_REPO_ACCESS_NOT_READ_ONLY")


def main() -> int:
    try:
        check()
    except ReciprocalPinError:
        print("F224_RECIPROCAL_SHA_DENIED operational_decision=NO_GO",
              file=sys.stderr)
        return 1
    print("F224_RECIPROCAL_SHA_PASS sources=2 operational_decision=NO_GO "
          "real_authorization=false external_calls=0")
    return 0


if __name__ == "__main__":
    sys.exit(main())
