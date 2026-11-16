-- AUTO-IA-07 PostgreSQL 16 CI-only. Never deploy this lab schema.
BEGIN;
CREATE ROLE aia_lab_owner NOLOGIN NOINHERIT NOBYPASSRLS;
CREATE ROLE aia_lab_tenant_a NOLOGIN NOINHERIT NOBYPASSRLS;
CREATE ROLE aia_lab_tenant_b NOLOGIN NOINHERIT NOBYPASSRLS;
CREATE SCHEMA auto_ia_lab AUTHORIZATION aia_lab_owner;
SET ROLE aia_lab_owner;
CREATE TABLE auto_ia_lab.budgets (
 tenant text NOT NULL, product text NOT NULL, policy_ref text NOT NULL,
 budget_minor bigint NOT NULL CHECK(budget_minor>=0),
 tokens bigint NOT NULL CHECK(tokens>=0),
 enabled boolean NOT NULL DEFAULT true,
 PRIMARY KEY(tenant,product));
CREATE TABLE auto_ia_lab.allowed_ops (
 product text NOT NULL, operation text NOT NULL, PRIMARY KEY(product,operation));
CREATE TABLE auto_ia_lab.requests (
 tenant text NOT NULL, product text NOT NULL,
 event_ref text NOT NULL, request_ref text NOT NULL,
 operation text NOT NULL, policy_ref text NOT NULL,
 payload_hash text NOT NULL, estimated_tokens bigint NOT NULL CHECK(estimated_tokens>0),
 estimated_minor bigint NOT NULL CHECK(estimated_minor>0),
 state text NOT NULL DEFAULT 'RESERVED' CHECK(state IN ('RESERVED','UNKNOWN')),
 PRIMARY KEY(tenant,product,event_ref), UNIQUE(tenant,product,request_ref),
 FOREIGN KEY(tenant,product) REFERENCES auto_ia_lab.budgets(tenant,product));
INSERT INTO auto_ia_lab.allowed_ops VALUES
 ('imagine-portal','support.message.interpret'),
 ('imagine-crm','lead.message.interpret'),
 ('imagine-sites','site.copy.propose'),
 ('imagine-comercial','campaign.copy.propose'),
 ('imagine-performance-ai','ad.copy.propose'),
 ('imagine-universidade','tutor.question.interpret'),
 ('imagine-seo-engine','meta.copy.propose'),
 ('imagine-ai-agents','document.summary.propose');
INSERT INTO auto_ia_lab.budgets VALUES
 ('SYNTHETIC_TENANT_A','imagine-crm','SYNTHETIC_POLICY_A',20,40,true),
 ('SYNTHETIC_TENANT_B','imagine-crm','SYNTHETIC_POLICY_B',20,40,true),
 ('SYNTHETIC_TENANT_A','imagine-ai-agents','SYNTHETIC_POLICY_A',12,33,true);
ALTER TABLE auto_ia_lab.budgets ENABLE ROW LEVEL SECURITY;
ALTER TABLE auto_ia_lab.budgets FORCE ROW LEVEL SECURITY;
ALTER TABLE auto_ia_lab.allowed_ops ENABLE ROW LEVEL SECURITY;
ALTER TABLE auto_ia_lab.allowed_ops FORCE ROW LEVEL SECURITY;
ALTER TABLE auto_ia_lab.requests ENABLE ROW LEVEL SECURITY;
ALTER TABLE auto_ia_lab.requests FORCE ROW LEVEL SECURITY;
CREATE POLICY owner_budget ON auto_ia_lab.budgets FOR ALL TO aia_lab_owner USING (true) WITH CHECK (true);
CREATE POLICY owner_ops ON auto_ia_lab.allowed_ops FOR ALL TO aia_lab_owner USING (true) WITH CHECK (true);
CREATE POLICY owner_request ON auto_ia_lab.requests FOR ALL TO aia_lab_owner USING (true) WITH CHECK (true);
CREATE POLICY budget_read ON auto_ia_lab.budgets FOR SELECT TO aia_lab_tenant_a,aia_lab_tenant_b
 USING ((current_user='aia_lab_tenant_a' AND tenant='SYNTHETIC_TENANT_A')
 OR (current_user='aia_lab_tenant_b' AND tenant='SYNTHETIC_TENANT_B'));
CREATE POLICY request_read ON auto_ia_lab.requests FOR SELECT TO aia_lab_tenant_a,aia_lab_tenant_b
 USING ((current_user='aia_lab_tenant_a' AND tenant='SYNTHETIC_TENANT_A')
 OR (current_user='aia_lab_tenant_b' AND tenant='SYNTHETIC_TENANT_B'));

CREATE FUNCTION auto_ia_lab.reserve(
 p_product text,p_operation text,p_event text,p_request text,p_hash text,
 p_est_tokens bigint,p_est_minor bigint
) RETURNS text LANGUAGE plpgsql SECURITY DEFINER
SET search_path=pg_catalog,auto_ia_lab AS $$
DECLARE
 actor text := current_setting('role',true);
 tenant_id text;
 limit_minor bigint; limit_tokens bigint; policy_id text; active boolean;
 used_minor bigint; used_tokens bigint; unresolved bigint;
 existing auto_ia_lab.requests%ROWTYPE;
