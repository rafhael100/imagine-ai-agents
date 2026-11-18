-- P67: disposable fictitious homologation only. No changes to P19/P27/P51/P59/P64.
-- Installation creates no LOGIN/password, company, certificate grant or HMAC key.
\set ON_ERROR_STOP on
BEGIN;
DO $$ BEGIN
 IF current_database() <> 'imagine_agents_ci' THEN
  RAISE EXCEPTION 'P67_DISPOSABLE_DATABASE_ONLY';
 END IF;
END $$;
CREATE EXTENSION IF NOT EXISTS pgcrypto WITH SCHEMA public;
CREATE ROLE aia_p67_owner NOLOGIN NOINHERIT NOSUPERUSER NOBYPASSRLS
 NOCREATEROLE NOCREATEDB NOREPLICATION;
CREATE ROLE aia_p67_create NOLOGIN NOINHERIT NOSUPERUSER NOBYPASSRLS
 NOCREATEROLE NOCREATEDB NOREPLICATION;
CREATE ROLE aia_p67_read NOLOGIN NOINHERIT NOSUPERUSER NOBYPASSRLS
 NOCREATEROLE NOCREATEDB NOREPLICATION;
CREATE SCHEMA aia_p67_owner AUTHORIZATION aia_p67_owner;
REVOKE ALL ON SCHEMA aia_p67_owner FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO aia_p67_owner;
SET ROLE aia_p67_owner;
ALTER DEFAULT PRIVILEGES IN SCHEMA aia_p67_owner REVOKE EXECUTE ON FUNCTIONS FROM PUBLIC;

-- All wire values are closed ASCII strings, integers, booleans or null. Sorting
-- uses C collation, not the database locale. Arrays are never contract values.
CREATE FUNCTION aia_p67_owner.canonical(value jsonb) RETURNS text
LANGUAGE plpgsql IMMUTABLE STRICT SET search_path=pg_catalog,aia_p67_owner AS $$
DECLARE field text; item jsonb; result text := ''; separator text := '';
BEGIN
 IF jsonb_typeof(value) = 'object' THEN
  FOR field,item IN SELECT key,val FROM jsonb_each(value) AS j(key,val) ORDER BY key COLLATE "C" LOOP
   result := result || separator || to_jsonb(field)::text || ':' || aia_p67_owner.canonical(item);
   separator := ',';
  END LOOP;
  RETURN '{' || result || '}';
 END IF;
 IF jsonb_typeof(value) NOT IN ('string','number','boolean','null') THEN
  RAISE EXCEPTION 'P67_CANONICAL_TYPE';
 END IF;
 RETURN value::text;
END $$;

CREATE FUNCTION aia_p67_owner.has_keys(value jsonb, expected text[]) RETURNS boolean
LANGUAGE plpgsql IMMUTABLE SET search_path=pg_catalog,aia_p67_owner AS $$
BEGIN
 IF jsonb_typeof(value) IS DISTINCT FROM 'object' THEN RETURN false; END IF;
 RETURN value ?& expected AND (SELECT count(*) FROM jsonb_object_keys(value)) = cardinality(expected);
END $$;

CREATE FUNCTION aia_p67_owner.is_integer(value jsonb, low bigint, high bigint) RETURNS boolean
LANGUAGE plpgsql IMMUTABLE SET search_path=pg_catalog,aia_p67_owner AS $$
BEGIN
 IF jsonb_typeof(value) IS DISTINCT FROM 'number' OR value::text !~ '^(0|[1-9][0-9]{0,9})$'
 THEN RETURN false; END IF;
 RETURN value::text::bigint BETWEEN low AND high;
END $$;

