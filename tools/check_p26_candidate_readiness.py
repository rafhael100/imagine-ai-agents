#!/usr/bin/env python3
"""P26 CI-only conservative release evidence. A green CI is NOT deployment approval."""
from __future__ import annotations

import json
from pathlib import Path

root = Path(__file__).resolve().parents[1]
required = {
    "P13_cross_source": ".github/workflows/ci-p13-reciprocal-autoia.yml",
    "P14_fail_closed_policy": "tools/check_p14_convergence_gates.py",
    "P15_tenant_policy": "tools/check_p15_policy_bound_ingress.php",
    "P16_durable_replay": "tools/check_p16_pg_replay.php",
    "P17_atomic_outbox": "tools/check_p17_atomic_staging.php",
    "P18_crash_race": "tools/check_p18_crash_race.php",
    "P19_rls": "tools/check_p19_rls_isolation.php",
    "P20_noai_workflow": "tools/check_p20_noai_workflow_guard.cjs",
    "P20_routes": "tools/check_p20_routes.php",
    "P21_consolidated_readiness": "tools/check_p21_candidate_readiness.py",
    "P22_db_identity": "tools/check_p22_db_identity.php",
    "P23_staging_audit": "tools/labs/PgAtomicStagingAuditLab.php",
    "P24_rls_writers": "tools/check_p24_rls_writers.php",
    "P25_binding_audit": "docs/reviews/AIA-P25-REQUEST-NONCE-AUDIT.md",
}
missing = [name for name, path in required.items() if not (root / path).is_file()]
if missing:
    raise SystemExit("P26_FAIL_MISSING_CANDIDATE_EVIDENCE " + ",".join(missing))

report = {
    "schema": "AIA_P26_CI_REVIEW_PACKET_V1",
    "source": "synthetic-only pinned cross-line candidate",
    "candidate_checks_present": sorted(required),
    "candidate_checks_count": len(required),
    "production_decision": "NO_GO",
    "safe_to_merge_main": False,
    "authorized_to_deploy": False,
    "n8n_installed_by_ci": False,
    "real_crm_or_portal_connected": False,
    "real_s2s_iam_verified": False,
    "real_tenant_authorization_verified": False,
    "operational_key_rotation_verified": False,
    "customer_data_used": False,
    "ai_provider_called": False,
    "real_credits_debited": False,
    "external_human_review_completed": False,
    "live_restore_and_rollback_tested": False,
    "open_gates": [
        "independent security review of stacked branches and provenance",
        "operational tenant-bound IAM and owner-product authorization",
        "S2S credentials, revocation and secure transport",
        "n8n license, private installation and backup/restore",
        "CRM/Portal integration acceptance, safe rollback and monitoring",
    ],
}
assert report["production_decision"] == "NO_GO"
assert all(not report[k] for k in (
    "safe_to_merge_main", "authorized_to_deploy", "ai_provider_called",
    "real_credits_debited", "external_human_review_completed"
))
print(json.dumps(report, ensure_ascii=False, sort_keys=True))
