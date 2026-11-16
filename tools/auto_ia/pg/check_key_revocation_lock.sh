#!/usr/bin/env bash
set -euo pipefail
php tools/auto_ia/pg/check_key_revocation_lock.php > /tmp/auto-key-call.sql
{
  echo 'BEGIN; SET ROLE aia_lab_tenant_a;'
  cat /tmp/auto-key-call.sql
  echo 'SELECT pg_sleep(3); COMMIT;'
} > /tmp/auto-key-hold.sql
PGAPPNAME=auto_key_hold psql -v ON_ERROR_STOP=1 -v expected=LAB_RECEIVED_NO_DELIVERY -f /tmp/auto-key-hold.sql > /tmp/auto-key-holder.log & holder=$!
ready=0
for attempt in $(seq 1 50); do
  if [[ "$(psql -Atqc "SELECT count(*) FROM pg_stat_activity WHERE application_name='auto_key_hold' AND wait_event='PgSleep'")" == 1 ]]; then ready=1; break; fi
  sleep 0.05
done
test "$ready" = 1
psql -v ON_ERROR_STOP=1 <<'SQL'
DO $$ BEGIN
 PERFORM set_config('lock_timeout','250ms',true);
 BEGIN
  UPDATE auto_ia_lab.mock_s2s_keys SET enabled=false WHERE issuer='imagine-crm' AND key_id='SYNTHETIC_KEY_A';
  RAISE EXCEPTION 'revocation did not wait for the admitted transaction';
 EXCEPTION WHEN lock_not_available THEN
  RAISE NOTICE 'KEY_VALIDITY_LOCK_PASS';
 END;
END $$;
SQL
wait "$holder"
psql -v ON_ERROR_STOP=1 -c "UPDATE auto_ia_lab.mock_s2s_keys SET enabled=false WHERE issuer='imagine-crm' AND key_id='SYNTHETIC_KEY_A'" > /dev/null
{ echo 'SET ROLE aia_lab_tenant_a;'; cat /tmp/auto-key-call.sql; } > /tmp/auto-key-replay.sql
psql -v ON_ERROR_STOP=1 -v expected=LAB_DENY_KEY -f /tmp/auto-key-replay.sql
psql -v ON_ERROR_STOP=1 <<'SQL'
DO $$ BEGIN
 IF (SELECT count(*) FROM auto_ia_lab.event_inbox WHERE event_ref='SYNTHETIC_LOCK_EVENT')<>1 THEN
 RAISE EXCEPTION 'unexpected inbox count'; END IF;
END $$;
SQL
echo 'CREDENTIAL_REVOKE_CONCURRENCY_PASS admission_before_revoke=1 replay_after_revoke=denied operational_authority=0'