CREATE FUNCTION aia_p67_owner.valid_business(b jsonb) RETURNS boolean
LANGUAGE plpgsql IMMUTABLE SET search_path=pg_catalog,aia_p67_owner AS $$
DECLARE field text; p jsonb;
BEGIN
 IF NOT aia_p67_owner.has_keys(b, ARRAY['schema','company_id','draft_ref','creator_id',
  'source_run_id','rule_id','settings_version','lead_id','lead_version','source_created_at','command_hash','projection'])
 THEN RETURN false; END IF;
 FOREACH field IN ARRAY ARRAY['schema','company_id','draft_ref','creator_id','source_run_id','rule_id','lead_id','command_hash'] LOOP
  IF jsonb_typeof(b->field) IS DISTINCT FROM 'string' THEN RETURN false; END IF;
 END LOOP;
 IF b->>'schema' <> 'CRM_AIA_OWNER_RULE_DRAFT_V1' OR b->>'rule_id' <> 'followup_qualified'
 OR b->>'command_hash' !~ '^[a-f0-9]{64}$' THEN RETURN false; END IF;
 FOREACH field IN ARRAY ARRAY['company_id','draft_ref','creator_id','source_run_id','lead_id'] LOOP
  IF b->>field !~ '^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$'
  THEN RETURN false; END IF;
 END LOOP;
 IF NOT aia_p67_owner.is_integer(b->'settings_version',0,20)
 OR NOT aia_p67_owner.is_integer(b->'lead_version',1,2147483647)
 OR NOT aia_p67_owner.is_integer(b->'source_created_at',1,4102444800) THEN RETURN false; END IF;
 p := b->'projection';
 IF NOT aia_p67_owner.has_keys(p,ARRAY['action','stage','delay_seconds','due_at'])
 OR jsonb_typeof(p->'action') IS DISTINCT FROM 'string' OR p->>'action' <> 'task.followup.create'
 OR jsonb_typeof(p->'stage') IS DISTINCT FROM 'string' OR p->>'stage' <> 'qualified'
 OR NOT aia_p67_owner.is_integer(p->'delay_seconds',0,86400)
 OR NOT aia_p67_owner.is_integer(p->'due_at',1,4102531200) THEN RETURN false; END IF;
 RETURN (p->>'due_at')::bigint = (b->>'source_created_at')::bigint + (p->>'delay_seconds')::bigint;
END $$;

CREATE FUNCTION aia_p67_owner.valid_authorization(a jsonb, op text) RETURNS boolean
LANGUAGE plpgsql IMMUTABLE SET search_path=pg_catalog,aia_p67_owner AS $$
DECLARE field text;
BEGIN
 IF op NOT IN ('crm.rule.draft.create','crm.rule.draft.read')
 OR NOT aia_p67_owner.has_keys(a, ARRAY['schema','issuer','audience','operation','company_id',
  'actor_role','actor_id','draft_ref','business_hash','nonce','issued_at','expires_at','key_id','mac'])
 THEN RETURN false; END IF;
 FOREACH field IN ARRAY ARRAY['schema','issuer','audience','operation','company_id','actor_role',
  'actor_id','draft_ref','business_hash','nonce','key_id','mac'] LOOP
  IF jsonb_typeof(a->field) IS DISTINCT FROM 'string' THEN RETURN false; END IF;
 END LOOP;
 IF a->>'schema' <> 'CRM_AIA_OWNER_DRAFT_AUTH_V1' OR a->>'issuer' <> 'imagine-crm'
 OR a->>'audience' <> 'imagine-ai-agents' OR a->>'operation' <> op OR a->>'actor_role' <> 'owner'
 OR a->>'business_hash' !~ '^[a-f0-9]{64}$' OR a->>'mac' !~ '^[a-f0-9]{64}$'
 OR a->>'key_id' !~ '^[a-z][a-z0-9_-]{0,63}$' THEN RETURN false; END IF;
 FOREACH field IN ARRAY ARRAY['company_id','actor_id','draft_ref','nonce'] LOOP
  IF a->>field !~ '^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$'
  THEN RETURN false; END IF;
 END LOOP;
 RETURN aia_p67_owner.is_integer(a->'issued_at',1,4102444800)
  AND aia_p67_owner.is_integer(a->'expires_at',1,4102444800);
END $$;

CREATE FUNCTION aia_p67_owner.live(a jsonb, now_epoch bigint) RETURNS boolean
LANGUAGE sql IMMUTABLE SET search_path=pg_catalog,aia_p67_owner AS $$
 SELECT (a->>'issued_at')::bigint BETWEEN now_epoch-30 AND now_epoch+5
 AND (a->>'expires_at')::bigint > now_epoch
 AND (a->>'expires_at')::bigint > (a->>'issued_at')::bigint
 AND (a->>'expires_at')::bigint - (a->>'issued_at')::bigint <= 30
$$;

