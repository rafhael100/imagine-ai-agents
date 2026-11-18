#!/usr/bin/env python3
"""Check staging additions to the existing static Compose checker."""
import json
import re
import sys

N8N_IMAGE = (
    "ghcr.io/n8n-io/n8n@sha256:"
    "b4b90747b81fcda7e76550c0d3aa7f0f64917a1e9573cff40d6834eaf2e0f8ea"
)


def verify(config):
    services = config["services"]
    if set(services) != {"n8n", "n8n-db"}:
        raise ValueError("unexpected service")
    if services["n8n"]["image"] != N8N_IMAGE:
        raise ValueError("n8n image differs from reviewed candidate")
    if not re.fullmatch(r"(?:docker\.io/library/)?postgres@sha256:[a-f0-9]{64}",
                        services["n8n-db"]["image"]):
        raise ValueError("expected PostgreSQL digest")
    mounts = {"n8n": "n8n_data", "n8n-db": "n8n_pgdata"}
    networks = {"n8n": {"n8n_apps_private", "n8n_storage"}, "n8n-db": {"n8n_storage"}}
    expected = {"driver": "local", "options": {"max-size": "10m", "max-file": "3"}}
    for name, service in services.items():
        if service.get("logging") != expected or service.get("ports"):
            raise ValueError("logging or host publication differs from staging policy")
        if set(service["networks"]) != networks[name]:
            raise ValueError("unexpected service network")
        volumes = {vol["source"] for vol in service["volumes"] if vol["type"] == "volume"}
        if volumes != {mounts[name]}:
            raise ValueError("unexpected persistent volume")
    if set(config["volumes"]) != set(mounts.values()) or any(
        vol.get("external") or vol.get("name") != f"imagine-n8n-private-staging_{key}"
        for key, vol in config["volumes"].items()
    ):
        raise ValueError("volume is not staging-owned")
    env = services["n8n"]["environment"]
    for key, value in {
        "N8N_LOG_LEVEL": "warn",
        "EXECUTIONS_DATA_SAVE_ON_ERROR": "none",
        "EXECUTIONS_DATA_SAVE_ON_SUCCESS": "none",
        "EXECUTIONS_DATA_SAVE_ON_PROGRESS": "false",
        "EXECUTIONS_DATA_SAVE_MANUAL_EXECUTIONS": "false",
    }.items():
        if env.get(key) != value:
            raise ValueError("unexpected execution retention")


if __name__ == "__main__":
    try:
        verify(json.load(sys.stdin))
    except (ValueError, KeyError, TypeError) as error:
        sys.exit(f"STAGING_CONFIG_DENIED: {error}")
    print("STAGING_CONFIG_PASS no_start=1")
