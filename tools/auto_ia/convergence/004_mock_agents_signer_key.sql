-- AIA-CONV-04 CI-only synthetic key for the already existing AUTO-IA-10 lab ring.
-- No actual service can use this key; no production secret material or IAM.
\set ON_ERROR_STOP on
BEGIN;
SET ROLE aia_lab_owner;
INSERT INTO auto_ia_lab.mock_s2s_keys(issuer,key_id,secret,enabled)
VALUES ('imagine-ai-agents','SYNTHETIC_KEY_A',
        'SYNTHETIC_OFFLINE_HMAC_TEST_KEY_A_32_BYTES_ONLY',true);
RESET ROLE;
COMMIT;
\echo AIA_CONV_04_SYNTHETIC_KEY_READY
