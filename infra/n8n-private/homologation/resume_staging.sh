#!/usr/bin/env bash
# Administrator-owned entry point. Start an already created, idle staging stack.
set -euo pipefail
[[ $# == 3 ]] || exit 2
project=$1
root=$2
inputs=$3
[[ $project == imagine-n8n-private-staging || $project =~ ^n8n-boot-fixture-[a-z0-9]+$ ]] || exit 2
[[ $root == /* && $inputs == /* ]] || exit 2
compose=(docker compose --project-name "$project" --project-directory "$root"
  --env-file "$inputs" -f "$root/docker-compose.yml" -f "$root/docker-compose.staging.yml"
  --profile homologation)

db_id=$("${compose[@]}" ps -aq n8n-db)
n8n_id=$("${compose[@]}" ps -aq n8n)
if [[ ! $db_id =~ ^[0-9a-f]{64}$ || ! $n8n_id =~ ^[0-9a-f]{64}$ ]]; then
  printf '%s\n' 'STAGING_RESUME_DENIED existing_containers_required' >&2
  exit 1
fi

wait_healthy() {
  local id=$1 health
  local deadline=$((SECONDS + 180))
  while (( SECONDS < deadline )); do
    health=$(docker inspect --format '{{.State.Status}}:{{.State.Health.Status}}' "$id")
    case "$health" in
      running:healthy) return 0 ;;
      running:starting) sleep 2 ;;
      *) break ;;
    esac
  done
  printf '%s\n' 'STAGING_RESUME_UNHEALTHY' >&2
  return 1
}

"${compose[@]}" start n8n-db
wait_healthy "$db_id"
"${compose[@]}" start n8n
wait_healthy "$n8n_id"
