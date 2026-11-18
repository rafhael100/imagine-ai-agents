-- Separate, read-only execution observation. Does not change P27 receipts.
\set ON_ERROR_STOP on
BEGIN;
DO $$ BEGIN
 IF current_database()<>'imagine_agents_ci' THEN
  RAISE EXCEPTION 'EXECUTION_STATUS_DISPOSABLE_DATABASE_ONLY';
 END IF;
END $$;
-- The existing non-login definer can only SELECT the execution domain.
-- No membership, write privilege or runtime execution grant is added.
GRANT USAGE ON SCHEMA aia_lead_ingest_lab,aia_p16_lab,aia_p26_native,
 aia_p37_lab,aia_p38_lab,aia_p44_lab TO aia_lab_owner;
GRANT SELECT ON aia_lead_ingest_lab.captured_job_sources,
 aia_p16_lab.event_replay,public.aia_lab_n8n_outbox_p6,
 aia_p26_native.executions,aia_p37_lab.executor_keys,
 aia_p38_lab.signed_ack_evidence,aia_p44_lab.consumed_owner_grants,
 aia_p44_lab.owner_keys TO aia_lab_owner;
DO $$ BEGIN
 IF to_regclass('aia_p42_lab.consumed_transport_proofs') IS NOT NULL THEN
  GRANT USAGE ON SCHEMA aia_p42_lab TO aia_lab_owner;
  GRANT SELECT ON aia_p42_lab.consumed_transport_proofs TO aia_lab_owner;
 END IF;
