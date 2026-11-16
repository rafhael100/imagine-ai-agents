\set ON_ERROR_STOP on
-- AIA-CONV-05 recovery-only verification; source DB remains untouched.
DO $$
DECLARE
  acked bigint; dead bigint; inbox_count bigint;
BEGIN
  SELECT count(*) FILTER(WHERE state='ACKED'),
         count(*) FILTER(WHERE state='DEAD')
  INTO acked,dead
  FROM public.aia_lab_n8n_outbox_p6;
  IF acked<>2 OR dead<>1 THEN
    RAISE EXCEPTION 'RESTORED_P12_STATES_INVALID acked=% dead=%',acked,dead;
  END IF;
  SELECT count(*) INTO inbox_count FROM auto_ia_lab.event_inbox;
  IF inbox_count<>2 THEN RAISE EXCEPTION 'RESTORED_INBOX_INVALID %',inbox_count; END IF;
  IF (SELECT count(*) FROM auto_ia_lab.mock_s2s_keys
      WHERE issuer='imagine-ai-agents' AND enabled=true)<>1 THEN
    RAISE EXCEPTION 'SYNTHETIC_SIGNING_KEY_NOT_RESTORED';
  END IF;
  IF (SELECT count(*) FROM auto_ia_lab.no_ai_ops)<>16 THEN
    RAISE EXCEPTION 'NO_AI_CATALOG_INCOMPLETE'; END IF;
  IF NOT EXISTS (
   SELECT 1 FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace
   WHERE n.nspname='auto_ia_lab' AND c.relname='event_inbox'
     AND c.relrowsecurity AND c.relforcerowsecurity
  ) THEN RAISE EXCEPTION 'RLS_NOT_RESTORED'; END IF;
  IF has_function_privilege('aia_lab_tenant_a',
     'auto_ia_lab.receive_no_ai(text,text,text,text,text,bigint,bigint)','EXECUTE')
  THEN RAISE EXCEPTION 'UNSIGNED_INGRESS_RESTORED_UNSAFELY'; END IF;
END $$;

SET ROLE aia_lab_tenant_a;
DO $$
DECLARE
  r auto_ia_lab.event_inbox%ROWTYPE;
  request_ref text; canonical text; mac text; result text;
BEGIN
  IF (SELECT count(*) FROM auto_ia_lab.event_inbox)<>1 THEN
    RAISE EXCEPTION 'A_CROSS_TENANT_RESTORE_READ'; END IF;
  SELECT * INTO STRICT r FROM auto_ia_lab.event_inbox
  WHERE tenant='SYNTHETIC_TENANT_A' AND event_ref='SYNTHETIC_RECOVERY_A';
  request_ref:='SYNTHETIC_REQUEST_'||
     substring(r.nonce_ref FROM char_length('SYNTHETIC_NONCE_')+1);
  canonical:=concat_ws(E'\n',
    'AUTO_IA_S2S_V1',
    'imagine-ai-agents','imagine-ai-agents','imagine-ai-agents',
    'run.status.project',
    r.tenant,r.event_ref,r.nonce_ref,request_ref,
    r.issued_epoch::text,r.expires_epoch::text,
    r.payload_hash,'SYNTHETIC_KEY_A');
  mac:=encode(public.hmac(canonical,
    'SYNTHETIC_OFFLINE_HMAC_TEST_KEY_A_32_BYTES_ONLY','sha256'),'hex');
  result:=auto_ia_lab.receive_signed_no_ai(
    'AUTO_IA_S2S_V1','imagine-ai-agents','imagine-ai-agents',
    'imagine-ai-agents','run.status.project',
    r.tenant,r.event_ref,r.nonce_ref,request_ref,
    r.issued_epoch,r.expires_epoch,r.payload_hash,'SYNTHETIC_KEY_A',mac);
  IF result<>'LAB_DUPLICATE_NO_DELIVERY' THEN
    RAISE EXCEPTION 'RESTORED_REPLAY_NOT_IDEMPOTENT got %', result;
  END IF;
END $$;
RESET ROLE;

SET ROLE aia_lab_tenant_b;
DO $$
DECLARE r text;
BEGIN
  IF (SELECT count(*) FROM auto_ia_lab.event_inbox)<>1 THEN
    RAISE EXCEPTION 'B_CROSS_TENANT_RESTORE_READ'; END IF;
  -- Wrong role and wrong signed tenant fail even before MAC lookup.
  r:=auto_ia_lab.receive_signed_no_ai(
    'AUTO_IA_S2S_V1','imagine-ai-agents','imagine-ai-agents',
    'imagine-ai-agents','run.status.project',
    'SYNTHETIC_TENANT_A','SYNTHETIC_RECOVERY_A',
    'SYNTHETIC_NONCE_WRONG','SYNTHETIC_REQUEST_WRONG',
    100,200,repeat('a',64),'SYNTHETIC_KEY_A',repeat('b',64));
  IF r<>'LAB_DENY_SERVICE_TENANT' THEN
    RAISE EXCEPTION 'CROSS_TENANT_REPLAY_ACCEPTED %',r; END IF;
END $$;
RESET ROLE;

DO $$
BEGIN
  IF (SELECT count(*) FROM auto_ia_lab.event_inbox)<>2
  OR (SELECT count(*) FROM public.aia_lab_n8n_outbox_p6)<>3
  THEN RAISE EXCEPTION 'RECOVERY_CREATED_OR_LOST_EVENTS'; END IF;
END $$;
\echo AIA_CONV05_RESTORE_PASS duplicate=blocked rls=preserved acked=2 dead=1 no_external_effect=1
