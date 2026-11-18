#!/usr/bin/env python3
"""P40: strict cross-branch safety preflight before real loopback mTLS lab.

No merge of CRM, Portal or Central-Auto source. All inputs are immutable
read-only checkouts, and CI remains NO_GO regardless of a green result.
"""
from __future__ import annotations

import json
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
P7=ROOT/"_central_auto_p7_pinned"
CRM=P7/"_crm_contract_pin"
PORTAL=P7/"_portal_contract_pin"

def expect(path:Path, needles:tuple[str,...])->None:
    if not path.is_file() or path.is_symlink():
        raise RuntimeError("P40_MISSING_OR_UNTRUSTED_FILE:"+str(path.relative_to(ROOT)))
    contents=path.read_text(encoding="utf-8")
    for needle in needles:
        if needle not in contents:
            raise RuntimeError("P40_CONTRACT_CHANGED:"+needle)

def main()->None:
    for directory in [P7,CRM,PORTAL]:
        if not directory.is_dir() or not (directory/".git").exists():
            raise RuntimeError("P40_PINNED_SOURCE_MISSING:"+str(directory))
    expect(P7/"tools/auto_ia/central_transport/mtls_crm_lab_server.py",(
        "CENTRAL_AUTO_MTLS_REQUEST_V1",
        "ThreadingHTTPServer",
        "127.0.0.1",
        "LAB_DENY_STORAGE",
        "CLIENT_CERT_CN",
    ))
    expect(P7/"tools/auto_ia/central_transport/run_loopback_mtls.sh",(
        "https://localhost:18787/private/automation-state",
        "CENTRAL_AUTO_P6_PG_NONCE=true",
        "mktemp -d",
    ))
    expect(P7/"tools/auto_ia/central_transport/001_pg_durable_nonce.sql",(
        "CREATE SCHEMA central_auto_lab",
        "FORCE ROW LEVEL SECURITY",
        "CREATE FUNCTION central_auto_lab.admit_nonce",
        "LAB_REPLAY_DENIED",
    ))
    expect(ROOT/"tools/labs/PgBoundOutboxAckLab.php",(
        "SIGNED_RECEIPT_VERIFICATION_DENIED",
        "SIGNED_RECEIPT_PAYLOAD_HASH_MISMATCH",
        "INSERT INTO aia_p38_lab.signed_ack_evidence",
    ))
    expect(ROOT/"tools/labs/PgSignedEvidenceAuditLab.php",(
        "REPEATABLE READ, READ ONLY",
        "ACKED_WITHOUT_SIGNED_EVIDENCE",
        "LAB_HISTORICAL_EVIDENCE_CONSISTENT_NO_GO",
    ))
    expect(ROOT/"tools/labs/PgSyntheticSignerRegistryLab.php",(
        "SELECT public_key_b64,key_fingerprint,active",
        "FOR SHARE",
    ))
    assert not (ROOT/"_central_auto_p7_pinned"/"main-production-credentials").exists()
    print(json.dumps({
        "schema":"AIA_P40_PINNED_CROSSREPO_REVIEW_V1",
        "crm_pin":"9afa6683e6b3463f12bd80a25f455f925828c0af",
        "portal_pin":"9d3877dfaa81524faf2c08f80244b485968316ec",
        "central_auto_pin":"7bd4b85a9621f518274800d9c0eb248abfbfff3b",
        "separate_contracts":[
            "CENTRAL_AUTO_MTLS_REQUEST_V1",
            "AIA_N8N_ACK_RECEIPT_V2",
        ],
        "source_merged":False,
        "host_port":"127.0.0.1:18787_CI_ONLY",
        "real_identity":False,
        "n8n_provider_calls":0,
        "production_decision":"NO_GO",
    },sort_keys=True))
if __name__=="__main__":
    main()
