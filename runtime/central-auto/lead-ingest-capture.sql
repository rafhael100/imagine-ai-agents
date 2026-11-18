-- Additive CI-only capture of the exact CRM_LEAD_EVENT_V1 bytes admitted by P29.
-- This is immutable ingress evidence, not another queue or execution authority.
\set ON_ERROR_STOP on
BEGIN;
DO $$ BEGIN
 IF current_database()<>'imagine_agents_ci'
 OR current_setting('server_encoding')<>'UTF8' THEN
  RAISE EXCEPTION 'LEAD_CAPTURE_DISPOSABLE_UTF8_DATABASE_ONLY';
 END IF;
END $$;
SET ROLE aia_lab_owner;

CREATE TABLE auto_ia_lab.lead_ingest_payloads (
 tenant text NOT NULL CHECK(tenant ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 product text NOT NULL CHECK(product='imagine-crm'),
 operation text NOT NULL CHECK(operation='lead.ingest'),
 event_ref text NOT NULL CHECK(event_ref ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 company_id uuid NOT NULL,
 event_id uuid NOT NULL,
 lead_id uuid NOT NULL,
 payload_bytes bytea NOT NULL CHECK(octet_length(payload_bytes) BETWEEN 2 AND 2048),
 payload_hash text NOT NULL CHECK(payload_hash ~ '^[a-f0-9]{64}$'),
 nonce_ref text NOT NULL CHECK(nonce_ref ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 request_ref text NOT NULL CHECK(request_ref ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 issued_epoch bigint NOT NULL,
 expires_epoch bigint NOT NULL,
 fingerprint text NOT NULL CHECK(fingerprint ~ '^[a-f0-9]{64}$'),
 key_id text NOT NULL CHECK(key_id ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 grant_revision integer NOT NULL CHECK(grant_revision>0),
 company_revision integer NOT NULL CHECK(company_revision>0),
 state text NOT NULL DEFAULT 'CAPTURED_NOT_DISPATCHED'
  CHECK(state='CAPTURED_NOT_DISPATCHED'),
 captured_at timestamptz NOT NULL DEFAULT clock_timestamp(),
 PRIMARY KEY(tenant,product,event_ref),
 UNIQUE(company_id,event_id),
 FOREIGN KEY(tenant,product,event_ref)
  REFERENCES auto_ia_lab.event_inbox(tenant,product,event_ref),
 FOREIGN KEY(company_id,tenant)
  REFERENCES auto_ia_lab.company_links(company_id,tenant),
 CHECK(event_ref='SYNTHETIC_'||upper(replace(event_id::text,'-',''))),
 CHECK(payload_hash=encode(public.digest(payload_bytes,'sha256'),'hex')),
 CHECK(issued_epoch>=0 AND expires_epoch>issued_epoch
  AND expires_epoch-issued_epoch<=300 AND expires_epoch<=4102444800)
);
ALTER TABLE auto_ia_lab.lead_ingest_payloads ENABLE ROW LEVEL SECURITY;
ALTER TABLE auto_ia_lab.lead_ingest_payloads FORCE ROW LEVEL SECURITY;
CREATE POLICY owner_lead_capture_read ON auto_ia_lab.lead_ingest_payloads
 FOR SELECT TO aia_lab_owner USING(true);
CREATE POLICY owner_lead_capture_insert ON auto_ia_lab.lead_ingest_payloads
 FOR INSERT TO aia_lab_owner WITH CHECK(true);

-- No UPDATE/DELETE policy exists, and TRUNCATE must not erase ingress evidence.
-- A database administrator capable of disabling triggers remains outside this
-- laboratory boundary; these controls do not claim privileged anti-rollback.
CREATE FUNCTION auto_ia_lab.reject_lead_ingest_payload_mutation()
RETURNS trigger LANGUAGE plpgsql SET search_path=pg_catalog AS $$
BEGIN
 RAISE EXCEPTION USING ERRCODE='55000',MESSAGE='LEAD_CAPTURE_IMMUTABLE';
END $$;
CREATE TRIGGER lead_ingest_payloads_no_row_mutation
 BEFORE UPDATE OR DELETE ON auto_ia_lab.lead_ingest_payloads
 FOR EACH ROW EXECUTE FUNCTION auto_ia_lab.reject_lead_ingest_payload_mutation();
CREATE TRIGGER lead_ingest_payloads_no_truncate
 BEFORE TRUNCATE ON auto_ia_lab.lead_ingest_payloads
 FOR EACH STATEMENT EXECUTE FUNCTION auto_ia_lab.reject_lead_ingest_payload_mutation();

CREATE FUNCTION auto_ia_lab.receive_company_signed_lead(
 company uuid,fp text,e jsonb,payload text
) RETURNS text LANGUAGE plpgsql SECURITY DEFINER
SET search_path=pg_catalog,auto_ia_lab AS $$
DECLARE
 input_payload ALIAS FOR $4;
 expected_keys text[]:=ARRAY['schema','issuer','audience','product','operation',
  'tenant_ref','event_ref','nonce_ref','request_ref','issued_at','expires_at',
  'payload_hash','key_id','signature'];
 field text; decoded json; payload_keys text[];
 field_count integer; distinct_count integer;
 payload_event uuid; payload_lead uuid; raw_bytes bytea; digest_hex text;
 issued bigint; expires bigint; n bigint;
 decision text; denial text:='LAB_DENY_CAPTURE';
 g auto_ia_lab.service_company_grants%ROWTYPE;
 link auto_ia_lab.company_links%ROWTYPE;
 captured auto_ia_lab.lead_ingest_payloads%ROWTYPE;
 current_grant auto_ia_lab.service_company_grants%ROWTYPE;
 current_link auto_ia_lab.company_links%ROWTYPE;
BEGIN
 IF current_database()<>'imagine_agents_ci'
 OR current_setting('server_encoding')<>'UTF8'
 OR company IS NULL OR company::text !~
  '^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$'
 OR fp IS NULL OR fp !~ '^[a-f0-9]{64}$'
 OR input_payload IS NULL OR octet_length(input_payload)>2048
 OR jsonb_typeof(e) IS DISTINCT FROM 'object'
 THEN RETURN 'LAB_DENY_PAYLOAD'; END IF;

 -- Parse as json, never jsonb, until duplicate names have been counted.
 -- json_each decodes escaped names and retains every occurrence: schema and
 -- sch\u0065ma therefore count as the same name and are rejected together.
 BEGIN
  decoded:=input_payload::json;
  IF json_typeof(decoded) IS DISTINCT FROM 'object'
  THEN RETURN 'LAB_DENY_PAYLOAD'; END IF;
  SELECT count(*),count(DISTINCT j.key),array_agg(j.key ORDER BY j.key)
   INTO field_count,distinct_count,payload_keys FROM json_each(decoded) AS j;
  IF field_count<>5 OR distinct_count<>5
  OR payload_keys IS DISTINCT FROM ARRAY['action','company_id','event_id','lead_id','schema']
  OR EXISTS(SELECT 1 FROM json_each(decoded) AS j
   WHERE json_typeof(j.value) IS DISTINCT FROM 'string')
  THEN RETURN 'LAB_DENY_PAYLOAD'; END IF;
  IF decoded->>'schema'<>'CRM_LEAD_EVENT_V1'
  OR decoded->>'action'<>'lead.created'
  OR decoded->>'company_id'<>company::text
  OR decoded->>'event_id' !~
   '^[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}$'
  OR decoded->>'lead_id' !~
   '^[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}$'
  THEN RETURN 'LAB_DENY_PAYLOAD'; END IF;
  payload_event:=(decoded->>'event_id')::uuid;
  payload_lead:=(decoded->>'lead_id')::uuid;
  raw_bytes:=convert_to(input_payload,'UTF8');
  digest_hex:=encode(public.digest(raw_bytes,'sha256'),'hex');
 EXCEPTION WHEN OTHERS THEN
  RETURN 'LAB_DENY_PAYLOAD';
 END;

 -- The HTTP parser preserves envelope duplicate rejection. This SQL boundary
 -- additionally rejects NULL, extra/missing fields and unsafe scalar types.
 IF NOT(e ?& expected_keys) OR (SELECT count(*) FROM jsonb_object_keys(e))<>14
 THEN RETURN 'LAB_DENY_ENVELOPE'; END IF;
 FOREACH field IN ARRAY expected_keys LOOP
  IF field IN ('issued_at','expires_at') THEN
   IF jsonb_typeof(e->field) IS DISTINCT FROM 'number'
   OR e->>field !~ '^(0|[1-9][0-9]{0,9})$'
   THEN RETURN 'LAB_DENY_ENVELOPE'; END IF;
  ELSIF jsonb_typeof(e->field) IS DISTINCT FROM 'string' THEN
   RETURN 'LAB_DENY_ENVELOPE';
  END IF;
 END LOOP;
 IF e->>'schema'<>'AUTO_IA_S2S_V1' OR e->>'issuer'<>'imagine-crm'
 OR e->>'audience'<>'imagine-ai-agents' OR e->>'product'<>'imagine-crm'
 OR e->>'operation'<>'lead.ingest'
 OR e->>'tenant_ref' !~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'
 OR e->>'event_ref'<>'SYNTHETIC_'||upper(replace(payload_event::text,'-',''))
 OR e->>'nonce_ref' !~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'
 OR e->>'request_ref' !~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'
 OR e->>'key_id' !~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'
 OR e->>'signature' !~ '^[a-f0-9]{64}$'
 OR e->>'payload_hash'<>digest_hex
 THEN RETURN 'LAB_DENY_PAYLOAD'; END IF;
 issued:=(e->>'issued_at')::bigint; expires:=(e->>'expires_at')::bigint;
 n:=floor(extract(epoch FROM clock_timestamp()))::bigint;
 IF issued<n-120 OR issued>n+5 OR expires<=n OR expires<=issued
 OR expires-issued>300 OR expires>4102444800
 THEN RETURN 'LAB_DENY_ENVELOPE'; END IF;

 -- This exception block is the savepoint for ALL effects of this invocation,
 -- including the existing receiver's inbox and admission/denial audit writes.
 -- Throwing on a late capture failure rolls those effects back together and
 -- never deletes a previously committed/concurrent winner's records.
 BEGIN
  decision:=auto_ia_lab.receive_company_signed_no_ai(company,fp,e);
  IF decision IS NULL OR decision NOT IN
   ('LAB_RECEIVED_NO_DELIVERY','LAB_DUPLICATE_NO_DELIVERY') THEN
   denial:=COALESCE(decision,'LAB_DENY_CAPTURE');
   RAISE EXCEPTION USING ERRCODE='P0L01',MESSAGE='LEAD_CAPTURE_REJECTED';
  END IF;
  SELECT * INTO g FROM auto_ia_lab.service_company_grants
   WHERE company_id=company AND fingerprint=fp AND operation='lead.ingest'
   FOR SHARE;
  IF NOT FOUND OR NOT g.active OR g.tenant<>e->>'tenant_ref' THEN
   denial:='LAB_DENY_GRANT';
   RAISE EXCEPTION USING ERRCODE='P0L01',MESSAGE='LEAD_CAPTURE_REJECTED';
  END IF;
  SELECT * INTO link FROM auto_ia_lab.company_links
   WHERE company_id=company AND tenant=g.tenant AND active FOR SHARE;
  IF NOT FOUND THEN
   denial:='LAB_DENY_COMPANY_LINK';
   RAISE EXCEPTION USING ERRCODE='P0L01',MESSAGE='LEAD_CAPTURE_REJECTED';
  END IF;
  PERFORM 1 FROM auto_ia_lab.event_inbox
   WHERE tenant=g.tenant AND product='imagine-crm' AND operation='lead.ingest'
   AND event_ref=e->>'event_ref' AND nonce_ref=e->>'nonce_ref'
   AND payload_hash=digest_hex AND issued_epoch=issued AND expires_epoch=expires
   FOR SHARE;
  IF NOT FOUND THEN
   denial:='LAB_DENY_PAYLOAD_CONFLICT';
   RAISE EXCEPTION USING ERRCODE='P0L01',MESSAGE='LEAD_CAPTURE_REJECTED';
  END IF;

  IF decision='LAB_RECEIVED_NO_DELIVERY' THEN
   INSERT INTO auto_ia_lab.lead_ingest_payloads
    (tenant,product,operation,event_ref,company_id,event_id,lead_id,
     payload_bytes,payload_hash,nonce_ref,request_ref,issued_epoch,expires_epoch,
     fingerprint,key_id,grant_revision,company_revision)
   VALUES(g.tenant,'imagine-crm','lead.ingest',e->>'event_ref',company,
    payload_event,payload_lead,raw_bytes,digest_hex,e->>'nonce_ref',e->>'request_ref',
    issued,expires,fp,e->>'key_id',g.revision,link.revision);
  ELSE
   -- The referenced inbox row is locked above. Capture rows are immutable,
   -- so an ordinary SELECT suffices without granting an UPDATE RLS policy
   -- merely to permit SELECT FOR SHARE on this append-only table.
   SELECT * INTO captured FROM auto_ia_lab.lead_ingest_payloads
    WHERE tenant=g.tenant AND product='imagine-crm' AND event_ref=e->>'event_ref';
   IF NOT FOUND THEN
    -- A historical inbox/receipt is not proof that these bytes were retained.
    -- Never backfill it from a later replay, even with a current valid MAC.
    denial:='LAB_DENY_LEGACY_PAYLOAD_ABSENT';
    RAISE EXCEPTION USING ERRCODE='P0L01',MESSAGE='LEAD_CAPTURE_REJECTED';
   END IF;
   IF captured.company_id IS DISTINCT FROM company
   OR captured.operation IS DISTINCT FROM 'lead.ingest'
   OR captured.event_id IS DISTINCT FROM payload_event
   OR captured.lead_id IS DISTINCT FROM payload_lead
   OR captured.payload_bytes IS DISTINCT FROM raw_bytes
   OR captured.payload_hash IS DISTINCT FROM digest_hex
   OR captured.nonce_ref IS DISTINCT FROM e->>'nonce_ref'
   OR captured.request_ref IS DISTINCT FROM e->>'request_ref'
   OR captured.issued_epoch IS DISTINCT FROM issued
   OR captured.expires_epoch IS DISTINCT FROM expires
   OR captured.state IS DISTINCT FROM 'CAPTURED_NOT_DISPATCHED' THEN
    denial:='LAB_DENY_PAYLOAD_CONFLICT';
    RAISE EXCEPTION USING ERRCODE='P0L01',MESSAGE='LEAD_CAPTURE_REJECTED';
   END IF;
   -- A current authorized certificate/key/grant may differ after rotation.
   -- It was revalidated above; the original fingerprint/key/revisions remain
   -- immutable provenance and are never rewritten or promoted to authority.
  END IF;

  -- Re-read authority after every capture insert/lock/trigger has completed.
  -- Existing receiver locks are retained, and these checks also catch a test
  -- trigger that changed authority in this same transaction. The final clock
  -- sample occurs AFTER all waits, never before payload persistence.
  SELECT * INTO current_grant FROM auto_ia_lab.service_company_grants
   WHERE company_id=company AND fingerprint=fp AND operation='lead.ingest'
   FOR SHARE;
  IF NOT FOUND OR NOT current_grant.active OR current_grant.tenant<>g.tenant
  OR current_grant.revision<>g.revision THEN
   denial:='LAB_DENY_GRANT';
   RAISE EXCEPTION USING ERRCODE='P0L01',MESSAGE='LEAD_CAPTURE_REJECTED';
  END IF;
  SELECT * INTO current_link FROM auto_ia_lab.company_links
   WHERE company_id=company AND tenant=g.tenant AND active FOR SHARE;
  IF NOT FOUND OR current_link.revision<>link.revision THEN
   denial:='LAB_DENY_COMPANY_LINK';
   RAISE EXCEPTION USING ERRCODE='P0L01',MESSAGE='LEAD_CAPTURE_REJECTED';
  END IF;
  PERFORM 1 FROM auto_ia_lab.mock_s2s_keys
   WHERE issuer=e->>'issuer' AND key_id=e->>'key_id' AND enabled FOR SHARE;
  IF NOT FOUND THEN
   denial:='LAB_DENY_KEY';
   RAISE EXCEPTION USING ERRCODE='P0L01',MESSAGE='LEAD_CAPTURE_REJECTED';
  END IF;
  n:=floor(extract(epoch FROM clock_timestamp()))::bigint;
  IF issued<n-120 OR issued>n+5 OR expires<=n OR current_grant.expires_at<=n THEN
   denial:='LAB_DENY_ENVELOPE';
   RAISE EXCEPTION USING ERRCODE='P0L01',MESSAGE='LEAD_CAPTURE_REJECTED';
  END IF;
  RETURN decision;
 EXCEPTION
  WHEN SQLSTATE 'P0L01' THEN RETURN denial;
  WHEN unique_violation THEN RETURN 'LAB_DENY_PAYLOAD_CONFLICT';
  -- All other SQL errors propagate after rollback. In particular, a timeout
  -- or failed write remains a retryable transport failure (HTTP 503), not a
  -- misleading authorization denial. Explicit late expiry above is semantic.
 END;
END $$;

RESET ROLE;
REVOKE ALL ON auto_ia_lab.lead_ingest_payloads FROM PUBLIC,aia_private_transport;
REVOKE ALL ON FUNCTION auto_ia_lab.reject_lead_ingest_payload_mutation() FROM PUBLIC;
REVOKE ALL ON FUNCTION auto_ia_lab.receive_company_signed_lead(uuid,text,jsonb,text) FROM PUBLIC;
GRANT EXECUTE ON FUNCTION auto_ia_lab.receive_company_signed_lead(uuid,text,jsonb,text)
 TO aia_private_transport;
COMMIT;
