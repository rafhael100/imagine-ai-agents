-- CENTRAL-AUTO-P6: PostgreSQL 16 ONLY inside disposable GitHub Actions.
-- The credentials/companies below are synthetic and MUST NOT be used in VPS.
\set ON_ERROR_STOP on
BEGIN;
CREATE ROLE ca_mtls_lab_owner NOLOGIN NOINHERIT NOBYPASSRLS;
CREATE ROLE ca_mtls_lab_service LOGIN NOINHERIT NOBYPASSRLS
 PASSWORD 'SYNTHETIC_CENTRAL_AUTO_P6_DB_LOGIN_ONLY';
CREATE SCHEMA central_auto_lab AUTHORIZATION ca_mtls_lab_owner;
SET ROLE ca_mtls_lab_owner;

CREATE TABLE central_auto_lab.mtls_nonce_ledger(
  issuer text NOT NULL CHECK(issuer='portal-automation-admin'),
  audience text NOT NULL CHECK(audience='crm-automation-state'),
  company_id uuid NOT NULL CHECK(
   company_id='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'::uuid),
  request_id text NOT NULL CHECK(
   request_id ~ '^SYNTHETIC_[A-Z0-9_]{1,40}$'),
  nonce text NOT NULL CHECK(nonce ~ '^SYNTHETIC_[A-Z0-9_]{1,40}$'),
  request_digest char(64) NOT NULL CHECK(
   request_digest ~ '^[a-f0-9]{64}$'),
  issued_at bigint NOT NULL,
  received_at timestamptz NOT NULL DEFAULT clock_timestamp(),
  PRIMARY KEY(issuer,company_id,nonce),
  UNIQUE(issuer,company_id,request_id)
);
ALTER TABLE central_auto_lab.mtls_nonce_ledger ENABLE ROW LEVEL SECURITY;
ALTER TABLE central_auto_lab.mtls_nonce_ledger FORCE ROW LEVEL SECURITY;
CREATE POLICY owner_only ON central_auto_lab.mtls_nonce_ledger
 FOR ALL TO ca_mtls_lab_owner USING(true) WITH CHECK(true);

CREATE FUNCTION central_auto_lab.admit_nonce(
 p_issuer text,p_audience text,p_company text,p_request text,
 p_nonce text,p_digest text,p_issued bigint
) RETURNS text LANGUAGE plpgsql SECURITY DEFINER
SET search_path=pg_catalog,central_auto_lab AS $$
DECLARE
 now_epoch bigint:=floor(extract(epoch FROM clock_timestamp()))::bigint;
 inserted integer;
BEGIN
 -- All caller data is untrusted. This DB only proves storage admission.
 -- The separate mTLS server must authenticate the certificate and MAC.
 IF p_issuer IS DISTINCT FROM 'portal-automation-admin'
  OR p_audience IS DISTINCT FROM 'crm-automation-state'
  OR p_company IS DISTINCT FROM 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'
 THEN RETURN 'LAB_DENY_SCOPE'; END IF;
 IF p_request IS NULL OR p_nonce IS NULL OR p_digest IS NULL
  OR p_request !~ '^SYNTHETIC_[A-Z0-9_]{1,40}$'
  OR p_nonce !~ '^SYNTHETIC_[A-Z0-9_]{1,40}$'
  OR p_digest !~ '^[a-f0-9]{64}$'
 THEN RETURN 'LAB_DENY_REFERENCE'; END IF;
 IF p_issued IS NULL OR p_issued<now_epoch-60 OR p_issued>now_epoch+5
 THEN RETURN 'LAB_DENY_WINDOW'; END IF;
 INSERT INTO central_auto_lab.mtls_nonce_ledger(
  issuer,audience,company_id,request_id,nonce,request_digest,issued_at
 ) VALUES (
  p_issuer,p_audience,p_company::uuid,p_request,p_nonce,p_digest,p_issued
 ) ON CONFLICT DO NOTHING;
 GET DIAGNOSTICS inserted=ROW_COUNT;
 IF inserted=1 THEN RETURN 'LAB_ADMITTED_NO_DELIVERY'; END IF;
 RETURN 'LAB_REPLAY_DENIED';
END $$;
RESET ROLE;

REVOKE ALL ON SCHEMA central_auto_lab FROM PUBLIC;
REVOKE ALL ON TABLE central_auto_lab.mtls_nonce_ledger FROM PUBLIC;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA central_auto_lab FROM PUBLIC;
GRANT CONNECT ON DATABASE imagine_agents_ci TO ca_mtls_lab_service;
GRANT USAGE ON SCHEMA central_auto_lab TO ca_mtls_lab_service;
GRANT EXECUTE ON FUNCTION central_auto_lab.admit_nonce(
 text,text,text,text,text,text,bigint) TO ca_mtls_lab_service;
COMMIT;

DO $$
BEGIN
 IF has_table_privilege('ca_mtls_lab_service',
     'central_auto_lab.mtls_nonce_ledger','SELECT')
 OR has_table_privilege('ca_mtls_lab_service',
     'central_auto_lab.mtls_nonce_ledger','INSERT')
 OR has_table_privilege('ca_mtls_lab_service',
     'central_auto_lab.mtls_nonce_ledger','UPDATE')
 THEN RAISE EXCEPTION 'SERVICE_DIRECT_LEDGER_ACCESS'; END IF;
 IF NOT EXISTS (
  SELECT 1 FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace
  WHERE n.nspname='central_auto_lab' AND c.relname='mtls_nonce_ledger'
    AND c.relrowsecurity AND c.relforcerowsecurity
 ) THEN RAISE EXCEPTION 'FORCE_RLS_MISSING'; END IF;
END $$;
\echo CENTRAL_AUTO_P6_DB_READY
