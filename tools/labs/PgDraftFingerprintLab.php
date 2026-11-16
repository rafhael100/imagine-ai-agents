<?php
declare(strict_types=1);
namespace ImagineAI\Convergence;

/**
 * P52 CI-only hash of the exact draft identity and projected values.
 *
 * This checksum detects accidental or unsupervised changes in a DB row; it
 * is NOT a signature, HMAC, external audit witness, durable owner consent,
 * identity proof or production-grade immutability guarantee.
 */
final class PgDraftFingerprintLab
{
    public static function sha256(
        string $tenant,string $request,string $ruleset,int $revision,
        string $proposal,string $rule,string $kind,string $metric,
        int $observed,int $threshold,string $state
    ):string {
        return hash('sha256',json_encode([
            'schema'=>'AIA_P52_DRAFT_FINGERPRINT_V1',
            'tenant_ref'=>$tenant,
            'request_ref'=>$request,
            'ruleset_ref'=>$ruleset,
            'revision'=>$revision,
            'proposal_ref'=>$proposal,
            'rule_ref'=>$rule,
            'draft_kind'=>$kind,
            'metric'=>$metric,
            'observed'=>$observed,
            'threshold'=>$threshold,
            'state'=>$state
        ],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES
            |JSON_UNESCAPED_UNICODE));
    }
}
