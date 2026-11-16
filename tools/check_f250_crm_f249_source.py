#!/usr/bin/env python3
"""F250: read-only source conformance for exact CRM F249 candidate SHA.

This only examines an immutable, public GitHub Actions checkout. It does not
execute CRM, query a database, open routes or authorize any business effect.
"""
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
CRM = ROOT / "_crm_f249_fixture"
PIN = "c67aaa0440ac4f9f1c607883d0c1347f5a067f92"


def check() -> None:
    expected = {
        "app/Support/Synthetic/AgentsSignedIngressCandidate.php": [
            "CRM_AIA_F248_PARITY_LAB",
            "AiaReadOnlyMockBoundary::inspectCanonicalJson('')",
            "return $deny();",
            "404,",
            "headers->has('authorization')",
            "headers->has('x-tenant-id')",
        ],
        "app/Support/Synthetic/AgentsPrivatePgDiagnostic.php": [
            "MockSyntheticScopePreflight::matches(",
            "MockServiceIdentityPreflight(1)",
            "return $this->ledger->claim(",
        ],
        "app/Support/Synthetic/AgentsPrivateOwnedKeyringLab.php": [
            "AIA_F245_KEYRING_LAB",
            "SYNTHETIC_CRM_API",
            "whereNull('revoked_at')",
            "public_key_base64",
        ],
        "app/Support/Synthetic/AgentsPrivateRestrictedKeyringLab.php": [
            "crm_aia_keyreader_f246_ci",
            "row_security_active(",
            "public_key_base64",
        ],
        "app/Support/Synthetic/AgentsPrivateKeyRevocationLockLab.php": [
            "crm_aia_keylock_f248_ci",
            "row_security_active(",
            "lockForUpdate()",
            "withLockedKey(",
            "can_replace_key",
            "can_revoke",
            "->transaction(",
        ],
        "tests/Feature/AgentsPrivateRestrictedKeyringTest.php": [
            "FORCE ROW LEVEL SECURITY",
            "test_f247_private_http_uses_restricted_keyring_then_pg_nonce_with_uniform_denial",
            "AiaPgNonceCandidateLedger",
            "REVOKE CONNECT ON DATABASE",
        ],
        "tests/Feature/AgentsPrivateKeyRevocationLockTest.php": [
            "SET lock_timeout",
            "FORCE ROW LEVEL SECURITY",
            "WITH CHECK (false)",
            "SYNTHETIC_F248_NONCE_FIRST",
            "DROP ROLE IF EXISTS",
        ],
        ".github/workflows/ci-crm-aia-f249-unified.yml": [
            "AIA_F245_KEYRING_LAB: 'true'",
            "AIA_F246_RLS_LAB: 'true'",
            "AIA_F247_COMPOSED_LAB: 'true'",
            "AIA_F248_ROW_LOCK_LAB: 'true'",
            "AgentsPrivateIngressCandidateTest",
            "AgentsPrivateHttpCycleTest",
            "AgentsPrivatePgNonceTest",
            "AgentsPrivateHttpPgCycleTest",
            "AgentsPrivateOwnedKeyringTest",
            "AgentsPrivateRestrictedKeyringTest",
            "AgentsPrivateKeyRevocationLockTest",
            "operational_decision=NO_GO",
        ],
    }
    if not CRM.is_dir() or CRM.is_symlink():
        raise ValueError("F250_PINNED_CRM_CHECKOUT_MISSING")
    for name, tokens in expected.items():
        target = CRM / name
        if not target.is_file() or target.is_symlink():
            raise ValueError("F250_CRM_SOURCE_MISSING")
        raw = target.read_text(encoding="utf-8")
        for token in tokens:
            if token not in raw:
                raise ValueError(f"F250_CRM_EXPECTED_BOUNDARY_DRIFT:{name}:{token}")

    web = CRM / "routes/web.php"
    if not web.is_file() or "AgentsSignedIngressCandidate" in web.read_text():
        raise ValueError("F250_UNAPPROVED_SERVICE_ROUTE")
    if (CRM / "routes/api.php").exists():
        raise ValueError("F250_UNAPPROVED_API_ROUTE")

    local = ROOT / "src/MockBridge/MockSyntheticHttpDenialReceipt.php"
    if not local.is_file():
        raise ValueError("F250_AGENTS_HTTP_DENIAL_GUARD_MISSING")
    own = local.read_text()
    for token in ("$status !== 404", "'max-age=0, no-store, private'",
                  "MockCrmPublicResponseContract::denial()"):
        if token not in own:
            raise ValueError("F250_AGENTS_DENIAL_CONTRACT_DRIFT")
    print(
        "F250_RECIPROCAL_CRM_F249_SOURCE_PASS "
        f"commit={PIN} source_only=true operational_decision=NO_GO"
    )


if __name__ == "__main__":
    try:
        check()
    except (OSError, ValueError) as error:
        print(f"F250_RECIPROCAL_SOURCE_DENIED {error} operational_decision=NO_GO", file=sys.stderr)
        sys.exit(1)