CREATE TABLE aia_p67_owner.companies (
 company_id uuid PRIMARY KEY,
 active boolean NOT NULL,
 revision integer NOT NULL CHECK(revision BETWEEN 1 AND 1000000)
);
CREATE TABLE aia_p67_owner.mac_keys (
 key_id text PRIMARY KEY CHECK(key_id ~ '^[a-z][a-z0-9_-]{0,63}$'),
 operation text NOT NULL CHECK(operation IN ('crm.rule.draft.create','crm.rule.draft.read')),
 -- These are the 64 ASCII bytes, not hex-decoded bytes. Unique secrets prevent
 -- accidental use of a single key for the two independently granted operations.
 secret text NOT NULL UNIQUE CHECK(secret ~ '^[a-f0-9]{64}$'),
 active boolean NOT NULL,
 revision integer NOT NULL CHECK(revision BETWEEN 1 AND 1000000),
 expires_at bigint NOT NULL CHECK(expires_at BETWEEN 1 AND 4102444800),
 UNIQUE(key_id,operation)
);
CREATE TABLE aia_p67_owner.service_grants (
 company_id uuid NOT NULL REFERENCES aia_p67_owner.companies(company_id),
 fingerprint text NOT NULL CHECK(fingerprint ~ '^[a-f0-9]{64}$'),
 operation text NOT NULL CHECK(operation IN ('crm.rule.draft.create','crm.rule.draft.read')),
 key_id text NOT NULL,
 active boolean NOT NULL,
 revision integer NOT NULL CHECK(revision BETWEEN 1 AND 1000000),
 expires_at bigint NOT NULL CHECK(expires_at BETWEEN 1 AND 4102444800),
 PRIMARY KEY(company_id,fingerprint,operation),
 FOREIGN KEY(key_id,operation) REFERENCES aia_p67_owner.mac_keys(key_id,operation)
);
CREATE TABLE aia_p67_owner.drafts (
 company_id uuid NOT NULL REFERENCES aia_p67_owner.companies(company_id),
 draft_ref uuid NOT NULL,
 source_run_id uuid NOT NULL,
 creator_id uuid NOT NULL,
 authorship_source text NOT NULL CHECK(authorship_source='CRM_AUTHENTICATED_OWNER'),
 state text NOT NULL CHECK(state='DRAFT'),
 business jsonb NOT NULL CHECK(aia_p67_owner.valid_business(business)),
 business_hash text NOT NULL CHECK(business_hash ~ '^[a-f0-9]{64}$'),
 accepted_fingerprint text NOT NULL CHECK(accepted_fingerprint ~ '^[a-f0-9]{64}$'),
 accepted_key_id text NOT NULL CHECK(accepted_key_id ~ '^[a-z][a-z0-9_-]{0,63}$'),
 accepted_grant_revision integer NOT NULL CHECK(accepted_grant_revision BETWEEN 1 AND 1000000),
 accepted_authorization_hash text NOT NULL CHECK(accepted_authorization_hash ~ '^[a-f0-9]{64}$'),
 created_at bigint NOT NULL CHECK(created_at BETWEEN 1 AND 4102444800),
 PRIMARY KEY(company_id,draft_ref),
 UNIQUE(company_id,source_run_id),
 CHECK(business->>'company_id'=company_id::text AND business->>'draft_ref'=draft_ref::text
  AND business->>'source_run_id'=source_run_id::text AND business->>'creator_id'=creator_id::text),
 CHECK(business_hash=encode(public.digest(aia_p67_owner.canonical(business),'sha256'),'hex'))
);
CREATE TABLE aia_p67_owner.nonces (
 company_id uuid NOT NULL REFERENCES aia_p67_owner.companies(company_id),
 operation text NOT NULL CHECK(operation IN ('crm.rule.draft.create','crm.rule.draft.read')),
 nonce uuid NOT NULL,
 draft_ref uuid NOT NULL,
 actor_id uuid NOT NULL,
 business_hash text NOT NULL CHECK(business_hash ~ '^[a-f0-9]{64}$'),
 request_hash text NOT NULL CHECK(request_hash ~ '^[a-f0-9]{64}$'),
 accepted_at bigint NOT NULL,
 expires_at bigint NOT NULL CHECK(expires_at > accepted_at),
 PRIMARY KEY(company_id,operation,nonce)
);
CREATE TABLE aia_p67_owner.audit (
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
 company_id uuid NOT NULL,
 operation text NOT NULL,
 nonce uuid NOT NULL,
 draft_ref uuid NOT NULL,
 actor_id uuid NOT NULL,
 business_hash text NOT NULL CHECK(business_hash ~ '^[a-f0-9]{64}$'),
 fingerprint text NOT NULL CHECK(fingerprint ~ '^[a-f0-9]{64}$'),
 key_id text NOT NULL,
 grant_revision integer NOT NULL,
 company_revision integer NOT NULL,
 key_revision integer NOT NULL,
 outcome text NOT NULL CHECK(outcome IN ('DRAFT_CREATED','DRAFT_REPLAY','DRAFT_READ','ABSENT')),
 accepted_at bigint NOT NULL,
 UNIQUE(company_id,operation,nonce),
 FOREIGN KEY(company_id,operation,nonce) REFERENCES aia_p67_owner.nonces(company_id,operation,nonce)
);

