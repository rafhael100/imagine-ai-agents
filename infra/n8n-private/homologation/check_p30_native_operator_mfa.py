#!/usr/bin/env python3
"""P30: native, voluntary operator MFA and session revocation in pinned n8n.

The authenticator is the image's own otpauth dependency. All account setup,
TOTP validation, recovery, password rotation and logout use original REST
routes; no user/authentication tables are written by this probe.

The client runs inside n8n and addresses loopback only. It deliberately sends
captured cookies to test server-side revocation while retaining Secure and
HttpOnly attributes. This is not a browser, HTTPS, mTLS or policy-enforcement
proof. No workflow, real person, activation key or external service is used.

Contracts inspected at n8n@2.47.7, official commit
d4dcb1b152a98fc31ea7c4ee6aecdc45c5c88063: controllers/{owner,auth,mfa,me},
auth/auth.service, mfa/{mfa,totp}.service and services/frontend.service.
"""
from __future__ import annotations

import json
import os
import re
import subprocess
import tempfile
from pathlib import Path

from check_p24_full_n8n_restore import N8N_IMAGE, Failure, Stack, require, run

N8N_VERSION = "2.47.7"
SOURCE_COMMIT = "d4dcb1b152a98fc31ea7c4ee6aecdc45c5c88063"
PROOFS = (
    "nominal_synthetic_owner_setup",
    "anonymous_protected_route_denied",
    "anonymous_enrollment_denied",
    "native_totp_enrollment_verified",
    "pre_enrollment_session_invalidated",
    "password_only_login_denied_after_enrollment",
    "invalid_totp_denied",
    "valid_totp_login_verified",
    "session_browser_binding_verified",
    "recovery_code_single_use_verified",
    "logout_replayed_cookie_denied",
    "logout_preserves_other_session",
    "password_rotation_invalidates_both_prior_sessions",
    "voluntary_disable_verified",
    "pre_disable_session_revoked",
    "pre_disable_session_stays_revoked_after_reenrollment",
    "reenrollment_has_new_secret",
    "final_native_mfa_session_verified",
    "secure_httponly_samesite_cookie_attributes",
    "unlicensed_enforcement_attempt_observed",
)

