\set ON_ERROR_STOP on
DO $$
BEGIN
 IF has_table_privilege('aia_lab_tenant_a','auto_ia_lab.event_inbox','INSERT')
 OR has_table_privilege('aia_lab_tenant_b','auto_ia_lab.event_inbox','UPDATE')
 THEN RAISE EXCEPTION 'INBOX_DIRECT_WRITE_ALLOWED'; END IF;
 IF NOT EXISTS(SELECT 1 FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace
 WHERE n.nspname='auto_ia_lab' AND c.relname='event_inbox'
 AND c.relrowsecurity AND c.relforcerowsecurity)
 THEN RAISE EXCEPTION 'INBOX_RLS_NOT_FORCED'; END IF;
END $$;
SET ROLE aia_lab_tenant_a;
DO $$
DECLARE s bigint:=floor(extract(epoch FROM clock_timestamp()))::bigint;
 r text;
BEGIN
 r:=auto_ia_lab.receive_no_ai('imagine-crm','lead.ingest',
 'SYNTHETIC_N8N_EV_01','SYNTHETIC_N8N_NONCE_01',repeat('f',64),s-10,s+120);
 IF r<>'LAB_RECEIVED_NO_DELIVERY' THEN RAISE EXCEPTION 'NEW_A %',r; END IF;
 r:=auto_ia_lab.receive_no_ai('imagine-crm','lead.ingest',
 'SYNTHETIC_N8N_EV_01','SYNTHETIC_N8N_NONCE_01',repeat('f',64),s-10,s+120);
 IF r<>'LAB_DUPLICATE_NO_DELIVERY' THEN RAISE EXCEPTION 'DUPLICATE_A %',r; END IF;
 r:=auto_ia_lab.receive_no_ai('imagine-crm','lead.ingest',
 'SYNTHETIC_N8N_EV_02','SYNTHETIC_N8N_NONCE_01',repeat('f',64),s-10,s+120);
 IF r<>'LAB_DENY_REPLAY_CONFLICT' THEN RAISE EXCEPTION 'NONCE_REUSE_A %',r; END IF;
 r:=auto_ia_lab.receive_no_ai('imagine-crm','lead.message.interpret',
 'SYNTHETIC_N8N_EV_02','SYNTHETIC_N8N_NONCE_02',repeat('f',64),s-10,s+120);
 IF r<>'LAB_DENY_AI_OR_UNKNOWN_OPERATION' THEN RAISE EXCEPTION 'AI_BYPASS_A %',r; END IF;
 r:=auto_ia_lab.receive_no_ai('imagine-crm','lead.ingest',
 'SYNTHETIC_N8N_EV_02','SYNTHETIC_N8N_NONCE_02',repeat('f',64),s-400,s-200);
 IF r<>'LAB_DENY_EXPIRED' THEN RAISE EXCEPTION 'EXPIRED_A %',r; END IF;
 IF (SELECT COUNT(*) FROM auto_ia_lab.event_inbox)<>1 THEN RAISE EXCEPTION 'INBOX_COUNT_A'; END IF;
END $$;
RESET ROLE;
SET ROLE aia_lab_tenant_b;
DO $$
DECLARE s bigint:=floor(extract(epoch FROM clock_timestamp()))::bigint;
 r text;
BEGIN
 IF (SELECT COUNT(*) FROM auto_ia_lab.event_inbox)<>0 THEN RAISE EXCEPTION 'CROSS_TENANT_INBOX'; END IF;
 r:=auto_ia_lab.receive_no_ai('imagine-crm','lead.ingest',
 'SYNTHETIC_N8N_EV_01','SYNTHETIC_N8N_NONCE_01',repeat('f',64),s-10,s+120);
 IF r<>'LAB_RECEIVED_NO_DELIVERY' THEN RAISE EXCEPTION 'SAME_NONCE_OTHER_TENANT %',r; END IF;
 IF (SELECT COUNT(*) FROM auto_ia_lab.event_inbox)<>1 THEN RAISE EXCEPTION 'INBOX_COUNT_B'; END IF;
END $$;
RESET ROLE;
DO $$
BEGIN
 IF (SELECT COUNT(*) FROM auto_ia_lab.event_inbox)<>2
 OR (SELECT COUNT(*) FROM auto_ia_lab.no_ai_ops)<>16
 THEN RAISE EXCEPTION 'INBOX_SCOPE_COUNTS'; END IF;
END $$;
\echo AUTO_IA_08_PG_INBOX_PASS
