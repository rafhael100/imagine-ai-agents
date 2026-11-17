-- P27 CI candidate only. A read grant is separate from lead.ingest authority.
-- No login/password or operational permission is created by this installation.
\set ON_ERROR_STOP on
BEGIN;
DO $$ BEGIN
 IF current_database()<>'imagine_agents_ci' THEN
  RAISE EXCEPTION 'P27_DISPOSABLE_DATABASE_ONLY';
 END IF;
END $$;
SET ROLE aia_lab_owner;
CREATE TABLE auto_ia_lab.receipt_read_grants (
 company_id uuid NOT NULL,
 fingerprint varchar(64) NOT NULL CHECK(fingerprint ~ '^[a-f0-9]{64}$'),
 tenant text NOT NULL CHECK(tenant ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 operation text NOT NULL CHECK(operation='event.receipt.read'),
 active boolean NOT NULL,
 revision integer NOT NULL CHECK(revision BETWEEN 1 AND 1000000),
 expires_at bigint NOT NULL,
 PRIMARY KEY(company_id,fingerprint,operation),
 FOREIGN KEY(company_id,tenant) REFERENCES auto_ia_lab.company_links(company_id,tenant)
);
ALTER TABLE auto_ia_lab.receipt_read_grants ENABLE ROW LEVEL SECURITY;
ALTER TABLE auto_ia_lab.receipt_read_grants FORCE ROW LEVEL SECURITY;
CREATE POLICY owner_receipt_read_grant ON auto_ia_lab.receipt_read_grants
 TO aia_lab_owner USING(true) WITH CHECK(true);

CREATE FUNCTION auto_ia_lab.read_company_event_receipt(company uuid,fp text,q jsonb)
RETURNS jsonb LANGUAGE plpgsql SECURITY DEFINER
SET search_path=pg_catalog,auto_ia_lab AS $$
DECLARE
 expected_keys text[]:=ARRAY['schema','issuer','audience','product','operation',
  'company_id','tenant_ref','event_ref','payload_hash','request_ref','nonce_ref',
  'issued_at','expires_at','key_id','signature'];
 field text; n bigint; issued bigint; expires bigint;
 r auto_ia_lab.receipt_read_grants%ROWTYPE;
 g auto_ia_lab.service_company_grants%ROWTYPE;
 e auto_ia_lab.event_inbox%ROWTYPE;
 key_value text; canonical text; expected_mac bytea; supplied_mac bytea;
 mismatch integer:=0; i integer;
BEGIN
 -- All ordinary denials return SQL NULL: callers cannot distinguish existence,
 -- grant state, key state, scope mismatch or a wrong event hash.
 IF current_database()<>'imagine_agents_ci' OR company IS NULL OR fp IS NULL
 OR fp !~ '^[a-f0-9]{64}$' OR jsonb_typeof(q) IS DISTINCT FROM 'object'
 THEN RETURN NULL; END IF;
 IF NOT(q ?& expected_keys) OR (SELECT count(*) FROM jsonb_object_keys(q))<>15
 THEN RETURN NULL; END IF;
 FOREACH field IN ARRAY expected_keys LOOP
  IF field IN ('issued_at','expires_at') THEN
   IF jsonb_typeof(q->field) IS DISTINCT FROM 'number'
   OR q->>field !~ '^(0|[1-9][0-9]{0,9})$' THEN RETURN NULL; END IF;
  ELSIF jsonb_typeof(q->field) IS DISTINCT FROM 'string' THEN RETURN NULL;
  END IF;
 END LOOP;
 IF q->>'schema'<>'AUTO_IA_RECEIPT_QUERY_V1'
 OR q->>'issuer'<>'imagine-crm' OR q->>'audience'<>'imagine-ai-agents'
 OR q->>'product'<>'imagine-crm' OR q->>'operation'<>'event.receipt.read'
 OR q->>'company_id'<>company::text
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

 SELECT * INTO r FROM auto_ia_lab.receipt_read_grants
 WHERE company_id=company AND fingerprint=fp AND operation='event.receipt.read'
 FOR SHARE;
 IF NOT FOUND OR NOT r.active OR r.expires_at<=n OR r.tenant<>q->>'tenant_ref'
 THEN RETURN NULL; END IF;
 SELECT * INTO g FROM auto_ia_lab.service_company_grants
 WHERE company_id=company AND fingerprint=fp AND operation='lead.ingest'
 FOR SHARE;
 IF NOT FOUND OR NOT g.active OR g.expires_at<=n OR g.tenant<>r.tenant
 THEN RETURN NULL; END IF;
 PERFORM 1 FROM auto_ia_lab.company_links
 WHERE company_id=company AND tenant=r.tenant AND active FOR SHARE;
 IF NOT FOUND THEN RETURN NULL; END IF;
 SELECT secret INTO key_value FROM auto_ia_lab.mock_s2s_keys
 WHERE issuer=q->>'issuer' AND key_id=q->>'key_id' AND enabled FOR SHARE;
 IF NOT FOUND THEN RETURN NULL; END IF;
 canonical:=concat_ws(E'\n','IMAGINE_AGENTS_EVENT_RECEIPT_QUERY_V1',
  q->>'schema',q->>'issuer',q->>'audience',q->>'product',q->>'operation',
  q->>'company_id',q->>'tenant_ref',q->>'event_ref',q->>'payload_hash',
  q->>'request_ref',q->>'nonce_ref',q->>'issued_at',q->>'expires_at',q->>'key_id');
 expected_mac:=public.hmac(canonical,key_value,'sha256');
 supplied_mac:=decode(q->>'signature','hex');
 FOR i IN 0..31 LOOP
  mismatch:=mismatch | (get_byte(expected_mac,i) # get_byte(supplied_mac,i));
 END LOOP;
 IF mismatch<>0 THEN RETURN NULL; END IF;

 SELECT * INTO e FROM auto_ia_lab.event_inbox
 WHERE tenant=r.tenant AND product='imagine-crm' AND operation='lead.ingest'
 AND event_ref=q->>'event_ref' AND payload_hash=q->>'payload_hash' FOR SHARE;
 IF NOT FOUND THEN RETURN NULL; END IF;
 -- Require the existing P19 admission record for this company. Generic inbox
 -- events or the independent P26 outbox cannot be presented as P19 receipts.
 PERFORM 1 FROM auto_ia_lab.service_event_audit
 WHERE company_id=company AND event_ref=e.event_ref
 AND outcome='LAB_RECEIVED_NO_DELIVERY';
 IF NOT FOUND THEN RETURN NULL; END IF;
 n:=floor(extract(epoch FROM clock_timestamp()))::bigint;
 IF issued<n-30 OR issued>n+5 OR expires<=n
 OR r.expires_at<=n OR g.expires_at<=n THEN RETURN NULL; END IF;
 RETURN jsonb_build_object('tenant_ref',e.tenant,'event_ref',e.event_ref,
  'payload_hash',e.payload_hash,'state','RECEIVED');
END $$;
RESET ROLE;
CREATE ROLE aia_private_receipt NOLOGIN NOINHERIT NOSUPERUSER NOBYPASSRLS
 NOCREATEROLE NOCREATEDB NOREPLICATION;
REVOKE ALL ON auto_ia_lab.receipt_read_grants FROM PUBLIC;
REVOKE ALL ON FUNCTION auto_ia_lab.read_company_event_receipt(uuid,text,jsonb) FROM PUBLIC;
GRANT USAGE ON SCHEMA auto_ia_lab TO aia_private_receipt;
GRANT EXECUTE ON FUNCTION auto_ia_lab.read_company_event_receipt(uuid,text,jsonb)
 TO aia_private_receipt;
COMMIT;
