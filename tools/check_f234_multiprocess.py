#!/usr/bin/env python3
"""F234: real concurrency across OS processes, ONE host/temp file ONLY."""
import os
import pathlib
import secrets
import subprocess
import sys
import tempfile
from concurrent.futures import ThreadPoolExecutor

ROOT = pathlib.Path(__file__).resolve().parents[1]
WORKER = ROOT / "tools" / "f234_nonce_worker.php"


def cli(mode, path, nonce="SYNTHETIC_F234_NONCE_A", capacity=64):
    args = ["php", str(WORKER), mode, str(path)]
    if mode == "consume":
        args += [nonce, str(capacity)]
    result = subprocess.run(args, cwd=ROOT, capture_output=True, text=True, timeout=10)
    if result.returncode:
        raise AssertionError(f"F234 worker crashed: {result.returncode}")
    out = result.stdout.strip()
    assert out in ("RESERVED", "DENIED"), out
    return out


def run():
    path = pathlib.Path(tempfile.gettempdir()) / (
        "aia-f234-" + secrets.token_hex(10) + ".json"
    )
    os.umask(0o077)
    try:
        assert cli("consume", path) == "DENIED"  # no implicit initialization
        assert cli("init", path) == "RESERVED"
        assert cli("init", path) == "DENIED"     # initialization is one-time
        with ThreadPoolExecutor(max_workers=12) as pool:
            results = list(pool.map(
                lambda _: cli("consume", path), range(12)
            ))
        assert results.count("RESERVED") == 1, results
        assert results.count("DENIED") == 11, results
        assert cli("consume", path) == "DENIED"  # new worker / same nonce
        assert cli("consume", path, "SYNTHETIC_F234_NONCE_B", 2) == "RESERVED"
        assert cli("consume", path, "SYNTHETIC_F234_NONCE_C", 2) == "DENIED"
        assert cli("consume", path, "WRONG_NONCE", 2) == "DENIED"

        path.write_text('{"v":1,"used":{"invalid":"tampered"}}', encoding="utf-8")
        assert cli("consume", path, "SYNTHETIC_F234_NONCE_D") == "DENIED"
        path.write_text("", encoding="utf-8")  # interrupted write, fail closed
        assert cli("consume", path, "SYNTHETIC_F234_NONCE_E") == "DENIED"
        print("F234_MULTIPROCESS_LAB_PASS workers=12 winners=1 "
              "distributed_guarantee=false operational_decision=NO_GO")
    finally:
        path.unlink(missing_ok=True)


if __name__ == "__main__":
    try:
        run()
    except Exception as exc:
        print(f"F234_MULTIPROCESS_LAB_FAILED {type(exc).__name__}", file=sys.stderr)
        sys.exit(1)