-- Only the non-login definer can access rows, and its five company-bound tables
-- still require the transaction's company. Caller-controlled GUCs grant no ACL.
DO $$ DECLARE name text; BEGIN
 FOREACH name IN ARRAY ARRAY['companies','service_grants','drafts','nonces','audit'] LOOP
  EXECUTE format('ALTER TABLE aia_p67_owner.%I ENABLE ROW LEVEL SECURITY',name);
  EXECUTE format('ALTER TABLE aia_p67_owner.%I FORCE ROW LEVEL SECURITY',name);
  EXECUTE format('CREATE POLICY scoped_owner ON aia_p67_owner.%I TO aia_p67_owner '
   'USING(company_id=nullif(current_setting(''aia_p67_owner.company_id'',true),'''')::uuid) '
   'WITH CHECK(company_id=nullif(current_setting(''aia_p67_owner.company_id'',true),'''')::uuid)',name);
 END LOOP;
END $$;
ALTER TABLE aia_p67_owner.mac_keys ENABLE ROW LEVEL SECURITY;
ALTER TABLE aia_p67_owner.mac_keys FORCE ROW LEVEL SECURITY;
CREATE POLICY technical_owner ON aia_p67_owner.mac_keys TO aia_p67_owner USING(true) WITH CHECK(true);

CREATE FUNCTION aia_p67_owner.process_request(fp text, op text, body jsonb) RETURNS jsonb
LANGUAGE plpgsql SET search_path=pg_catalog,aia_p67_owner AS $$
DECLARE
 a jsonb; b jsonb; reply jsonb; company uuid; n bigint; inserted uuid;
 c aia_p67_owner.companies%ROWTYPE;
 g aia_p67_owner.service_grants%ROWTYPE;
 k aia_p67_owner.mac_keys%ROWTYPE;
 d aia_p67_owner.drafts%ROWTYPE;
 expected_mac bytea; supplied_mac bytea; mismatch integer := 0; i integer;
 created boolean := false; present boolean := false; outcome text; status text;
