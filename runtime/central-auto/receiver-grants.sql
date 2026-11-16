-- P19 isolated synthetic integration only. Existing AUTO_IA inbox/catalog/key-ring are reused.
\set ON_ERROR_STOP on
BEGIN;
DO $$ BEGIN IF current_database()<>'imagine_agents_ci' THEN RAISE EXCEPTION 'CI_DATABASE_ONLY'; END IF; END $$;
SET ROLE aia_lab_owner;
CREATE TABLE auto_ia_lab.service_company_grants(
 company_id uuid NOT NULL, fingerprint varchar(64) NOT NULL CHECK(fingerprint ~ '^[a-f0-9]{64}$'),
 tenant text NOT NULL CHECK(tenant ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 operation text NOT NULL CHECK(operation='lead.ingest'), active boolean NOT NULL,
 revision integer NOT NULL CHECK(revision>0), expires_at bigint NOT NULL,
 PRIMARY KEY(company_id,fingerprint,operation));
CREATE TABLE auto_ia_lab.service_event_audit(
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
 company_id uuid NOT NULL, event_ref text NOT NULL, outcome text NOT NULL,
 grant_revision integer, received_at timestamptz NOT NULL DEFAULT clock_timestamp());
ALTER TABLE auto_ia_lab.service_company_grants ENABLE ROW LEVEL SECURITY;
ALTER TABLE auto_ia_lab.service_company_grants FORCE ROW LEVEL SECURITY;
ALTER TABLE auto_ia_lab.service_event_audit ENABLE ROW LEVEL SECURITY;
ALTER TABLE auto_ia_lab.service_event_audit FORCE ROW LEVEL SECURITY;
CREATE POLICY owner_grant ON auto_ia_lab.service_company_grants TO aia_lab_owner USING(true) WITH CHECK(true);
CREATE POLICY owner_audit ON auto_ia_lab.service_event_audit TO aia_lab_owner USING(true) WITH CHECK(true);
CREATE FUNCTION auto_ia_lab.receive_company_signed_no_ai(company uuid,fp text,e jsonb) RETURNS text
LANGUAGE plpgsql SECURITY DEFINER SET search_path=pg_catalog,auto_ia_lab AS $$
DECLARE g record; key_value text; canonical text; decision text; existing record; n bigint;
BEGIN
 n:=floor(extract(epoch FROM clock_timestamp()))::bigint;
 IF current_database()<>'imagine_agents_ci' OR (SELECT count(*) FROM jsonb_object_keys(e))<>14
 OR e->>'schema'<>'AUTO_IA_S2S_V1' OR e->>'issuer'<>'imagine-crm' OR e->>'audience'<>'imagine-ai-agents'
 OR e->>'product'<>'imagine-crm' OR e->>'operation'<>'lead.ingest'
 OR jsonb_typeof(e->'issued_at')<>'number' OR jsonb_typeof(e->'expires_at')<>'number'
 OR (e->>'issued_at')::bigint<n-120 OR (e->>'issued_at')::bigint>n+5
 OR (e->>'expires_at')::bigint<=n OR (e->>'expires_at')::bigint<=(e->>'issued_at')::bigint
 OR (e->>'expires_at')::bigint-(e->>'issued_at')::bigint>300
 OR fp !~ '^[a-f0-9]{64}$' THEN RETURN 'LAB_DENY_ENVELOPE'; END IF;
 SELECT * INTO g FROM auto_ia_lab.service_company_grants WHERE company_id=company AND fingerprint=fp AND operation=e->>'operation' FOR SHARE;
 IF NOT FOUND OR NOT g.active OR g.expires_at<=n OR g.tenant<>e->>'tenant_ref' THEN
 INSERT INTO auto_ia_lab.service_event_audit(company_id,event_ref,outcome) VALUES(company,COALESCE(e->>'event_ref','INVALID'),'LAB_DENY_GRANT');
 RETURN 'LAB_DENY_GRANT'; END IF;
 IF NOT EXISTS(SELECT 1 FROM auto_ia_lab.no_ai_ops WHERE product=e->>'product' AND operation=e->>'operation') THEN RETURN 'LAB_DENY_OPERATION'; END IF;
 IF e->>'event_ref' !~ '^SYNTHETIC_[A-Z0-9_]{1,48}$' OR e->>'nonce_ref' !~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'
 OR e->>'request_ref' !~ '^SYNTHETIC_[A-Z0-9_]{1,48}$' OR e->>'key_id' !~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'
 OR e->>'payload_hash' !~ '^[a-f0-9]{64}$' OR e->>'signature' !~ '^[a-f0-9]{64}$' THEN RETURN 'LAB_DENY_SCHEMA'; END IF;
 SELECT secret INTO key_value FROM auto_ia_lab.mock_s2s_keys WHERE issuer=e->>'issuer' AND key_id=e->>'key_id' AND enabled=true FOR SHARE;
 IF NOT FOUND THEN RETURN 'LAB_DENY_KEY'; END IF;
 canonical:=concat_ws(E'\n',e->>'schema',e->>'issuer',e->>'audience',e->>'product',e->>'operation',e->>'tenant_ref',e->>'event_ref',e->>'nonce_ref',e->>'request_ref',e->>'issued_at',e->>'expires_at',e->>'payload_hash',e->>'key_id');
 IF encode(public.hmac(canonical,key_value,'sha256'),'hex')<>e->>'signature' THEN RETURN 'LAB_DENY_MAC'; END IF;
 -- Same durable inbox, unique event/nonce constraints and operation catalog as AUTO-IA-08/10.
 INSERT INTO auto_ia_lab.event_inbox(tenant,product,operation,event_ref,nonce_ref,payload_hash,issued_epoch,expires_epoch)
 VALUES(g.tenant,e->>'product',e->>'operation',e->>'event_ref',e->>'nonce_ref',e->>'payload_hash',(e->>'issued_at')::bigint,(e->>'expires_at')::bigint) ON CONFLICT DO NOTHING;
 IF FOUND THEN decision:='LAB_RECEIVED_NO_DELIVERY'; ELSE
 SELECT * INTO existing FROM auto_ia_lab.event_inbox WHERE tenant=g.tenant AND product=e->>'product' AND (event_ref=e->>'event_ref' OR nonce_ref=e->>'nonce_ref');
 IF FOUND AND existing.event_ref=e->>'event_ref' AND existing.nonce_ref=e->>'nonce_ref' AND existing.operation=e->>'operation'
 AND existing.payload_hash=e->>'payload_hash' AND existing.issued_epoch=(e->>'issued_at')::bigint AND existing.expires_epoch=(e->>'expires_at')::bigint
 THEN decision:='LAB_DUPLICATE_NO_DELIVERY'; ELSE decision:='LAB_DENY_REPLAY_CONFLICT'; END IF;
 END IF;
 IF g.expires_at<=floor(extract(epoch FROM clock_timestamp()))::bigint THEN RAISE EXCEPTION 'GRANT_EXPIRED_DURING_ADMISSION'; END IF;
 INSERT INTO auto_ia_lab.service_event_audit(company_id,event_ref,outcome,grant_revision) VALUES(company,e->>'event_ref',decision,g.revision);
 RETURN decision;
END $$;
RESET ROLE;
CREATE ROLE aia_private_transport NOLOGIN NOSUPERUSER NOBYPASSRLS NOCREATEROLE NOCREATEDB;
REVOKE ALL ON FUNCTION auto_ia_lab.receive_company_signed_no_ai(uuid,text,jsonb) FROM PUBLIC;
GRANT USAGE ON SCHEMA auto_ia_lab TO aia_private_transport;
GRANT EXECUTE ON FUNCTION auto_ia_lab.receive_company_signed_no_ai(uuid,text,jsonb) TO aia_private_transport;
COMMIT;
