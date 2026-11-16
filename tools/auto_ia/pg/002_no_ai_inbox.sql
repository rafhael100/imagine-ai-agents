-- AUTO-IA-08: CI-only durable inbox for synthetic, deterministic NO_AI events.
-- No network, n8n connector, S2S credential, customer, service effect or API call.
BEGIN;
SET ROLE aia_lab_owner;
CREATE TABLE auto_ia_lab.no_ai_ops (
 product text NOT NULL, operation text NOT NULL, PRIMARY KEY(product,operation)
);
INSERT INTO auto_ia_lab.no_ai_ops VALUES
 ('imagine-portal','service.event.notify'),
 ('imagine-portal','subscription.status.project'),
 ('imagine-crm','lead.ingest'),
 ('imagine-crm','lead.score.rules'),
 ('imagine-sites','briefing.register'),
 ('imagine-sites','site.approval.notify'),
 ('imagine-comercial','quote.calculate'),
 ('imagine-comercial','funnel.calculate'),
 ('imagine-performance-ai','campaign.metrics.aggregate'),
 ('imagine-performance-ai','management.fee.calculate'),
 ('imagine-universidade','lesson.progress.record'),
 ('imagine-universidade','eligibility.snapshot.check'),
 ('imagine-seo-engine','hreflang.audit'),
 ('imagine-seo-engine','sitemap.generate'),
 ('imagine-ai-agents','usage.quota.evaluate'),
 ('imagine-ai-agents','run.status.project');
CREATE TABLE auto_ia_lab.event_inbox (
 tenant text NOT NULL,
 product text NOT NULL, operation text NOT NULL,
 event_ref text NOT NULL,
 nonce_ref text NOT NULL,
 payload_hash text NOT NULL,
 issued_epoch bigint NOT NULL, expires_epoch bigint NOT NULL,
 received_at timestamptz NOT NULL DEFAULT clock_timestamp(),
 PRIMARY KEY(tenant,product,event_ref),
 UNIQUE(tenant,product,nonce_ref)
);
ALTER TABLE auto_ia_lab.no_ai_ops ENABLE ROW LEVEL SECURITY;
ALTER TABLE auto_ia_lab.no_ai_ops FORCE ROW LEVEL SECURITY;
ALTER TABLE auto_ia_lab.event_inbox ENABLE ROW LEVEL SECURITY;
ALTER TABLE auto_ia_lab.event_inbox FORCE ROW LEVEL SECURITY;
CREATE POLICY owner_no_ai ON auto_ia_lab.no_ai_ops FOR ALL TO aia_lab_owner
 USING(true) WITH CHECK(true);
CREATE POLICY owner_inbox ON auto_ia_lab.event_inbox FOR ALL TO aia_lab_owner
 USING(true) WITH CHECK(true);
CREATE POLICY tenant_inbox_read ON auto_ia_lab.event_inbox FOR SELECT
 TO aia_lab_tenant_a,aia_lab_tenant_b
 USING ((current_user='aia_lab_tenant_a' AND tenant='SYNTHETIC_TENANT_A')
     OR (current_user='aia_lab_tenant_b' AND tenant='SYNTHETIC_TENANT_B'));

CREATE FUNCTION auto_ia_lab.receive_no_ai(
 p_product text,p_operation text,p_event text,p_nonce text,p_hash text,
 p_issued bigint,p_expires bigint
) RETURNS text LANGUAGE plpgsql SECURITY DEFINER
SET search_path=pg_catalog,auto_ia_lab AS $$
DECLARE
 actor text := current_setting('role',true);
 tenant_id text;
 server_now bigint;
 existing auto_ia_lab.event_inbox%ROWTYPE;
BEGIN
 tenant_id := CASE actor WHEN 'aia_lab_tenant_a' THEN 'SYNTHETIC_TENANT_A'
                        WHEN 'aia_lab_tenant_b' THEN 'SYNTHETIC_TENANT_B' ELSE NULL END;
 IF tenant_id IS NULL THEN RETURN 'LAB_DENY_IDENTITY'; END IF;
 IF p_event IS NULL OR p_nonce IS NULL OR p_hash IS NULL
 OR p_event !~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'
 OR p_nonce !~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'
 OR p_hash !~ '^[a-f0-9]{64}$' THEN RETURN 'LAB_DENY_ENVELOPE'; END IF;
 server_now := floor(extract(epoch FROM clock_timestamp()))::bigint;
 IF p_issued IS NULL OR p_expires IS NULL
 OR p_issued < server_now-120 OR p_issued > server_now+10
 OR p_expires <= server_now OR p_expires <= p_issued
 OR p_expires-p_issued > 300 THEN RETURN 'LAB_DENY_EXPIRED'; END IF;
 IF NOT EXISTS(SELECT 1 FROM auto_ia_lab.no_ai_ops
     WHERE product=p_product AND operation=p_operation) THEN
    RETURN 'LAB_DENY_AI_OR_UNKNOWN_OPERATION'; END IF;

 -- Two unique constraints prevent a duplicate nonce or event from being
 -- inserted by concurrent transactions. No outbound effect follows insertion.
 INSERT INTO auto_ia_lab.event_inbox
 (tenant,product,operation,event_ref,nonce_ref,payload_hash,issued_epoch,expires_epoch)
 VALUES (tenant_id,p_product,p_operation,p_event,p_nonce,p_hash,p_issued,p_expires)
 ON CONFLICT DO NOTHING;
 IF FOUND THEN RETURN 'LAB_RECEIVED_NO_DELIVERY'; END IF;
 SELECT * INTO existing FROM auto_ia_lab.event_inbox
 WHERE tenant=tenant_id AND product=p_product
   AND (event_ref=p_event OR nonce_ref=p_nonce);
 IF FOUND AND existing.event_ref=p_event AND existing.nonce_ref=p_nonce
 AND existing.operation=p_operation AND existing.payload_hash=p_hash
 AND existing.issued_epoch=p_issued AND existing.expires_epoch=p_expires THEN
    RETURN 'LAB_DUPLICATE_NO_DELIVERY'; END IF;
 RETURN 'LAB_DENY_REPLAY_CONFLICT';
END $$;
RESET ROLE;
REVOKE ALL ON auto_ia_lab.no_ai_ops,auto_ia_lab.event_inbox FROM PUBLIC;
REVOKE ALL ON FUNCTION auto_ia_lab.receive_no_ai(text,text,text,text,text,bigint,bigint) FROM PUBLIC;
GRANT SELECT ON auto_ia_lab.event_inbox TO aia_lab_tenant_a,aia_lab_tenant_b;
GRANT EXECUTE ON FUNCTION auto_ia_lab.receive_no_ai(text,text,text,text,text,bigint,bigint)
 TO aia_lab_tenant_a,aia_lab_tenant_b;
COMMIT;
