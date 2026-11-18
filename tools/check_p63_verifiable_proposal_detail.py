#!/usr/bin/env python3
"""P63 functional proposal detail in the existing P62 private PG/HTTP lab.

No new execution engine, network service, human identity or workflow. The
driver reuses P62's isolated containers, read-only page and fixture helpers.
"""
from __future__ import annotations

import json
import os
from pathlib import Path
import re
import secrets
import subprocess
import sys
import tempfile

from check_p62_live_signed_review import (
    Failure, PageScan, ReviewLab, require, run,
)


class DetailScan(PageScan):
    def __init__(self):
        super().__init__()
        self.detail_states: list[str] = []
        self.detail_proposals: list[str] = []
        self.operators: list[str] = []

    def handle_starttag(self, tag, attrs):
        super().handle_starttag(tag, attrs)
        values = dict(attrs)
        for key, target in (("data-detail-state", self.detail_states),
                            ("data-detail-proposal", self.detail_proposals),
                            ("data-detail-operator", self.operators)):
            if key in values:
                target.append(values[key])


class DetailLab(ReviewLab):
    def args(self, scope: str) -> list[str]:
        return super().args(scope) + ["-e", "AIA_P63_PROPOSAL_DETAIL_LAB=true"]

    def detail(self, mode: str, payload: dict | None = None) -> dict:
        self.sequence += 1
        name = self.project + "-detail-" + str(self.sequence)
        require(run(["docker", "container", "inspect", name], "P63_NEW_PROBE",
                    check=False).returncode != 0, "P63_PREEXISTING_PROBE")
        self.extra[name] = name
        args = ["docker", "run", "--rm", "-i", "--name", name,
                "--label", "imagine.p25.owner=" + self.token] + self.args("probe")
        args += ["--entrypoint", "php", self.php_image,
                 "/repo/tools/p63-proposal-detail-probe.php", mode]
        result = run(args, "P63_PROBE", stdin=json.dumps(payload or {}).encode(),
                     timeout=40, check=False)
        self.remove_owned(name)
        if result.returncode:
            markers = re.findall(rb"^P63_PROBE_DENIED_[A-Z0-9_]+$", result.stderr, re.MULTILINE)
            raise Failure(markers[0].decode() if len(markers) == 1 else "P63_PHP_FAILED")
        value = json.loads(result.stdout)
        require(isinstance(value, dict), "P63_PROBE_JSON_OBJECT")
        return value


def detail_page(lab: DetailLab, proposal: str, state: str, operator: str | None = None,
                current: str = "available") -> str:
    status, body, headers = lab.http("?kind=all&proposal=" + proposal)
    require(status == 200, "P63_DETAIL_HTTP_OK")
    require("default-src 'none'" in headers.get("content-security-policy", "")
            and headers.get("cache-control", "").startswith("no-store"), "P63_PRIVATE_HEADERS")
    parsed = DetailScan()
    parsed.feed(body)
    require(parsed.actions == [] and parsed.remotes == [], "P63_READ_ONLY_LOCAL_PAGE")
    require(parsed.detail_states == [state] and parsed.detail_proposals == [proposal]
            and parsed.operators == ([] if operator is None else [operator]), "P63_EXACT_DETAIL")
    require(parsed.current == [current] and "Autoria sintética vinculada e verificada" in body,
            "P63_EXISTING_AUTHOR_AND_CURRENT_REVIEW")
    for forbidden in ("SYNTHETIC_P62_PRIVATE_B", "SYNTHETIC_P62_AUTHOR", "canonical_vote",
                      "signature_b64", "public_key_b64", "command_canonical", "ruleset_canonical"):
        require(forbidden not in body, "P63_NO_RAW_OR_PRIVATE_FIELDS")
    if state == "VERIFIED":
        phrase = {"gte": "maior ou igual a", "lte": "menor ou igual a", "eq": "igual a"}[operator]
        require("A regra exige um valor " + phrase + " 12" in body
                and "Insumos originais da simulação" in body
                and "10,00%" in body and "20,00%" in body
                and "3 de 3 regras geraram rascunhos" in body, "P63_READABLE_CONDITION")
    else:
        require("Entradas originais indisponíveis" in body
                and "Insumos originais da simulação" not in body, "P63_HONEST_LEGACY")
    return body


def denied_detail(lab: DetailLab, proposal: str):
    status, body, _ = lab.http("?proposal=" + proposal)
    require(status == 503 and body == "P56_READONLY_LAB_DENIED", "P63_UNIFORM_DETAIL_DENIAL")


