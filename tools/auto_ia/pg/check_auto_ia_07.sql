\set ON_ERROR_STOP on
-- Test DB roles and FORCE RLS, not actual production IAM.
DO $$
BEGIN
 IF NOT EXISTS(SELECT 1 FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace
 WHERE n.nspname='auto_ia_lab' AND c.relname='requests' AND c.relrowsecurity AND c.relforcerowsecurity)
 THEN RAISE EXCEPTION 'FORCE_RLS_NOT_SET'; END IF;
 IF has_table_privilege('aia_lab_tenant_a','auto_ia_lab.requests','INSERT')
 OR has_table_privilege('aia_lab_tenant_a','auto_ia_lab.requests','UPDATE')
 OR has_table_privilege('aia_lab_tenant_a','auto_ia_lab.budgets','UPDATE')
 THEN RAISE EXCEPTION 'TENANT_DIRECT_WRITE_ALLOWED'; END IF;
END $$;
SET ROLE aia_lab_tenant_a;
DO $$
DECLARE r text;
BEGIN
 IF (SELECT COUNT(*) FROM auto_ia_lab.budgets)<>2 THEN RAISE EXCEPTION 'TENANT_A_RLS'; END IF;
 r:=auto_ia_lab.reserve('imagine-crm','lead.message.interpret','SYNTHETIC_EVENT_01',
 'SYNTHETIC_REQUEST_01',repeat('a',64),10,12);
 IF r<>'LAB_RESERVED_NO_AUTHORIZATION' THEN RAISE EXCEPTION 'FIRST_RESERVE %',r; END IF;
 r:=auto_ia_lab.reserve('imagine-crm','lead.message.interpret','SYNTHETIC_EVENT_01',
 'SYNTHETIC_REQUEST_01',repeat('a',64),10,12);
 IF r<>'LAB_IDEMPOTENT_NO_AUTHORIZATION' THEN RAISE EXCEPTION 'IDEMPOTENCY %',r; END IF;
 r:=auto_ia_lab.reserve('imagine-crm','lead.message.interpret','SYNTHETIC_EVENT_01',
 'SYNTHETIC_REQUEST_01',repeat('b',64),10,12);
 IF r<>'LAB_DENY_REPLAY_CONFLICT' THEN RAISE EXCEPTION 'REPLAY %',r; END IF;
 r:=auto_ia_lab.reserve('imagine-crm','lead.ingest','SYNTHETIC_EVENT_02',
 'SYNTHETIC_REQUEST_02',repeat('a',64),1,1);
 IF r<>'LAB_DENY_OPERATION' THEN RAISE EXCEPTION 'NO_AI_OPERATION %',r; END IF;
 r:=auto_ia_lab.reserve('imagine-crm','lead.message.interpret','SYNTHETIC_EVENT_03',
 'SYNTHETIC_REQUEST_03',repeat('a',64),30,9);
 IF r<>'LAB_DENY_BUDGET' THEN RAISE EXCEPTION 'BUDGET %',r; END IF;
 IF (SELECT COUNT(*) FROM auto_ia_lab.requests)<>1 THEN RAISE EXCEPTION 'DOUBLE_USAGE'; END IF;
END $$;
RESET ROLE;
SET ROLE aia_lab_tenant_b;
DO $$
DECLARE r text;
BEGIN
 IF (SELECT COUNT(*) FROM auto_ia_lab.budgets)<>1
 OR (SELECT COUNT(*) FROM auto_ia_lab.requests)<>0
 THEN RAISE EXCEPTION 'CROSS_TENANT_READ'; END IF;
 r:=auto_ia_lab.mark_unknown('imagine-crm','SYNTHETIC_EVENT_01','SYNTHETIC_REQUEST_01');
 IF r<>'LAB_DENY_SCOPE_OR_MISSING' THEN RAISE EXCEPTION 'CROSS_TENANT_WRITE %',r; END IF;
 r:=auto_ia_lab.reserve('imagine-crm','lead.message.interpret','SYNTHETIC_EVENT_01',
 'SYNTHETIC_REQUEST_01',repeat('a',64),10,12);
 IF r<>'LAB_RESERVED_NO_AUTHORIZATION' THEN RAISE EXCEPTION 'B_INDEPENDENT %',r; END IF;
 r:=auto_ia_lab.mark_unknown('imagine-crm','SYNTHETIC_EVENT_01','SYNTHETIC_REQUEST_01');
 IF r<>'LAB_UNKNOWN_HELD_NO_RETRY' THEN RAISE EXCEPTION 'B_UNKNOWN %',r; END IF;
 r:=auto_ia_lab.reserve('imagine-crm','lead.message.interpret','SYNTHETIC_EVENT_02',
 'SYNTHETIC_REQUEST_02',repeat('a',64),1,1);
 IF r<>'LAB_DENY_USAGE_UNCERTAIN' THEN RAISE EXCEPTION 'UNKNOWN_FREEZE %',r; END IF;
END $$;
RESET ROLE;
DO $$
BEGIN
 IF (SELECT COUNT(*) FROM auto_ia_lab.requests)<>2 OR
    (SELECT COUNT(*) FROM auto_ia_lab.allowed_ops)<>8 THEN
    RAISE EXCEPTION 'INVALID_LAB_COUNTS'; END IF;
END $$;
\echo AUTO_IA_07_RLS_PASS
