#!/usr/bin/env python3
"""Local RPO monitor for receipts from a fresh and verified R2 read."""
import argparse
import json
from datetime import datetime, timezone
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument("spool", type=Path)
args = parser.parse_args()

try:
    captures = [
        datetime.fromisoformat(json.loads(path.read_text())["captured_at"])
        for path in args.spool.glob("*.r2.json")
    ]
    age = int((datetime.now(timezone.utc) - max(captures)).total_seconds())
except (OSError, KeyError, TypeError, ValueError):
    print("RPO_UNKNOWN: no valid verified snapshot receipt")
    raise SystemExit(2)
if age < 0 or age >= 24 * 3600:
    print(f"RPO_BREACH: age_seconds={age}")
    raise SystemExit(2)
if age >= 23 * 3600:
    print(f"RPO_WARNING: age_seconds={age}")
    raise SystemExit(1)
print(f"RPO_OK: age_seconds={age}")