END $$;
SET ROLE aia_lab_owner;
CREATE TABLE auto_ia_lab.execution_read_grants(
 company_id uuid NOT NULL,
 fingerprint text NOT NULL CHECK(fingerprint ~ '^[a-f0-9]{64}$'),
 tenant text NOT NULL CHECK(tenant ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 operation text NOT NULL CHECK(operation='event.execution.read'),
 active boolean NOT NULL,
 revision integer NOT NULL CHECK(revision BETWEEN 1 AND 1000000),
 expires_at bigint NOT NULL,
 PRIMARY KEY(company_id,fingerprint,operation),
 FOREIGN KEY(company_id,tenant) REFERENCES auto_ia_lab.company_links(company_id,tenant)
);
ALTER TABLE auto_ia_lab.execution_read_grants ENABLE ROW LEVEL SECURITY;
ALTER TABLE auto_ia_lab.execution_read_grants FORCE ROW LEVEL SECURITY;
CREATE POLICY owner_execution_read_grant ON auto_ia_lab.execution_read_grants
 TO aia_lab_owner USING(true) WITH CHECK(true);

CREATE FUNCTION auto_ia_lab.read_company_execution_snapshot(company uuid,fp text,q jsonb)
RETURNS jsonb LANGUAGE plpgsql SECURITY DEFINER
SET search_path=pg_catalog,auto_ia_lab AS $$
DECLARE
 expected text[]:=ARRAY['schema','issuer','audience','product','operation',
  'company_id','tenant_ref','event_ref','payload_hash','request_ref','nonce_ref',
  'issued_at','expires_at','key_id','signature'];
 field text; n bigint; issued bigint; expires bigint;
 r auto_ia_lab.execution_read_grants%ROWTYPE;
 key_value text; canonical text; wanted bytea; supplied bytea;
 mismatch integer:=0; mac_index integer; snapshot jsonb; legacy boolean:=false;
BEGIN
 -- The endpoint starts one repeatable-read transaction before this function.
 -- Current authorization rows remain locked through validation and signing.
 IF current_database()<>'imagine_agents_ci'
 OR current_setting('transaction_isolation')<>'repeatable read'
 OR company IS NULL OR fp IS NULL OR fp !~ '^[a-f0-9]{64}$'
 OR jsonb_typeof(q) IS DISTINCT FROM 'object'
 THEN RETURN NULL; END IF;
 IF NOT(q ?& expected) OR (SELECT count(*) FROM jsonb_object_keys(q))<>15
 THEN RETURN NULL; END IF;
 FOREACH field IN ARRAY expected LOOP
  IF field IN ('issued_at','expires_at') THEN
   IF jsonb_typeof(q->field) IS DISTINCT FROM 'number'
   OR q->>field !~ '^(0|[1-9][0-9]{0,9})$' THEN RETURN NULL; END IF;
  ELSIF jsonb_typeof(q->field) IS DISTINCT FROM 'string' THEN RETURN NULL;
  END IF;
 END LOOP;
 IF q->>'schema'<>'AUTO_IA_EXECUTION_QUERY_V1'
 OR q->>'issuer'<>'imagine-crm' OR q->>'audience'<>'imagine-ai-agents'
 OR q->>'product'<>'imagine-crm' OR q->>'operation'<>'event.execution.read'
 OR q->>'company_id'<>company::text
 OR company::text !~ '^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$'
 OR q->>'tenant_ref' !~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'
 OR q->>'event_ref' !~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'
 OR q->>'request_ref' !~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'
 OR q->>'nonce_ref' !~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'
 OR q->>'key_id' !~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'
 OR q->>'payload_hash' !~ '^[a-f0-9]{64}$'
 OR q->>'signature' !~ '^[a-f0-9]{64}$' THEN RETURN NULL; END IF;
 n:=floor(extract(epoch FROM clock_timestamp()))::bigint;
 issued:=(q->>'issued_at')::bigint; expires:=(q->>'expires_at')::bigint;
 IF issued<n-30 OR issued>n+5 OR expires<=n OR expires<=issued
 OR expires-issued>60 OR expires>4102444800 THEN RETURN NULL; END IF;
 SELECT * INTO r FROM auto_ia_lab.execution_read_grants
 WHERE company_id=company AND fingerprint=fp AND operation='event.execution.read'
 FOR SHARE;
 IF NOT FOUND OR NOT r.active OR r.expires_at<=n OR r.tenant<>q->>'tenant_ref'
 THEN RETURN NULL; END IF;
 PERFORM 1 FROM auto_ia_lab.company_links
 WHERE company_id=company AND tenant=r.tenant AND active FOR SHARE;
 IF NOT FOUND THEN RETURN NULL; END IF;
 SELECT secret INTO key_value FROM auto_ia_lab.mock_s2s_keys
 WHERE issuer='imagine-crm' AND key_id=q->>'key_id' AND enabled FOR SHARE;
 IF NOT FOUND THEN RETURN NULL; END IF;
 canonical:=concat_ws(E'\n','IMAGINE_AGENTS_EXECUTION_QUERY_V1',
  q->>'schema',q->>'issuer',q->>'audience',q->>'product',q->>'operation',
  q->>'company_id',q->>'tenant_ref',q->>'event_ref',q->>'payload_hash',
  q->>'request_ref',q->>'nonce_ref',q->>'issued_at',q->>'expires_at',q->>'key_id');
 wanted:=public.hmac(canonical,key_value,'sha256');
 supplied:=decode(q->>'signature','hex');
 FOR mac_index IN 0..31 LOOP
  mismatch:=mismatch | (get_byte(wanted,mac_index) # get_byte(supplied,mac_index));
 END LOOP;
 IF mismatch<>0 THEN RETURN NULL; END IF;

 -- Source, queue, intent, result and ACK are one coherent snapshot. No state
 -- is repaired and no executor/owner authority is reactivated by this read.
 SELECT jsonb_build_object(
  'source',to_jsonb(c)||jsonb_build_object('payload_bytes',chr(92)||'x'||encode(c.payload_bytes,'hex')),
  'inbox',to_jsonb(i),'provenance',to_jsonb(s),
  'replay',to_jsonb(p),'outbox',to_jsonb(o),'owner_grant',to_jsonb(g),
  'owner_key',to_jsonb(k),'execution',to_jsonb(x),'executor_key',to_jsonb(ek),
  'ack_evidence',to_jsonb(a),
  'audit_received',EXISTS(SELECT 1 FROM auto_ia_lab.service_event_audit h
   WHERE h.company_id=c.company_id AND h.event_ref=c.event_ref
    AND h.outcome='LAB_RECEIVED_NO_DELIVERY' AND h.grant_revision=c.grant_revision))
 INTO snapshot
 FROM auto_ia_lab.lead_ingest_payloads c
 LEFT JOIN auto_ia_lab.event_inbox i
  ON i.tenant=c.tenant AND i.product=c.product AND i.event_ref=c.event_ref
 LEFT JOIN aia_lead_ingest_lab.captured_job_sources s
  ON s.tenant_ref=c.tenant AND s.event_ref=c.event_ref
 LEFT JOIN aia_p16_lab.event_replay p ON p.tenant_ref=c.tenant AND p.event_ref=c.event_ref
 LEFT JOIN public.aia_lab_n8n_outbox_p6 o ON o.tenant_ref=c.tenant AND o.event_ref=c.event_ref
 LEFT JOIN aia_p44_lab.consumed_owner_grants g ON g.tenant_ref=c.tenant AND g.event_ref=c.event_ref
 LEFT JOIN aia_p44_lab.owner_keys k ON k.company_id=g.company_id AND k.key_id=g.signer_key_id
 LEFT JOIN aia_p26_native.executions x ON x.tenant_ref=c.tenant AND x.event_ref=c.event_ref
 LEFT JOIN aia_p37_lab.executor_keys ek
  ON ek.tenant_ref=x.tenant_ref AND ek.key_id=(x.context_json::jsonb->>'executor_key_id')
 LEFT JOIN aia_p38_lab.signed_ack_evidence a ON a.tenant_ref=c.tenant AND a.event_ref=c.event_ref
 WHERE c.company_id=company AND c.tenant=r.tenant AND c.product='imagine-crm'
  AND c.operation='lead.ingest' AND c.event_ref=q->>'event_ref'
  AND c.payload_hash=q->>'payload_hash';
 IF NOT FOUND THEN RETURN NULL; END IF;
 IF to_regclass('aia_p42_lab.consumed_transport_proofs') IS NOT NULL THEN
  EXECUTE 'SELECT EXISTS(SELECT 1 FROM aia_p42_lab.consumed_transport_proofs '
   'WHERE tenant_ref=$1 AND event_ref=$2)' INTO legacy USING r.tenant,q->>'event_ref';
 END IF;
 n:=floor(extract(epoch FROM clock_timestamp()))::bigint;
 IF issued<n-30 OR issued>n+5 OR expires<=n OR r.expires_at<=n THEN RETURN NULL; END IF;
 RETURN snapshot||jsonb_build_object('legacy_proof',legacy,
  'auth_expires_at',least(expires,r.expires_at));
END $$;
RESET ROLE;
CREATE ROLE aia_private_execution NOLOGIN NOINHERIT NOSUPERUSER NOBYPASSRLS
 NOCREATEROLE NOCREATEDB NOREPLICATION;
REVOKE ALL ON auto_ia_lab.execution_read_grants FROM PUBLIC;
REVOKE ALL ON FUNCTION auto_ia_lab.read_company_execution_snapshot(uuid,text,jsonb) FROM PUBLIC;
GRANT USAGE ON SCHEMA auto_ia_lab TO aia_private_execution;
GRANT EXECUTE ON FUNCTION auto_ia_lab.read_company_execution_snapshot(uuid,text,jsonb)
 TO aia_private_execution;
COMMIT;
