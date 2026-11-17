-- P26 disposable CI only. The original P6 outbox / P38 ACK remain authoritative.
-- A STARTED row fences automatic retry even if the external outcome is unknown.
CREATE SCHEMA aia_p26_native;
REVOKE ALL ON SCHEMA aia_p26_native FROM PUBLIC;
CREATE TABLE aia_p26_native.executions (
 tenant_ref varchar(64) NOT NULL,
 event_ref varchar(64) NOT NULL,
 company_id uuid NOT NULL,
 worker_ref varchar(64) NOT NULL,
 attempt integer NOT NULL CHECK (attempt BETWEEN 1 AND 3),
 payload_hash char(64) NOT NULL CHECK (payload_hash ~ '^[a-f0-9]{64}$'),
 template_sha256 char(64) NOT NULL CHECK (template_sha256 ~ '^[a-f0-9]{64}$'),
 workflow_sha256 char(64) NOT NULL CHECK (workflow_sha256 ~ '^[a-f0-9]{64}$'),
 workflow_id varchar(64) NOT NULL,
 context_json text NOT NULL CHECK (octet_length(context_json) <= 4096),
 context_sha256 char(64) NOT NULL CHECK (context_sha256 ~ '^[a-f0-9]{64}$'),
 state varchar(16) NOT NULL CHECK (state IN ('STARTED','RESULT','ACKED','UNKNOWN')),
 reason varchar(64) NOT NULL,
 result_json text CHECK (octet_length(result_json) <= 262144),
 result_sha256 char(64) CHECK (result_sha256 ~ '^[a-f0-9]{64}$'),
 canonical_receipt varchar(1024),
 signature_b64 varchar(88),
 started_at timestamptz NOT NULL DEFAULT clock_timestamp(),
 observed_at timestamptz,
 updated_at timestamptz NOT NULL DEFAULT clock_timestamp(),
 PRIMARY KEY (tenant_ref,event_ref),
 FOREIGN KEY (tenant_ref,event_ref)
   REFERENCES public.aia_lab_n8n_outbox_p6(tenant_ref,event_ref),
 CHECK ((state IN ('RESULT','ACKED')) =
   (result_json IS NOT NULL AND result_sha256 IS NOT NULL
    AND canonical_receipt IS NOT NULL AND signature_b64 IS NOT NULL))
);
REVOKE ALL ON TABLE aia_p26_native.executions FROM PUBLIC;
