#!/usr/bin/env bash
# READ-ONLY VPS discovery. No login, network connection, install, start, stop or config edits.
set -euo pipefail
echo "AUTO_IA_11_HOST_READ_ONLY_PREFLIGHT — NOT A GO-LIVE APPROVAL"
echo "UTC: $(date -u +%Y-%m-%dT%H:%M:%SZ)"
uname -srm
if command -v docker >/dev/null 2>&1; then
  docker --version
  docker compose version
  echo "Docker containers (names + state only):"
  docker ps -a --format '{{.Names}} {{.Status}}'
else
  echo "BLOCKED: docker CLI not found"
fi
echo "Disk free:"
df -hP / | tail -n 1
echo "Memory summary:"
if command -v free >/dev/null 2>&1; then free -h | head -n 2; fi
echo "Listening loopback port 17990 (potential conflict; no changes):"
if command -v ss >/dev/null 2>&1; then ss -ltn '( sport = :17990 )' | head -n 8; fi
echo "NO secrets, env vars, container inspect or mounts printed; no changes made."
echo "NO_GO until external AppSec/DBA/licensing/backup/restore/HTTPS approvals."
