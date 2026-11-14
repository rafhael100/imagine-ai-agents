<?php
declare(strict_types=1);

/**
 * F231: Ed25519 detached signature verification with public SYNTHETIC
 * fixture keys. No real secret, transport, CRM route or client data.
 */
use ImagineAI\MockBridge\{MockCrmReadRequest, MockServiceIdentityPreflight};

$root = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'ImagineAI\\';
    if (str_starts_with($class, $prefix)) {
        require $root . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});
function f231Check(bool $ok): void {
    if (!$ok) throw new RuntimeException('F231_DENIED');
}
function f231Wire(string $tenant): string {
    return MockCrmReadRequest::encodeCanonicalJson([
        'contract' => 'SYNTHETIC_CRM_AIA_READ_V1',
        'request_ref' => 'SYNTHETIC_F231_REQUEST',
        'operation' => 'crm.lead.read_minimal',
        'tenant_ref' => $tenant,
        'principal_ref' => 'SYNTHETIC_F231_USER',
        'resource_ref' => 'SYNTHETIC_F231_LEAD',
        'purpose_ref' => 'SYNTHETIC_F231_TEST',
        'fields' => ['ref', 'stage'],
    ]);
}
function f231Claims(string $request, string $tenant, string $nonce, string $keyId): array {
    return [
        'contract' => 'SYNTHETIC_AIA_SERVICE_IDENTITY_V1',
        'issuer' => 'SYNTHETIC_AGENT_SERVICE',
        'audience' => 'SYNTHETIC_CRM_API',
        'key_id' => $keyId,
        'subject' => 'SYNTHETIC_AGENTS_READ_ONLY',
        'tenant_ref' => $tenant,
        'operation' => 'crm.lead.read_minimal',
        'nonce' => $nonce,
        'issued_at' => 1794690000,
        'expires_at' => 1794690020,
        'request_sha256' => hash('sha256', $request),
    ];
}
function f231Sign(array $claims, string $privateKey): array {
    $bytes = json_encode(
        $claims, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
    $sig = sodium_crypto_sign_detached(
        "SYNTHETIC_AIA_SERVICE_ED25519_V1\n" . $bytes,
        $privateKey
    );
    return [$bytes, base64_encode($sig)];
}
try {
    f231Check(function_exists('sodium_crypto_sign_detached')
        && function_exists('sodium_crypto_sign_verify_detached'));
    // Public test vectors derived from a published deterministic seed.
    // NEVER use these as service credentials or keep real private keys in Git.
    $kpA = sodium_crypto_sign_seed_keypair(hash('sha256', 'PUBLIC_F231_TEST_SEED_A', true));
    $kpB = sodium_crypto_sign_seed_keypair(hash('sha256', 'PUBLIC_F231_TEST_SEED_B', true));
    $private = sodium_crypto_sign_secretkey($kpA);
    $public = sodium_crypto_sign_publickey($kpA);
    $publicB = sodium_crypto_sign_publickey($kpB);
    $kid = 'SYNTHETIC_F231_KID_A';
    $trusted = [$kid => ['public_key' => $public, 'enabled' => true]];
    $tenants = ['SYNTHETIC_TENANT_A','SYNTHETIC_TENANT_B','SYNTHETIC_TENANT_C'];
    $allowed = 0;
    foreach ($tenants as $idx => $tenant) {
        $request = f231Wire($tenant);
        $claims = f231Claims($request, $tenant, 'SYNTHETIC_F231_NONCE_'.($idx+1), $kid);
        [$body, $signature] = f231Sign($claims, $private);
        $guard = new MockServiceIdentityPreflight();
        f231Check($guard->verifyOnce($request, $body, $signature, $trusted, $tenant, 1794690010));
        f231Check(!$guard->verifyOnce($request, $body, $signature, $trusted, $tenant, 1794690010));
        f231Check($guard->countConsumed() === 1);
        ++$allowed;
    }

    $tenant = $tenants[0];
    $req = f231Wire($tenant);
    $claims = f231Claims($req, $tenant, 'SYNTHETIC_F231_BASE_NONCE', $kid);
    [$body,$sig] = f231Sign($claims,$private);

    // Independently signed invalid claims cannot bypass identity checks.
    $resigned = static function(array $changes) use ($claims,$private): array {
        return f231Sign(array_replace($claims,$changes),$private);
    };
    $wrongTenant = $resigned(['tenant_ref' => $tenants[1]]);
    $wrongOperation = $resigned(['operation' => 'crm.lead.write']);
    $wrongAudience = $resigned(['audience' => 'SYNTHETIC_OTHER']);
    $wrongIssuer = $resigned(['issuer' => 'SYNTHETIC_OTHER']);
    $wrongSubject = $resigned(['subject' => 'SYNTHETIC_ADMIN']);
    $expired = $resigned(['expires_at' => 1794690000]);
    $longTtl = $resigned(['expires_at' => 1794690031]);
    $badDigest = $resigned(['request_sha256' => str_repeat('0',64)]);
    $unknownKey = $resigned(['key_id' => 'SYNTHETIC_F231_UNKNOWN']);
    $extra = $claims; $extra['is_admin'] = true;
    $extraSeal = f231Sign($extra, $private);
    $reordered = f231Sign(array_reverse($claims,true),$private);
    $wrongSignature = f231Sign($claims,sodium_crypto_sign_secretkey($kpB));
    $otherReq = f231Wire($tenants[1]);
    $badJson = substr($body,0,-1) . ',"nonce":"SYNTHETIC_F231_BASE_NONCE"}';
    $trailing = $body."\n";
    $malformed = '$' . $sig;
    // Tuple: label, request, bytes, signature, trusted keys, tenant, clock.
    $bad = [
        ['CROSS_TENANT', $req,$body,$sig,$trusted,$tenants[1],1794690010],
        ['REQUEST_SWAP', $otherReq,$body,$sig,$trusted,$tenant,1794690010],
        ['WRONG_TENANT_CLAIM', $req,$wrongTenant[0],$wrongTenant[1],$trusted,$tenant,1794690010],
        ['WRONG_OPERATION', $req,$wrongOperation[0],$wrongOperation[1],$trusted,$tenant,1794690010],
        ['WRONG_AUDIENCE', $req,$wrongAudience[0],$wrongAudience[1],$trusted,$tenant,1794690010],
        ['WRONG_ISSUER', $req,$wrongIssuer[0],$wrongIssuer[1],$trusted,$tenant,1794690010],
        ['WRONG_SUBJECT', $req,$wrongSubject[0],$wrongSubject[1],$trusted,$tenant,1794690010],
        ['EXPIRED_CLAIM', $req,$expired[0],$expired[1],$trusted,$tenant,1794690010],
        ['LONG_TTL', $req,$longTtl[0],$longTtl[1],$trusted,$tenant,1794690010],
        ['DIGEST_TAMPER', $req,$badDigest[0],$badDigest[1],$trusted,$tenant,1794690010],
        ['UNKNOWN_KID', $req,$unknownKey[0],$unknownKey[1],$trusted,$tenant,1794690010],
        ['EXTRA_CLAIM', $req,$extraSeal[0],$extraSeal[1],$trusted,$tenant,1794690010],
        ['REORDERED_CLAIMS', $req,$reordered[0],$reordered[1],$trusted,$tenant,1794690010],
        ['INVALID_SIGNER', $req,$wrongSignature[0],$wrongSignature[1],$trusted,$tenant,1794690010],
        ['WRONG_PUBLIC_KEY', $req,$body,$sig,[$kid=>['public_key'=>$publicB,'enabled'=>true]],$tenant,1794690010],
        ['DISABLED_KEY', $req,$body,$sig,[$kid=>['public_key'=>$public,'enabled'=>false]],$tenant,1794690010],
        ['NO_TRUSTED_KEYS', $req,$body,$sig,[],$tenant,1794690010],
        ['MALFORMED_BASE64', $req,$body,$malformed,$trusted,$tenant,1794690010],
        ['DUPLICATE_JSON_KEY', $req,$badJson,$sig,$trusted,$tenant,1794690010],
        ['TRAILING_BYTES', $req,$trailing,$sig,$trusted,$tenant,1794690010],
        ['BEFORE_ISSUE', $req,$body,$sig,$trusted,$tenant,1794689999],
        ['AFTER_EXPIRY', $req,$body,$sig,$trusted,$tenant,1794690020],
        ['OVERSIZE_REQUEST', str_repeat('X',1025),$body,$sig,$trusted,$tenant,1794690010],
        ['OVERSIZE_CLAIMS', $req,str_repeat('X',1025),$sig,$trusted,$tenant,1794690010],
    ];
    f231Check(count($bad) === 24);
    $denied = 0;
    foreach ($bad as [$label,$request,$claimsJson,$signature,$keys,$company,$clock]) {
        $fresh = new MockServiceIdentityPreflight();
        f231Check(!$fresh->verifyOnce($request,$claimsJson,$signature,$keys,$company,$clock));
        f231Check($fresh->countConsumed() === 0);
        ++$denied;
    }
    $guard = new MockServiceIdentityPreflight();
    $guard->setAvailable(false);
    f231Check(!$guard->verifyOnce($req,$body,$sig,$trusted,$tenant,1794690010));
    f231Check($guard->countConsumed() === 0);
    $guard->setAvailable(true);
    f231Check($guard->verifyOnce($req,$body,$sig,$trusted,$tenant,1794690010));
    f231Check(!$guard->verifyOnce($req,$body,$sig,$trusted,$tenant,1794690010));
    f231Check($guard->countConsumed() === 1);

    // Capacity and same-nonce cross-tenant replay checked independently.
    $limited = new MockServiceIdentityPreflight(1);
    $bClaims = f231Claims($otherReq,$tenants[1],'SYNTHETIC_F231_SECOND_NONCE',$kid);
    [$bJson,$bSig] = f231Sign($bClaims,$private);
    f231Check($limited->verifyOnce($req,$body,$sig,$trusted,$tenant,1794690010));
    f231Check(!$limited->verifyOnce($otherReq,$bJson,$bSig,$trusted,$tenants[1],1794690010));
    f231Check($limited->countConsumed() === 1);
    $sameNonce = f231Claims($otherReq,$tenants[1],'SYNTHETIC_F231_BASE_NONCE',$kid);
    [$sameJson,$sameSig] = f231Sign($sameNonce,$private);
    $shared = new MockServiceIdentityPreflight(3);
    f231Check($shared->verifyOnce($req,$body,$sig,$trusted,$tenant,1794690010));
    f231Check(!$shared->verifyOnce($otherReq,$sameJson,$sameSig,$trusted,$tenants[1],1794690010));
    f231Check($shared->countConsumed() === 1);

    f231Check($allowed===3 && $denied===24);
    echo "F231_ED25519_SERVICE_PREFLIGHT_PASS tenants=3 negatives=24 ".
         "operational_decision=NO_GO real_authorization=false external_calls=0\n";
} catch (\Throwable) {
    fwrite(STDERR, "F231_ED25519_SERVICE_PREFLIGHT_DENIED operational_decision=NO_GO\n");
    exit(1);
}
