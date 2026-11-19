#!/usr/bin/env python3
"""Unpack the fixed staging envelope and apply the existing P22 volume check."""
import argparse
import hashlib
import re
import shutil
import sys
import tarfile
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from seal_backup import LIMIT, inspect_volume

COMPONENTS = {"n8n-db.dump", "n8n-data.tar", "captured-at", "n8n-key-version", "SHA256SUMS"}


def unpack(archive_path, quarantine):
    if quarantine.is_symlink() or quarantine.stat().st_mode & 0o777 != 0o700:
        raise ValueError("quarantine must be a private directory")
    with tarfile.open(archive_path, "r:gz") as archive:
        remaining = COMPONENTS.copy()
        total = 0
        for member in archive:
            total += member.size
            if not member.isfile() or member.name not in remaining or not 0 < member.size or total > LIMIT:
                raise ValueError("unexpected staging archive entry")
            remaining.remove(member.name)
            with archive.extractfile(member) as source, (quarantine / member.name).open("xb") as output:
                shutil.copyfileobj(source, output)
        if remaining:
            raise ValueError("missing staging archive entries")
    inspect_volume(quarantine / "n8n-data.tar")
    manifest = quarantine / "SHA256SUMS"
    if manifest.stat().st_size > 512:
        raise ValueError("unexpected staging manifest size")
    expected = COMPONENTS - {"SHA256SUMS"}
    for line in manifest.read_text(encoding="ascii").splitlines():
        entry = re.fullmatch(r"([0-9a-f]{64})  ([a-zA-Z0-9.-]+)", line)
        if not entry or entry[2] not in expected:
            raise ValueError("unexpected staging manifest entry")
        expected.remove(entry[2])
        with (quarantine / entry[2]).open("rb") as file:
            if hashlib.file_digest(file, "sha256").hexdigest() != entry[1]:
                raise ValueError("staging component digest mismatch")
    if expected:
        raise ValueError("incomplete staging manifest")


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("archive", type=Path)
    parser.add_argument("quarantine", type=Path)
    args = parser.parse_args()
    unpack(args.archive, args.quarantine)