BEGIN
 tenant_id := CASE actor WHEN 'aia_lab_tenant_a' THEN 'SYNTHETIC_TENANT_A'
                        WHEN 'aia_lab_tenant_b' THEN 'SYNTHETIC_TENANT_B' ELSE NULL END;
 IF tenant_id IS NULL THEN RETURN 'LAB_DENY_IDENTITY'; END IF;
 IF p_event IS NULL OR p_request IS NULL OR p_hash IS NULL
    OR p_event !~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'
    OR p_request !~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'
    OR p_hash !~ '^[a-f0-9]{64}$' OR p_est_tokens IS NULL OR p_est_minor IS NULL
    OR p_est_tokens<=0 OR p_est_minor<=0
    OR p_est_tokens>100000000 OR p_est_minor>100000000 THEN
   RETURN 'LAB_DENY_INPUT'; END IF;
 IF NOT EXISTS(SELECT 1 FROM auto_ia_lab.allowed_ops
               WHERE product=p_product AND operation=p_operation) THEN
   RETURN 'LAB_DENY_OPERATION'; END IF;
 SELECT budget_minor,tokens,policy_ref,enabled
 INTO limit_minor,limit_tokens,policy_id,active FROM auto_ia_lab.budgets
 WHERE tenant=tenant_id AND product=p_product FOR UPDATE;
 IF NOT FOUND OR NOT active THEN RETURN 'LAB_DENY_POLICY'; END IF;
 SELECT * INTO existing FROM auto_ia_lab.requests
 WHERE tenant=tenant_id AND product=p_product
   AND (event_ref=p_event OR request_ref=p_request);
 IF FOUND THEN
   IF existing.event_ref=p_event AND existing.request_ref=p_request
      AND existing.operation=p_operation AND existing.policy_ref=policy_id
      AND existing.payload_hash=p_hash AND existing.estimated_tokens=p_est_tokens
      AND existing.estimated_minor=p_est_minor THEN
      RETURN 'LAB_IDEMPOTENT_NO_AUTHORIZATION';
   END IF;
   RETURN 'LAB_DENY_REPLAY_CONFLICT';
 END IF;
 SELECT COALESCE(SUM(estimated_minor),0),COALESCE(SUM(estimated_tokens),0),
   COUNT(*) FILTER(WHERE state='UNKNOWN')
 INTO used_minor,used_tokens,unresolved FROM auto_ia_lab.requests
 WHERE tenant=tenant_id AND product=p_product;
 IF unresolved>0 THEN RETURN 'LAB_DENY_USAGE_UNCERTAIN'; END IF;
 IF p_est_minor>limit_minor-used_minor OR p_est_tokens>limit_tokens-used_tokens THEN
   RETURN 'LAB_DENY_BUDGET'; END IF;
 INSERT INTO auto_ia_lab.requests
 (tenant,product,event_ref,request_ref,operation,policy_ref,payload_hash,estimated_tokens,estimated_minor)
 VALUES(tenant_id,p_product,p_event,p_request,p_operation,policy_id,p_hash,p_est_tokens,p_est_minor);
 RETURN 'LAB_RESERVED_NO_AUTHORIZATION';
END $$;

CREATE FUNCTION auto_ia_lab.mark_unknown(p_product text,p_event text,p_request text)
RETURNS text LANGUAGE plpgsql SECURITY DEFINER
SET search_path=pg_catalog,auto_ia_lab AS $$
DECLARE
 actor text := current_setting('role',true);
 tenant_id text; affected integer;
BEGIN
 tenant_id := CASE actor WHEN 'aia_lab_tenant_a' THEN 'SYNTHETIC_TENANT_A'
                        WHEN 'aia_lab_tenant_b' THEN 'SYNTHETIC_TENANT_B' ELSE NULL END;
 IF tenant_id IS NULL THEN RETURN 'LAB_DENY_IDENTITY'; END IF;
 -- Serialize UNKNOWN against a concurrent reservation on the SAME budget row.
 PERFORM 1 FROM auto_ia_lab.budgets
 WHERE tenant=tenant_id AND product=p_product FOR UPDATE;
 IF NOT FOUND THEN RETURN 'LAB_DENY_POLICY'; END IF;
 UPDATE auto_ia_lab.requests SET state='UNKNOWN'
 WHERE tenant=tenant_id AND product=p_product AND event_ref=p_event AND request_ref=p_request;
 GET DIAGNOSTICS affected=ROW_COUNT;
 IF affected<>1 THEN RETURN 'LAB_DENY_SCOPE_OR_MISSING'; END IF;
 RETURN 'LAB_UNKNOWN_HELD_NO_RETRY';
END $$;
RESET ROLE;
REVOKE ALL ON SCHEMA auto_ia_lab FROM PUBLIC;
REVOKE ALL ON ALL TABLES IN SCHEMA auto_ia_lab FROM PUBLIC;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA auto_ia_lab FROM PUBLIC;
GRANT USAGE ON SCHEMA auto_ia_lab TO aia_lab_tenant_a,aia_lab_tenant_b;
GRANT SELECT ON auto_ia_lab.budgets,auto_ia_lab.requests TO aia_lab_tenant_a,aia_lab_tenant_b;
GRANT EXECUTE ON FUNCTION auto_ia_lab.reserve(text,text,text,text,text,bigint,bigint)
 TO aia_lab_tenant_a,aia_lab_tenant_b;
GRANT EXECUTE ON FUNCTION auto_ia_lab.mark_unknown(text,text,text)
 TO aia_lab_tenant_a,aia_lab_tenant_b;
COMMIT;
