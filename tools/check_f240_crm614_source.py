#!/usr/bin/env python3
"""F240: pinned private CRM F248 source conformance; no Laravel execution."""
from pathlib import Path
import sys

ROOT=Path(__file__).resolve().parents[1]
CRM=ROOT/"_crm_f248_fixture"
PIN="f12d57aa0057387729d6dba89987a94975b67478"

def check():
    expected={
        "app/Support/Synthetic/AgentsSignedIngressCandidate.php":[
            "CRM_AIA_F248_PARITY_LAB",
            "GITHUB_ACTIONS",
            "GITHUB_REPOSITORY",
            "APP_ENV",
            "AiaReadOnlyMockBoundary::hasCanonicalFixtureShape($body)",
            "AiaReadOnlyMockBoundary::inspectCanonicalJson('')",
            "'Cache-Control' => 'private, no-store, max-age=0'",
            "'X-Robots-Tag' => 'noindex, nofollow, noarchive'",
            "'X-Content-Type-Options' => 'nosniff'",
            "headers->all('x-aia-claims')",
            "headers->all('x-aia-signature')",
            "base64_decode($signature, true)",
            "return $deny();",
            "404,",
        ],
        "tests/Feature/AgentsPrivateIngressCandidateTest.php":[
            "AgentsSignedIngressCandidate::inspect(",
            "assertDenied(",
            "MockServiceIdentityPreflight",
            "verifyOnce(",
            "Route::getRoutes()->count()",
        ],
        ".github/workflows/ci-crm-aia-f248-private.yml":[
            "fe79e7e9a55e4d6728ab42c2ef578e40d9bd68ba",
            "persist-credentials: false",
            "php artisan test --filter=AgentsPrivateIngressCandidateTest",
            "Verify no business route was added",
        ],
    }
    if not CRM.is_dir() or CRM.is_symlink():
        raise ValueError("F240_NO_CRM_PRIVATE_PINNED_SOURCE")
    for path,tokens in expected.items():
        source=CRM/path
        if not source.is_file() or source.is_symlink():
            raise ValueError("F240_PRIVATE_ORIGINAL_SOURCE_MISSING")
        text=source.read_text(encoding="utf-8")
        for token in tokens:
            if token not in text:
                raise ValueError("F240_PRIVATE_SOURCE_CONTRACT_DRIFT")
    web=CRM/"routes/web.php"
    if not web.is_file() or "AgentsSignedIngressCandidate" in web.read_text(encoding="utf-8"):
        raise ValueError("F240_PRIVATE_ROUTE_REGISTERED")
    api=CRM/"routes/api.php"
    if api.exists():
        raise ValueError("F240_PRIVATE_API_ROUTE_NOT_APPROVED")
    own=(ROOT/"src/MockBridge/MockSyntheticHttpDenialReceipt.php").read_text()
    for token in (
        "'private, no-store, max-age=0'",
        "'x-robots-tag'",
        "'x-content-type-options'",
        "MockCrmPublicResponseContract::denial()",
        "$status !== 404",
    ):
        if token not in own:
            raise ValueError("F240_AGENTS_F248_PROFILE_MISSING")
    print(f"F240_CRM_PRIVATE_PIN_PASS sha={PIN} source_only=true "
          "runtime_execution=false operational_decision=NO_GO")

if __name__ == "__main__":
    try:
        check()
    except (OSError, ValueError):
        print("F240_CRM_PRIVATE_PIN_DENIED operational_decision=NO_GO",file=sys.stderr)
        sys.exit(1)