# Static source travels through stdin. The password, TOTP secret, recovery
# codes and session cookies are generated/received only inside this process.
# Neither raw responses nor unexpected exception messages are printed.
CLIENT = r"""
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const crypto = require('node:crypto');
const {createRequire} = require('node:module');

class ProbeFailure extends Error {
  constructor(code) { super('P30 assertion failed'); this.code = 'P30_' + code; }
}
function check(value, code) { if (!value) throw new ProbeFailure(code); }
function session() { return {browserId: crypto.randomUUID(), cookie: ''}; }
function saved(value) { return {...value}; }

async function main() {
  const executable = (process.env.PATH || '').split(path.delimiter)
    .map(directory => path.join(directory, 'n8n')).find(value => fs.existsSync(value));
  check(executable, 'N8N_EXECUTABLE_MISSING');
  let directory = path.dirname(fs.realpathSync(executable));
  let packageFile;
  for (let depth = 0; depth < 8; depth++) {
    const candidate = path.join(directory, 'package.json');
    if (fs.existsSync(candidate)) {
      const metadata = JSON.parse(fs.readFileSync(candidate, 'utf8'));
      if (metadata.name === 'n8n') {
        check(metadata.version === '2.47.7', 'PACKAGE_VERSION_CHANGED');
        packageFile = candidate;
        break;
      }
    }
    const parent = path.dirname(directory);
    if (parent === directory) break;
    directory = parent;
  }
  check(packageFile, 'N8N_PACKAGE_MISSING');
  const OTPAuth = createRequire(packageFile)('otpauth');
  check(typeof OTPAuth.TOTP.generate === 'function', 'NATIVE_AUTHENTICATOR_MISSING');
  const token = secret => OTPAuth.TOTP.generate({secret: OTPAuth.Secret.fromBase32(secret)});
  let loginRequests = 0;
  let issuedCookies = 0;

  async function request(method, route, current, data) {
    check(route.startsWith('/rest/'), 'CLIENT_ROUTE_OUTSIDE_REST');
    if (method === 'POST' && route === '/rest/login') {
      loginRequests++;
      check(loginRequests <= 5, 'LOGIN_RATE_BUDGET');
    }
    const body = data === undefined ? undefined : Buffer.from(JSON.stringify(data));
    const headers = {accept: 'application/json', 'browser-id': current.browserId};
    if (current.cookie) headers.cookie = current.cookie;
    if (body) {
      headers['content-type'] = 'application/json';
      headers['content-length'] = String(body.length);
    }
    return await new Promise((resolve, reject) => {
      const req = http.request({host: '127.0.0.1', port: 5678, method,
        path: route, headers, agent: false}, res => {
        const chunks = [];
        let length = 0;
        res.on('data', chunk => {
          length += chunk.length;
          if (length > 1024 * 1024) {
            res.destroy();
            reject(new ProbeFailure('RESPONSE_TOO_LARGE'));
          } else chunks.push(chunk);
        });
        res.on('error', () => reject(new ProbeFailure('RESPONSE_TRANSPORT')));
        res.on('end', () => {
          try {
            check(res.statusCode !== 429, 'UNEXPECTED_RATE_LIMIT');
            check((res.headers['content-type'] || '').startsWith('application/json'),
              'REST_RESPONSE_NOT_JSON');
            const parsed = JSON.parse(Buffer.concat(chunks).toString('utf8'));
            check(parsed && typeof parsed === 'object', 'REST_RESPONSE_SHAPE');
            const authCookies = (res.headers['set-cookie'] || [])
              .filter(value => value.startsWith('n8n-auth='));
            check(authCookies.length <= 1, 'AMBIGUOUS_AUTH_COOKIE');
            if (authCookies.length) {
              const value = authCookies[0];
              const pair = value.split(';', 1)[0];
              if (pair !== 'n8n-auth=') {
                check(/;\s*Secure(?:;|$)/i.test(value) &&
                  /;\s*HttpOnly(?:;|$)/i.test(value) &&
                  /;\s*SameSite=(?:Lax|Strict)(?:;|$)/i.test(value) &&
                  /;\s*Path=\/(?:;|$)/i.test(value), 'COOKIE_ATTRIBUTES');
                issuedCookies++;
                current.cookie = pair;
              } else current.cookie = '';
            }
            resolve({status: res.statusCode, body: parsed});
          } catch (error) { reject(error); }
        });
      });
      req.setTimeout(15000, () => req.destroy());
      req.on('error', () => reject(new ProbeFailure('REQUEST_TRANSPORT')));
      req.end(body);
    });
  }
  async function protectedRoute(current, expected, label) {
    // Never let a rejection's clear-cookie header erase the captured token.
    // The second replay after re-enrollment must send the SAME old cookie.
    const response = await request('GET', '/rest/workflows', saved(current));
    check(response.status === expected, label);
    if (expected === 200) {
      check(response.body.count === 0 && Array.isArray(response.body.data) &&
        response.body.data.length === 0, 'UNEXPECTED_WORKFLOW');
    }
  }
  async function settings(current) {
    const response = await request('GET', '/rest/settings', current);
    check(response.status === 200 && response.body.data?.mfa?.enabled === true &&
      response.body.data.mfa.enforced === false &&
      response.body.data.enterprise?.mfaEnforcement === false,
      'EFFECTIVE_LICENSE_OR_ENFORCEMENT_CHANGED');
  }
  async function identity(current, mfaEnabled) {
    const response = await request('GET', '/rest/login', current);
    check(response.status === 200 && response.body.data?.email === email &&
      response.body.data.mfaEnabled === mfaEnabled &&
      response.body.data.mfaAuthenticated === mfaEnabled, 'SESSION_IDENTITY_OR_MFA');
  }
  async function enroll(current) {
    const response = await request('GET', '/rest/mfa/qr', current);
    check(response.status === 200, 'QR_REQUEST');
    const prepared = response.body.data;
    check(typeof prepared?.secret === 'string' && typeof prepared.qrCode === 'string' &&
      prepared.qrCode.startsWith('otpauth://totp/') &&
      Array.isArray(prepared.recoveryCodes) && prepared.recoveryCodes.length === 10 &&
      prepared.recoveryCodes.every(value => typeof value === 'string' && value.length > 10) &&
      new Set(prepared.recoveryCodes).size === 10, 'ENROLLMENT_MATERIAL');
    check(OTPAuth.URI.parse(prepared.qrCode).secret.base32 === prepared.secret,
      'AUTHENTICATOR_URI_BINDING');
    check((await request('POST', '/rest/mfa/verify', current,
      {mfaCode: token(prepared.secret)})).status === 200, 'VERIFY_NATIVE_TOTP');
    check((await request('POST', '/rest/mfa/enable', current,
      {mfaCode: token(prepared.secret)})).status === 200, 'ENABLE_NATIVE_TOTP');
    await identity(current, true);
    await protectedRoute(current, 200, 'ENROLLED_SESSION_DENIED');
    return prepared;
  }

  await protectedRoute(session(), 401, 'ANONYMOUS_PROTECTED_ROUTE_ACCEPTED');
  check((await request('GET', '/rest/mfa/qr', session())).status === 401,
    'ANONYMOUS_ENROLLMENT_ACCEPTED');
  const email = 'p30-' + crypto.randomUUID() + '@example.invalid';
  let password = 'P30!aA1-' + crypto.randomBytes(24).toString('hex');
  const operator = session();
  const setup = await request('POST', '/rest/owner/setup', operator,
    {email, firstName: 'P30', lastName: 'SyntheticOperator', password});
  check(setup.status === 200 && setup.body.data?.email === email &&
    setup.body.data.firstName === 'P30' && setup.body.data.lastName === 'SyntheticOperator' &&
    operator.cookie, 'NOMINAL_OWNER_SETUP');
  await protectedRoute(operator, 200, 'INITIAL_OWNER_SESSION_DENIED');
  await settings(operator);
  const preEnrollment = saved(operator);
  const first = await enroll(operator);
  await protectedRoute(preEnrollment, 401, 'PRE_ENROLLMENT_COOKIE_ACCEPTED');

  // Exactly five login requests: no factor, bad factor, TOTP, recovery, replay.
  // Do not disable the native five-per-email-per-minute limiter to run a test.
  const credentials = {emailOrLdapLoginId: email, password};
  const missing = await request('POST', '/rest/login', session(), credentials);
  check(missing.status === 401 && missing.body.code === 998, 'MFA_NOT_REQUIRED_AFTER_ENROLLMENT');
  check((await request('POST', '/rest/login', session(),
    {...credentials, mfaCode: 'SYNTHETIC_INVALID_TOTP'})).status === 401,
    'INVALID_TOTP_ACCEPTED');
  const totpSession = session();
  const login = await request('POST', '/rest/login', totpSession,
    {...credentials, mfaCode: token(first.secret)});
  check(login.status === 200 && login.body.data?.mfaEnabled === true &&
    login.body.data.mfaAuthenticated === true && totpSession.cookie, 'VALID_TOTP_DENIED');
  await protectedRoute(totpSession, 200, 'TOTP_SESSION_DENIED');
  await protectedRoute({...saved(totpSession), browserId: crypto.randomUUID()}, 401,
    'SESSION_BROWSER_BINDING_MISSING');
  await protectedRoute(totpSession, 200, 'ORIGINAL_BROWSER_SESSION_DENIED');

  const recoverySession = session();
  const recovery = await request('POST', '/rest/login', recoverySession,
    {...credentials, mfaRecoveryCode: first.recoveryCodes[0]});
  check(recovery.status === 200 && recovery.body.data?.mfaEnabled === true &&
    recovery.body.data.mfaAuthenticated === true, 'RECOVERY_LOGIN_DENIED');
  await protectedRoute(recoverySession, 200, 'RECOVERY_SESSION_DENIED');
  check((await request('POST', '/rest/login', session(),
    {...credentials, mfaRecoveryCode: first.recoveryCodes[0]})).status === 401,
    'RECOVERY_CODE_REPLAY_ACCEPTED');
  const loggedOut = saved(recoverySession);
  const logout = await request('POST', '/rest/logout', recoverySession);
  check(logout.status === 200 && logout.body.data?.loggedOut === true, 'NATIVE_LOGOUT_FAILED');
  await protectedRoute(loggedOut, 401, 'LOGGED_OUT_COOKIE_ACCEPTED');
  await protectedRoute(totpSession, 200, 'LOGOUT_REVOKED_DIFFERENT_SESSION');

  check((await request('POST', '/rest/mfa/enforce-mfa', totpSession,
    {enforce: true})).status === 200, 'UNLICENSED_ENFORCEMENT_REQUEST_CHANGED');
  await settings(totpSession);

  const beforePasswordRotation = saved(operator);
  const otherBeforeRotation = saved(totpSession);
  const nextPassword = 'P30!bB2-' + crypto.randomBytes(24).toString('hex');
  const rotation = await request('PATCH', '/rest/me/password', operator,
    {currentPassword: password, newPassword: nextPassword, mfaCode: token(first.secret)});
  check(rotation.status === 200 && rotation.body.data?.success === true,
    'NATIVE_PASSWORD_ROTATION_FAILED');
  password = nextPassword;
  await protectedRoute(beforePasswordRotation, 401, 'PRE_ROTATION_SESSION_ACCEPTED');
  await protectedRoute(otherBeforeRotation, 401, 'OTHER_PRE_ROTATION_SESSION_ACCEPTED');
  await protectedRoute(operator, 200, 'POST_ROTATION_SESSION_DENIED');

  const preDisable = saved(operator);
  check((await request('POST', '/rest/mfa/disable', operator,
    {mfaCode: token(first.secret)})).status === 200, 'VOLUNTARY_DISABLE_FAILED');
  await identity(operator, false);
  await protectedRoute(operator, 200, 'VOLUNTARY_UNENROLLED_SESSION_DENIED');
  await protectedRoute(preDisable, 401, 'PRE_DISABLE_COOKIE_ACCEPTED');
  const second = await enroll(operator);
  check(second.secret !== first.secret, 'REENROLLMENT_REUSED_SECRET');
  await protectedRoute(preDisable, 401, 'OLD_MFA_SESSION_RESURRECTED');
  await identity(operator, true);
  await settings(operator);
  check(loginRequests === 5 && issuedCookies >= 7, 'SESSION_PROOF_INCOMPLETE');

  process.stdout.write(JSON.stringify({
    schema: 'P30_NATIVE_MFA_CLIENT_V1',
    nominal_synthetic_owner_setup: true,
    anonymous_protected_route_denied: true,
    anonymous_enrollment_denied: true,
    native_totp_enrollment_verified: true,
    pre_enrollment_session_invalidated: true,
    password_only_login_denied_after_enrollment: true,
    invalid_totp_denied: true,
    valid_totp_login_verified: true,
    session_browser_binding_verified: true,
    recovery_code_single_use_verified: true,
    logout_replayed_cookie_denied: true,
    logout_preserves_other_session: true,
    password_rotation_invalidates_both_prior_sessions: true,
    voluntary_disable_verified: true,
    pre_disable_session_revoked: true,
    pre_disable_session_stays_revoked_after_reenrollment: true,
    reenrollment_has_new_secret: true,
    final_native_mfa_session_verified: true,
    secure_httponly_samesite_cookie_attributes: true,
    unlicensed_enforcement_attempt_observed: true,
    login_requests: loginRequests,
    mfa_enforcement_licensed: false,
    mfa_enforced_instance_wide: false
  }));
}
main().catch(error => {
  process.stdout.write(JSON.stringify({schema: 'P30_NATIVE_MFA_FAILURE_V1',
    code: error instanceof ProbeFailure ? error.code : 'P30_UNEXPECTED_CLIENT_FAILURE'}));
  process.exitCode = 1;
});
"""


