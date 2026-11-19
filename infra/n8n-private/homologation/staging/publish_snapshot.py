#!/usr/bin/env python3
"""Publish a sealed synthetic staging capture and verify a fresh R2 read."""
import hashlib
import json
import os
import re
import subprocess
import tempfile
from datetime import datetime, timezone
from pathlib import Path


def digest(path):
    with path.open("rb") as stream:
        return hashlib.file_digest(stream, "sha256").hexdigest()


def main():
    os.umask(0o077)
    env = os.environ
    endpoint = env["STAGING_R2_EU_ENDPOINT"].rstrip("/")
    if not re.fullmatch(r"https://[a-f0-9]{32}\.eu\.r2\.cloudflarestorage\.com", endpoint):
        raise ValueError("invalid EU endpoint")
    project = env["COMPOSE_PROJECT_NAME"]
    if not re.fullmatch(r"imagine-n8n-stage-[a-f0-9]{12}", project):
        raise ValueError("invalid staging project")
    spool = Path(env["STAGING_SPOOL"])
    if spool.is_symlink() or spool.stat().st_mode & 0o777 != 0o700 or spool.stat().st_uid != os.getuid():
        raise ValueError("invalid spool permissions")
    # Separate stage-only variables. An inherited pilot AWS credential is never used.
    access = env["N8N_STAGING_R2_ACCESS_KEY_ID"]
    secret = env["N8N_STAGING_R2_SECRET_ACCESS_KEY"]
    if not access or not secret:
        raise ValueError("staging R2 credential missing")
    auth = "user = " + json.dumps(access + ":" + secret) + "\n"
    day = datetime.now(timezone.utc).date().isoformat()
    payload = spool / f"{day}.tar.gz.age"
    receipt = spool / f"{day}.r2.json"
    if payload.is_symlink() or not payload.is_file():
        raise ValueError("day's sealed capture is missing")
    size = payload.stat().st_size
    if not 0 < size <= int(env["STAGING_MAX_SNAPSHOT_BYTES"]):
        raise ValueError("sealed capture exceeds configured staging limit")
    expected = digest(payload)
    key = f"staging/daily/{project}/{payload.name}"
    if receipt.exists():
        previous = json.loads(receipt.read_text())
        if (previous.get("sha256"), previous.get("object"), previous.get("bytes")) != (expected, key, size):
            raise ValueError("local receipt differs from the capture")
        print("snapshot already published and verified")
        return

    def request(method, output, args):
        response = subprocess.run(
            ["curl", "-q", "-s", "--retry", "0", "--max-time", "180", "--proto", "=https",
             "--aws-sigv4", "aws:amz:auto:s3", "--config", "-", "-X", method,
             "-w", "%{http_code}", "-o", str(output), *args,
             f"{endpoint}/imagine-n8n-backups-testes/{key}"],
            input=auth, text=True, capture_output=True, check=False,
        )
        if response.returncode or not re.fullmatch(r"\d{3}", response.stdout):
            raise RuntimeError(f"{method} did not return a usable HTTP status")
        return response.stdout

    with tempfile.TemporaryDirectory(dir=spool) as tmp:
        tmp = Path(tmp)
        status = request("PUT", tmp / "response", [
            "--upload-file", str(payload), "-H", "If-None-Match: *",
            "-H", f"x-amz-content-sha256: {expected}", "-H", "Content-Type: application/octet-stream",
        ])
        # A previous attempt may have committed the object without a receipt.
        if status not in {"200", "201", "204", "412"}:
            raise RuntimeError(f"R2 PUT failed: HTTP {status}")
        copy = tmp / "download.age"
        if request("GET", copy, ["--max-filesize", str(size)]) != "200":
            raise RuntimeError("R2 GET did not verify the object")
        if copy.stat().st_size != size or digest(copy) != expected:
            raise RuntimeError("R2 object size or SHA-256 differs")
        report = {
            "object": key, "bytes": size, "sha256": expected,
            "captured_at": datetime.fromtimestamp(payload.stat().st_mtime, timezone.utc).isoformat(),
            "verified_at": datetime.now(timezone.utc).isoformat(), "put_http": int(status),
        }
        report_path = tmp / "receipt.json"
        report_path.write_text(json.dumps(report) + "\n")
        os.replace(report_path, receipt)
        print(json.dumps(report))


if __name__ == "__main__":
    main()