BEGIN
 IF current_database()<>'imagine_agents_ci' OR fp IS NULL OR fp !~ '^[a-f0-9]{64}$'
 OR op NOT IN ('crm.rule.draft.create','crm.rule.draft.read') OR op IS NULL
 OR NOT aia_p67_owner.has_keys(body,CASE WHEN op='crm.rule.draft.create'
  THEN ARRAY['authorization','business'] ELSE ARRAY['authorization'] END)
 OR octet_length(body::text)>8192 THEN RETURN NULL; END IF;
 a := body->'authorization';
 IF NOT aia_p67_owner.valid_authorization(a,op) THEN RETURN NULL; END IF;
 n := floor(extract(epoch FROM clock_timestamp()))::bigint;
 IF NOT aia_p67_owner.live(a,n) THEN RETURN NULL; END IF;
 IF op='crm.rule.draft.create' THEN
  b := body->'business';
  IF NOT aia_p67_owner.valid_business(b) OR b->>'company_id'<>a->>'company_id'
  OR b->>'draft_ref'<>a->>'draft_ref' OR b->>'creator_id'<>a->>'actor_id'
  OR encode(public.digest(aia_p67_owner.canonical(b),'sha256'),'hex')<>a->>'business_hash'
  THEN RETURN NULL; END IF;
 END IF;
 company := (a->>'company_id')::uuid;
 PERFORM set_config('aia_p67_owner.company_id',company::text,true);
 SELECT * INTO c FROM aia_p67_owner.companies WHERE company_id=company FOR SHARE;
 IF NOT FOUND OR NOT c.active THEN RETURN NULL; END IF;
 SELECT * INTO g FROM aia_p67_owner.service_grants
 WHERE company_id=company AND fingerprint=fp AND operation=op FOR SHARE;
 IF NOT FOUND OR NOT g.active OR g.key_id<>a->>'key_id' THEN RETURN NULL; END IF;
 SELECT * INTO k FROM aia_p67_owner.mac_keys
 WHERE key_id=g.key_id AND operation=op FOR SHARE;
 IF NOT FOUND OR NOT k.active THEN RETURN NULL; END IF;
 n := floor(extract(epoch FROM clock_timestamp()))::bigint;
 IF NOT aia_p67_owner.live(a,n) OR g.expires_at<=n OR k.expires_at<=n THEN RETURN NULL; END IF;
 expected_mac := public.hmac(E'IMAGINE_CRM_OWNER_DRAFT_REQUEST_V1\n'
  || aia_p67_owner.canonical(a-'mac'),k.secret,'sha256');
 supplied_mac := decode(a->>'mac','hex');
 FOR i IN 0..31 LOOP
  mismatch := mismatch | (get_byte(expected_mac,i) # get_byte(supplied_mac,i));
 END LOOP;
 IF mismatch<>0 THEN RETURN NULL; END IF;

 -- All changes after admission share this subtransaction. Even a direct SQL
 -- caller that commits a NULL result cannot commit a late nonce/draft/audit.
 BEGIN
  IF op='crm.rule.draft.create' THEN
   INSERT INTO aia_p67_owner.drafts(company_id,draft_ref,source_run_id,creator_id,authorship_source,
    state,business,business_hash,accepted_fingerprint,accepted_key_id,accepted_grant_revision,
    accepted_authorization_hash,created_at)
   VALUES(company,(a->>'draft_ref')::uuid,(b->>'source_run_id')::uuid,(b->>'creator_id')::uuid,
    'CRM_AUTHENTICATED_OWNER','DRAFT',b,a->>'business_hash',fp,k.key_id,g.revision,
    encode(public.digest(aia_p67_owner.canonical(a),'sha256'),'hex'),n)
   ON CONFLICT DO NOTHING RETURNING draft_ref INTO inserted;
   created := FOUND;
   -- INSERT may wait on either unique key. This SELECT obtains the committed
   -- winner and validates its immutable author/business without repairing it.
   SELECT * INTO d FROM aia_p67_owner.drafts
   WHERE company_id=company AND draft_ref=(a->>'draft_ref')::uuid FOR SHARE;
   IF NOT FOUND OR d.source_run_id<>(b->>'source_run_id')::uuid OR d.creator_id<>(a->>'actor_id')::uuid
   OR d.business_hash<>a->>'business_hash' OR d.business<>b THEN
    RAISE EXCEPTION USING ERRCODE='P6701',MESSAGE='P67_DENIED';
   END IF;
   present := true;
   outcome := CASE WHEN created THEN 'DRAFT_CREATED' ELSE 'DRAFT_REPLAY' END;
  ELSE
   SELECT * INTO d FROM aia_p67_owner.drafts
   WHERE company_id=company AND draft_ref=(a->>'draft_ref')::uuid FOR SHARE;
   present := FOUND;
   IF present AND d.business_hash<>a->>'business_hash' THEN present := false; END IF;
   outcome := CASE WHEN present THEN 'DRAFT_READ' ELSE 'ABSENT' END;
  END IF;
  IF present THEN
   IF d.authorship_source IS DISTINCT FROM 'CRM_AUTHENTICATED_OWNER' OR d.state IS DISTINCT FROM 'DRAFT'
   OR NOT aia_p67_owner.valid_business(d.business)
   OR d.business->>'company_id' IS DISTINCT FROM d.company_id::text
   OR d.business->>'draft_ref' IS DISTINCT FROM d.draft_ref::text
   OR d.business->>'creator_id' IS DISTINCT FROM d.creator_id::text
   OR d.business->>'source_run_id' IS DISTINCT FROM d.source_run_id::text
   OR d.business_hash IS DISTINCT FROM encode(public.digest(aia_p67_owner.canonical(d.business),'sha256'),'hex')
   THEN RAISE EXCEPTION 'P67_STORED_INVARIANT'; END IF;
   b := d.business;
   status := 'DRAFT';
  ELSE
   b := NULL;
   status := 'ABSENT';
  END IF;
  n := floor(extract(epoch FROM clock_timestamp()))::bigint;
  IF NOT aia_p67_owner.live(a,n) OR g.expires_at<=n OR k.expires_at<=n THEN
   RAISE EXCEPTION USING ERRCODE='P6701',MESSAGE='P67_DENIED';
  END IF;
  INSERT INTO aia_p67_owner.nonces(company_id,operation,nonce,draft_ref,actor_id,business_hash,
   request_hash,accepted_at,expires_at)
  VALUES(company,op,(a->>'nonce')::uuid,(a->>'draft_ref')::uuid,(a->>'actor_id')::uuid,
   a->>'business_hash',encode(public.digest(aia_p67_owner.canonical(body),'sha256'),'hex'),n,(a->>'expires_at')::bigint)
  ON CONFLICT DO NOTHING RETURNING nonce INTO inserted;
  IF NOT FOUND THEN RAISE EXCEPTION USING ERRCODE='P6701',MESSAGE='P67_DENIED'; END IF;
  INSERT INTO aia_p67_owner.audit(company_id,operation,nonce,draft_ref,actor_id,business_hash,
   fingerprint,key_id,grant_revision,company_revision,key_revision,outcome,accepted_at)
  VALUES(company,op,(a->>'nonce')::uuid,(a->>'draft_ref')::uuid,(a->>'actor_id')::uuid,a->>'business_hash',
   fp,k.key_id,g.revision,c.revision,k.revision,outcome,n);
  n := floor(extract(epoch FROM clock_timestamp()))::bigint;
  IF NOT aia_p67_owner.live(a,n) OR g.expires_at<=n OR k.expires_at<=n THEN
   RAISE EXCEPTION USING ERRCODE='P6701',MESSAGE='P67_DENIED';
  END IF;
  reply := jsonb_build_object('schema','CRM_AIA_OWNER_DRAFT_RESPONSE_V1',
   'issuer','imagine-ai-agents','audience','imagine-crm','operation',op,'company_id',company::text,
   'actor_id',a->>'actor_id','draft_ref',a->>'draft_ref','business_hash',a->>'business_hash',
   'nonce',a->>'nonce','status',status,'business',b,'executed',false,'operational',false,
   'issued_at',n,'expires_at',least(n+15,(a->>'expires_at')::bigint),'key_id',k.key_id);
  reply := reply || jsonb_build_object('mac',encode(public.hmac(
   E'IMAGINE_CRM_OWNER_DRAFT_RESPONSE_V1\n'||aia_p67_owner.canonical(reply),k.secret,'sha256'),'hex'));
  n := floor(extract(epoch FROM clock_timestamp()))::bigint;
  IF NOT aia_p67_owner.live(a,n) OR g.expires_at<=n OR k.expires_at<=n THEN
   RAISE EXCEPTION USING ERRCODE='P6701',MESSAGE='P67_DENIED';
  END IF;
  RETURN reply;
 EXCEPTION WHEN SQLSTATE 'P6701' THEN RETURN NULL;
 END;
END $$;

CREATE FUNCTION aia_p67_owner.create_draft(fp text, body jsonb) RETURNS jsonb
LANGUAGE sql SECURITY DEFINER SET search_path=pg_catalog,aia_p67_owner AS $$
 SELECT aia_p67_owner.process_request(fp,'crm.rule.draft.create',body)
$$;
CREATE FUNCTION aia_p67_owner.read_draft(fp text, body jsonb) RETURNS jsonb
LANGUAGE sql SECURITY DEFINER SET search_path=pg_catalog,aia_p67_owner AS $$
 SELECT aia_p67_owner.process_request(fp,'crm.rule.draft.read',body)
$$;
REVOKE ALL ON ALL TABLES IN SCHEMA aia_p67_owner FROM PUBLIC;
REVOKE ALL ON ALL SEQUENCES IN SCHEMA aia_p67_owner FROM PUBLIC;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA aia_p67_owner FROM PUBLIC;
GRANT USAGE ON SCHEMA aia_p67_owner TO aia_p67_create,aia_p67_read;
GRANT EXECUTE ON FUNCTION aia_p67_owner.create_draft(text,jsonb) TO aia_p67_create;
GRANT EXECUTE ON FUNCTION aia_p67_owner.read_draft(text,jsonb) TO aia_p67_read;
RESET ROLE;
COMMIT;
