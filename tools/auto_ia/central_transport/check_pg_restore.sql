\set ON_ERROR_STOP on
-- CENTRAL-AUTO-P7: executed ONLY against restored CI database imagine_restored_ci.
DO $$
DECLARE
  rows bigint;
BEGIN
 IF current_database()<>'imagine_restored_ci' THEN
   RAISE EXCEPTION 'REFUSE_UNEXPECTED_DB_%',current_database(); END IF;
 SELECT count(*) INTO rows FROM central_auto_lab.mtls_nonce_ledger;
 IF rows<>5 THEN RAISE EXCEPTION 'RESTORE_ROW_COUNT % EXPECTED 5',rows; END IF;
 IF NOT EXISTS (
   SELECT 1 FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace
   WHERE n.nspname='central_auto_lab' AND c.relname='mtls_nonce_ledger'
     AND c.relrowsecurity AND c.relforcerowsecurity
 ) THEN RAISE EXCEPTION 'FORCE_RLS_NOT_RESTORED'; END IF;
 IF NOT has_function_privilege('ca_mtls_lab_service',
    'central_auto_lab.admit_nonce(text,text,text,text,text,text,bigint)',
    'EXECUTE') THEN
     RAISE EXCEPTION 'ADMISSION_GRANT_MISSING'; END IF;
 IF has_table_privilege('ca_mtls_lab_service',
    'central_auto_lab.mtls_nonce_ledger','SELECT')
 OR has_table_privilege('ca_mtls_lab_service',
    'central_auto_lab.mtls_nonce_ledger','INSERT')
 OR has_table_privilege('ca_mtls_lab_service',
    'central_auto_lab.mtls_nonce_ledger','UPDATE')
 THEN RAISE EXCEPTION 'RESTRICTED_SERVICE_CAN_MUTATE_LEDGER'; END IF;
 IF (SELECT count(*) FROM central_auto_lab.mtls_nonce_ledger
   WHERE nonce='SYNTHETIC_NONCE_1')<>1
 THEN RAISE EXCEPTION 'ORIGINAL_NONCE_NOT_RESTORED'; END IF;
END $$;

SET ROLE ca_mtls_lab_service;
DO $$
DECLARE
  result text;
  now_epoch bigint:=floor(extract(epoch FROM clock_timestamp()))::bigint;
BEGIN
 result:=central_auto_lab.admit_nonce(
  'portal-automation-admin','crm-automation-state',
  'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
  'SYNTHETIC_REQUEST_1','SYNTHETIC_NONCE_1',repeat('b',64),now_epoch
 );
 IF result<>'LAB_REPLAY_DENIED' THEN
   RAISE EXCEPTION 'REPLAY_ACCEPTED_AFTER_RESTORE %', result; END IF;
 result:=central_auto_lab.admit_nonce(
  'portal-automation-admin','crm-automation-state',
  'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
  'SYNTHETIC_OTHER_COMPANY','SYNTHETIC_UNTRUSTED_NONCE',
  repeat('a',64),now_epoch
 );
 IF result<>'LAB_DENY_SCOPE' THEN
   RAISE EXCEPTION 'OTHER_TENANT_ACCEPTED_AFTER_RESTORE %',result; END IF;
END $$;
RESET ROLE;

DO $$
BEGIN
 IF (SELECT count(*) FROM central_auto_lab.mtls_nonce_ledger)<>5 THEN
   RAISE EXCEPTION 'RECOVERY_TEST_ADDED_ROWS'; END IF;
END $$;
\echo CENTRAL_AUTO_P7_RESTORE_PASS rows=5 rls=forced direct_write=0 replay=denied
