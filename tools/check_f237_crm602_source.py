#!/usr/bin/env python3
"""F237: immutable CRM F247 reciprocal *source* guard, not CRM runtime testing."""
from pathlib import Path
import sys

BASE = Path(__file__).resolve().parents[1]
CRM = BASE / "_crm_f247_fixture"
SHA = "6e4e99879e456aea19124065b105cd989bbf1274"

def check():
    required = {
        "app/Support/Synthetic/AiaPgEd25519LabBridge.php": [
            "use ImagineAI\\MockBridge\\MockServiceIdentityPreflight;",
            "new MockServiceIdentityPreflight(1)",
            "->verifyOnce(",
            "return $ledger->claim(",
            "AiaReadOnlyMockBoundary::inspectCanonicalJson(",
        ],
        "app/Support/Synthetic/AiaPgNonceCandidateLedger.php": [
            "ON CONFLICT (issuer,audience,nonce) DO NOTHING",
            "GITHUB_ACTIONS",
            "imagine_crm_ci",
            "DB_HOST",
            "RLS_TEST_USERNAME",
        ],
        "app/Support/Synthetic/AiaPgServiceScopeLab.php": [
            "FORCE", "row_security", "relforcerowsecurity",
            "crm_aia_scope_ci", "where('tenant_ref', $expectedTenant)",
        ],
        "app/Support/Synthetic/AiaStagingDeniedIngressLab.php": [
            "AIA_F246_HTTP_LAB",
            "GITHUB_REPOSITORY",
            "AiaReadOnlyMockBoundary::inspectCanonicalJson('')",
            "404,", "return $denied();", "$verifyDiagnostic(",
        ],
        "tests/Feature/AiaNoGoHttpBoundaryTest.php": [
            "test_f245_no_real_service_or_crm_lead_route_can_be_published",
            "assertNotFound()",
            "real_authorization",
        ],
        "tests/Feature/AiaPostgresEd25519NonceCandidateTest.php": [
            "AIA_ED25519_PG_LAB",
            "SYNTHETIC_TENANT_A",
            "assertDenial(",
        ],
    }
    if not CRM.is_dir():
        raise ValueError("F237_CRM_PINNED_CHECKOUT_MISSING")
    for relative, tokens in required.items():
        path = CRM / relative
        if not path.is_file() or path.is_symlink():
            raise ValueError("F237_EXPECTED_CRM_FILE_MISSING")
        text = path.read_text(encoding="utf-8")
        for token in tokens:
            if token not in text:
                raise ValueError("F237_CRM_CONTRACT_DRIFT")
    web = CRM / "routes" / "web.php"
    if not web.is_file():
        raise ValueError("F237_WEB_ROUTE_SOURCE_MISSING")
    if "AiaStagingDeniedIngressLab" in web.read_text(encoding="utf-8"):
        raise ValueError("F237_UNAPPROVED_HTTP_INGRESS")
    api = CRM / "routes" / "api.php"
    if api.is_file() and "AiaStagingDeniedIngressLab" in api.read_text(encoding="utf-8"):
        raise ValueError("F237_UNAPPROVED_HTTP_INGRESS")
    own = (BASE/"src/MockBridge/MockServiceIdentityPreflight.php").read_text()
    if "SYNTHETIC_AIA_SERVICE_ED25519_V1" not in own:
        raise ValueError("F237_AGENTS_PROOF_DRIFT")
    print(f"F237_PINNED_CRM_SOURCE_PASS commit={SHA} "
          "runtime_execution=false operational_decision=NO_GO")

if __name__ == "__main__":
    try:
        check()
    except (OSError, ValueError):
        print("F237_PINNED_CRM_SOURCE_DENIED operational_decision=NO_GO", file=sys.stderr)
        sys.exit(1)
