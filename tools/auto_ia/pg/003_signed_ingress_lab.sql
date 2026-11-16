-- AUTO-IA-10 CI-only; existing AUTO-IA-07,08 must already be initialized.
-- The key material below is SYNTHETIC test data. Never use in any environment.
CREATE EXTENSION IF NOT EXISTS pgcrypto;
BEGIN;
SET ROLE aia_lab_owner;
CREATE TABLE auto_ia_lab.mock_s2s_keys (
  issuer text NOT NULL,
  key_id text NOT NULL,
  secret text NOT NULL,
  enabled boolean NOT NULL,
  PRIMARY KEY(issuer,key_id)
);
ALTER TABLE auto_ia_lab.mock_s2s_keys ENABLE ROW LEVEL SECURITY;
ALTER TABLE auto_ia_lab.mock_s2s_keys FORCE ROW LEVEL SECURITY;
CREATE POLICY mock_key_owner_only ON auto_ia_lab.mock_s2s_keys FOR ALL TO aia_lab_owner
  USING(true) WITH CHECK(true);
INSERT INTO auto_ia_lab.mock_s2s_keys VALUES
('imagine-crm','SYNTHETIC_KEY_A','SYNTHETIC_OFFLINE_HMAC_TEST_KEY_A_32_BYTES_ONLY',true),
('imagine-crm','SYNTHETIC_KEY_B','SYNTHETIC_OFFLINE_HMAC_TEST_KEY_B_32_BYTES_ONLY',false);

CREATE FUNCTION auto_ia_lab.receive_signed_no_ai(
 p_schema text, p_issuer text, p_audience text, p_product text,
 p_operation text, p_tenant text, p_event text, p_nonce text,
 p_request text, p_issued bigint, p_expires bigint, p_payload_hash text,
 p_key_id text, p_signature text
) RETURNS text LANGUAGE plpgsql SECURITY DEFINER
SET search_path=pg_catalog,auto_ia_lab AS $$
DECLARE
  v_actor text := current_setting('role',true);
  v_tenant text;
  v_secret text;
  v_data text;
  v_mac text;
BEGIN
 v_tenant := CASE v_actor
   WHEN 'aia_lab_tenant_a' THEN 'SYNTHETIC_TENANT_A'
   WHEN 'aia_lab_tenant_b' THEN 'SYNTHETIC_TENANT_B' ELSE NULL END;
 IF v_tenant IS NULL OR p_tenant IS DISTINCT FROM v_tenant
   THEN RETURN 'LAB_DENY_SERVICE_TENANT'; END IF;
 IF p_schema IS DISTINCT FROM 'AUTO_IA_S2S_V1'
   OR p_issuer IS DISTINCT FROM p_product
   OR p_audience IS DISTINCT FROM 'imagine-ai-agents'
   OR p_key_id IS NULL
   OR p_key_id !~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'
   OR p_signature IS NULL OR p_signature !~ '^[a-f0-9]{64}$'
   OR p_operation IS NULL OR p_operation !~ '^[a-z][a-z0-9_.]{0,79}$'
   OR p_event IS NULL OR p_event !~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'
   OR p_nonce IS NULL OR p_nonce !~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'
   OR p_request IS NULL OR p_request !~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'
   OR p_payload_hash IS NULL OR p_payload_hash !~ '^[a-f0-9]{64}$'
   OR p_issued IS NULL OR p_expires IS NULL THEN
     RETURN 'LAB_DENY_SIGNED_SCHEMA';
 END IF;
 -- Check server time in underlying receive_no_ai as well. An expired
 -- signature is not accepted even if a caller-supplied now is forged.
 IF p_expires<=p_issued OR p_expires-p_issued>300
   OR p_issued>floor(extract(epoch FROM clock_timestamp()))::bigint+5
   OR p_expires<=floor(extract(epoch FROM clock_timestamp()))::bigint THEN
   RETURN 'LAB_DENY_SIGNED_WINDOW';
 END IF;
 SELECT secret INTO v_secret FROM auto_ia_lab.mock_s2s_keys
 WHERE issuer=p_issuer AND key_id=p_key_id AND enabled=true;
 IF NOT FOUND THEN RETURN 'LAB_DENY_KEY'; END IF;
 v_data:=concat_ws(E'\n',p_schema,p_issuer,p_audience,p_product,p_operation,
   p_tenant,p_event,p_nonce,p_request,p_issued::text,p_expires::text,
   p_payload_hash,p_key_id);
 v_mac:=encode(public.hmac(v_data,v_secret,'sha256'),'hex');
 IF v_mac <> p_signature THEN RETURN 'LAB_DENY_MAC'; END IF;
 -- The unsigned ingress function is now unreachable to tenant lab roles,
 -- and only called from this checked, security-definer function.
 RETURN auto_ia_lab.receive_no_ai(p_product,p_operation,p_event,p_nonce,
   p_payload_hash,p_issued,p_expires);
END $$;
RESET ROLE;

REVOKE ALL ON TABLE auto_ia_lab.mock_s2s_keys FROM PUBLIC;
REVOKE ALL ON FUNCTION auto_ia_lab.receive_signed_no_ai(
 text,text,text,text,text,text,text,text,text,bigint,bigint,text,text,text) FROM PUBLIC;
REVOKE EXECUTE ON FUNCTION auto_ia_lab.receive_no_ai(
 text,text,text,text,text,bigint,bigint) FROM aia_lab_tenant_a,aia_lab_tenant_b;
GRANT EXECUTE ON FUNCTION auto_ia_lab.receive_signed_no_ai(
 text,text,text,text,text,text,text,text,text,bigint,bigint,text,text,text)
 TO aia_lab_tenant_a,aia_lab_tenant_b;
COMMIT;