def exercise(lab: DetailLab) -> dict:
    seed = lab.detail("seed")
    require(seed.get("schema") == "P63_SEEDED_V1", "P63_SEED")
    require(lab.fixture is not None, "P63_BASE_FIXTURE")
    proposals = seed["proposals"]
    legacy = lab.fixture["drafts"]["WAIT"]
    foreign = lab.fixture["drafts"]["PRIVATE_B"]
    lab.fresh_votes()
    domain = lab.detail("checks", {"proposals": proposals, "legacy": legacy, "foreign": foreign})
    require(domain.get("schema") == "P63_DOMAIN_VERIFIED_V1", "P63_DOMAIN")
    lab.start_server()
    lab.fresh_votes()
    before = lab.php("stats")
    for operator in ("gte", "lte", "eq"):
        detail_page(lab, proposals[operator], "VERIFIED", operator)
    detail_page(lab, legacy, "ORIGINAL_INPUTS_UNAVAILABLE")
    rejected = detail_page(lab, lab.fixture["drafts"]["REJECT"], "ORIGINAL_INPUTS_UNAVAILABLE")
    require("PARECER DE RECUSA REGISTRADO" in rejected, "P63_EXISTING_REJECTION_VISIBLE")
    denied_detail(lab, foreign)
    denied_detail(lab, "SYNTHETIC_PROPOSAL_" + "0" * 24)
    for query in ("?proposal[]=x", "?proposal=wrong", "?proposal=" + proposals["gte"] + "&tenant=B"):
        status, body, _ = lab.http(query)
        require(status == 400 and body == "P56_READONLY_LAB_DENIED", "P63_QUERY_BOUNDARY")
    require(lab.php("stats") == before, "P63_HTTP_DID_NOT_MUTATE")
    for mutation, restore in (("tamper", "restore"), ("sibling", "restore-sibling")):
        # Refresh only synthetic fixture votes, before each independent
        # family; product checks always use the real PostgreSQL clock.
        lab.fresh_votes()
        before = lab.php("stats")
        lab.detail(mutation)
        try:
            # The sibling fixture retains P52/P59 consistency. Only full
            # P49 reproduction proves it no longer belongs to this batch.
            if mutation == "sibling":
                require(lab.php("view")["decision"] == "LAB_SIGNED_REVIEW_SNAPSHOT_NO_GO",
                        "P63_SIBLING_ISOLATES_REEVALUATION")
            changed = lab.php("stats")
            denied_detail(lab, proposals["gte"])
            require(lab.detail("view", {"proposal": proposals["gte"]})["decision"] == "DENY",
                    "P63_CHANGED_INPUT_OR_BATCH_DENIED")
            require(lab.php("stats") == changed, "P63_DENIAL_DID_NOT_REPAIR")
        finally:
            lab.detail(restore)
        require(lab.php("stats") == before, "P63_FIXTURE_RESTORED")
    lab.fresh_votes()
    snapshot = lab.detail("concurrent", {"proposal": proposals["gte"]})
    require(snapshot.get("whole_old_snapshot_preserved") is True
            and snapshot.get("next_snapshot_denied") is True, "P63_ONE_SNAPSHOT")
    # Preserve P62's whole-queue UNAVAILABLE rule after reviewer revocation.
    lab.fresh_votes()
    lab._sql("src", "UPDATE aia_p57_lab.reviewers SET active=false,revision=revision+1 "
             "WHERE tenant_ref='SYNTHETIC_TENANT_A' AND reviewer_role='SECURITY'")
    revoked = lab.php("stats")
    body = detail_page(lab, proposals["gte"], "VERIFIED", "gte", "unavailable")
    require("Parecer atual indisponível" in body and "aguardando" not in body.lower(),
            "P63_REVOKED_NOT_RELABELED_AWAITING")
    require(lab.php("stats") == revoked, "P63_REVOKED_DETAIL_DID_NOT_MUTATE")
    return {
        "schema": "AIA_P63_VERIFIABLE_PROPOSAL_DETAIL_VERIFIED_V1",
        "domain": domain, "concurrent_snapshot": snapshot,
        "upgraded_legacy_batches": seed["upgraded_legacy_batches"],
        "legacy_replay_no_backfill": seed["legacy_replay_no_backfill"],
        "captured_replay_no_change": seed["captured_replay_no_change"],
        "zero_proposals_inputs_preserved": seed["zero_proposals_inputs_preserved"],
        "failed_write_left_no_batch_or_drafts": seed["failed_write_left_no_batch_or_drafts"],
        "three_distinct_conditions_http": True, "legacy_detail_explicit": True,
        "changed_inputs_and_sibling_denied": True, "revoked_current_review_preserved": True,
        "host_published_ports": 0, "real_human_identity": False,
        "human_approval_granted": False, "n8n_executions": 0, "paid_ai_calls": 0,
        "go_for_installation": False,
    }


def main() -> dict:
    require(os.environ.get("GITHUB_ACTIONS") == "true"
            and os.environ.get("GITHUB_REPOSITORY") == "rafhael100/imagine-ai-agents", "P63_CI_ONLY")
    require(run(["docker", "context", "show"], "P63_DOCKER_CONTEXT").stdout.strip() == b"default",
            "P63_LOCAL_DOCKER_CONTEXT")
    context = json.loads(run(["docker", "context", "inspect", "default"], "P63_DOCKER_ENDPOINT").stdout)
    require(context[0]["Endpoints"]["docker"]["Host"] == "unix:///var/run/docker.sock",
            "P63_LOCAL_DOCKER_SOCKET")
    with tempfile.TemporaryDirectory(prefix="aia-p63-", dir=os.environ.get("RUNNER_TEMP")) as tmp:
        lab = DetailLab("aia-p63-" + secrets.token_hex(7), Path(tmp) / "private")
        try:
            lab.prepare()
            return exercise(lab)
        finally:
            lab.cleanup()


if __name__ == "__main__":
    try:
        print(json.dumps(main(), sort_keys=True))
        print("AIA_P63_VERIFIABLE_PROPOSAL_DETAIL_PASS NO_GO")
    except (Failure, OSError, ValueError, subprocess.SubprocessError) as error:
        print(str(error) if isinstance(error, Failure) else "P63_RUNTIME_FAILURE", file=sys.stderr)
        raise SystemExit(1)