def main() -> dict:
    require(os.environ.get("GITHUB_ACTIONS") == "true" and
            os.environ.get("GITHUB_REPOSITORY") == "rafhael100/imagine-ai-agents" and
            os.environ.get("P30_RUNNER_ENVIRONMENT") == "github-hosted", "P30_HOSTED_CI_ONLY")
    require(not os.environ.get("DOCKER_HOST") and not os.environ.get("DOCKER_CONTEXT"),
            "P30_REMOTE_DOCKER_FORBIDDEN")
    require(run(["docker", "context", "show"], "P30_DOCKER_CONTEXT").stdout.strip() == b"default",
            "P30_DEFAULT_DOCKER_CONTEXT_REQUIRED")
    context = json.loads(run(["docker", "context", "inspect", "default"],
                             "P30_DOCKER_CONTEXT").stdout)
    require(context[0]["Endpoints"]["docker"]["Host"] == "unix:///var/run/docker.sock",
            "P30_LOCAL_DOCKER_REQUIRED")
    run_id = os.environ.get("GITHUB_RUN_ID", "")
    attempt = os.environ.get("GITHUB_RUN_ATTEMPT", "")
    require(re.fullmatch(r"[0-9]{1,20}", run_id) is not None and
            re.fullmatch(r"[0-9]{1,4}", attempt) is not None, "P30_RUN_ID_INVALID")
    pg_image = os.environ.get("N8N_PG_IMAGE", "")
    require(re.fullmatch(r"(?:docker.io/library/)?postgres@sha256:[a-f0-9]{64}", pg_image) is not None,
            "P30_POSTGRES_DIGEST_REQUIRED")
    os.umask(0o077)
    with tempfile.TemporaryDirectory(prefix="central-p30-", dir=os.environ["RUNNER_TEMP"]) as tmp:
        stack = Stack("imagine-p30-" + run_id + "-" + attempt, Path(tmp) / "secrets", pg_image)
        try:
            stack.claim_empty()
            print("P30_STAGE_PRIVATE_N8N_BOOT", flush=True)
            stack.dc("up", "-d", "--wait", "--wait-timeout", "180", timeout=240)
            stack.inspect_runtime()
            require(stack.dc("exec", "-T", "n8n", "n8n", "--version",
                             label="P30_VERSION").stdout.strip() == N8N_VERSION.encode(),
                    "P30_RUNTIME_VERSION_CHANGED")
            for table in ("workflow_entity", "credentials_entity", "execution_entity"):
                require(stack.sql("SELECT count(*) FROM " + table) == "0",
                        "P30_FRESH_INSTANCE_REQUIRED")
            print("P30_STAGE_NATIVE_MFA_AND_SESSION_ROUTES", flush=True)
            outcome = stack.dc("exec", "-T", "n8n", "node", "-", stdin=CLIENT.encode(),
                               label="P30_NATIVE_CLIENT", timeout=180, check=False)
            proof = json.loads(outcome.stdout)
            if outcome.returncode:
                code = proof.get("code", "") if isinstance(proof, dict) else ""
                require(isinstance(code, str) and re.fullmatch(r"P30_[A-Z0-9_]{1,96}", code) is not None,
                        "P30_CLIENT_FAILED_WITHOUT_SAFE_CODE")
                raise Failure(code)
            require(isinstance(proof, dict) and set(proof) == set(PROOFS) | {
                "schema", "login_requests", "mfa_enforcement_licensed", "mfa_enforced_instance_wide",
            }, "P30_CLOSED_CLIENT_REPORT")
            require(proof["schema"] == "P30_NATIVE_MFA_CLIENT_V1" and
                    all(proof[name] is True for name in PROOFS) and proof["login_requests"] == 5 and
                    proof["mfa_enforcement_licensed"] is False and
                    proof["mfa_enforced_instance_wide"] is False, "P30_CLIENT_PROOF_INCOMPLETE")
            require(stack.sql("SELECT value FROM settings WHERE key='mfa.enforced'") == "false",
                    "P30_PERSISTED_ENFORCEMENT_NOT_FALSE")
            require(stack.sql('SELECT count(*) FROM "user"') == "1" and
                    stack.sql('SELECT count(*) FROM "user" WHERE kind=\'person\' '
                              'AND "firstName"=\'P30\' AND "lastName"=\'SyntheticOperator\' '
                              'AND "mfaEnabled" AND NOT disabled AND password IS NOT NULL '
                              'AND email LIKE \'p30-%@example.invalid\'') == "1",
                    "P30_NOMINAL_MFA_OPERATOR_NOT_DURABLE")
            require(stack.sql('SELECT count(*) FROM invalid_auth_token WHERE "expiresAt">now()') == "1",
                    "P30_LOGOUT_INVALIDATION_NOT_DURABLE")
            for table in ("workflow_entity", "credentials_entity", "execution_entity"):
                require(stack.sql("SELECT count(*) FROM " + table) == "0", "P30_UNEXPECTED_WORKFLOW_STATE")
            print("P30_STAGE_NATIVE_MFA_AND_REVOCATION_PASS", flush=True)
            return {
                **{name: proof[name] for name in PROOFS},
                "schema": "CENTRAL_AUTO_P30_NATIVE_OPERATOR_MFA_V1",
                "n8n_image": N8N_IMAGE,
                "n8n_version_verified": N8N_VERSION,
                "inspected_official_source_commit": SOURCE_COMMIT,
                "authenticator": "otpauth_from_pinned_n8n_image",
                "synthetic_nominal_operators": 1,
                "native_login_requests": 5,
                "durable_logout_invalidations": 1,
                "mfa_enforcement_licensed": False,
                "mfa_enforced_instance_wide": False,
                "persisted_enforcement_false_verified": True,
                "mandatory_mfa_policy_gate_closed": False,
                "transport": "http_loopback_inside_private_n8n_container",
                "host_published_ports": 0,
                "runtime_networks_internal": True,
                "browser_https_mtls_rehearsed": False,
                "workflows_created": 0,
                "workflow_execution_requested": False,
                "ai_or_external_calls_requested": False,
                "human_credentials_used": False,
                "license_activation_requested": False,
                "go_for_installation": False,
                "decision": "NO_GO",
            }
        finally:
            stack.cleanup()


if __name__ == "__main__":
    try:
        print(json.dumps(main(), sort_keys=True))
    except Failure as exc:
        raise SystemExit("P30_DENY " + str(exc)) from None
    except (OSError, ValueError, KeyError, TypeError, subprocess.SubprocessError):
        raise SystemExit("P30_DENY_UNEXPECTED_LOCAL_OR_RUNTIME_FAILURE") from None
