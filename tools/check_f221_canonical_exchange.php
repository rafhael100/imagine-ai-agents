<?php
declare(strict_types=1);

/**
 * F221: actual PHP request bytes -> edge -> facade -> JSON response bytes.
 * No HTTP routes, CRM, IAM, service credentials, customer data or writes.
 */
use ImagineAI\Domain\TenantContext;
use ImagineAI\MockAudit\MockCrmReadAuditTrail;
use ImagineAI\MockBridge\{MockCrmOwnerPolicy,MockCrmReadPublicFacade,MockCrmReadRequest,MockCrmReadRequestBuilder,MockCrmPublicResponseContract};
use ImagineAI\MockTelemetry\{MockCrmReadEdgeBoundary,MockCrmReadEdgeCounters};
use ImagineAI\MockTrust\{MockTrustGateway,MockTrustEnvelope,MockOwnerScopeEvidence};
use ImagineAI\Mocks\MockCrmReader;

spl_autoload_register(static function (string $class): void {
    $prefix='ImagineAI\\';
    if (str_starts_with($class,$prefix)) {
        require dirname(__DIR__).'/src/'.str_replace(
            '\\','/',substr($class,strlen($prefix))).'.php';
    }
});
function f221Assert(bool $ok): void
{
    if (!$ok) throw new RuntimeException('F221_EXCHANGE_DENIED');
}
function f221Proof(TenantContext $context,string $suffix): array
{
    $env=new MockTrustEnvelope(
        'SYNTHETIC_F221_ENV_'.$suffix,'SYNTHETIC_AGENT_SERVICE',
        'SYNTHETIC_CRM_API',$context,'SYNTHETIC_F221_NONCE_'.$suffix,
        ['SYNTHETIC_LEAD_READ'],10,40
    );
    $owner=new MockOwnerScopeEvidence(
        'SYNTHETIC_F221_OWNER_'.$suffix,$env->digest,$context,
        'SYNTHETIC_LEAD_READ',10,40,true
    );
    return [$env,$owner];
}
function f221Fixture(string $suffix,bool $grant=true,
                     int $capacity=32): array
{
    $context=new TenantContext(
        'SYNTHETIC_CRM','SYNTHETIC_A','SYNTHETIC_PRINCIPAL',
        'SYNTHETIC_SUMMARY','SYNTHETIC_A_LEAD'
    );
    [$env,$owner]=f221Proof($context,$suffix);
    $policy=new MockCrmOwnerPolicy();
    if ($grant) $policy->grantFixture($context,['ref','stage']);
    $audit=new MockCrmReadAuditTrail();
    $counters=new MockCrmReadEdgeCounters($capacity);
    $facade=new MockCrmReadPublicFacade(
        new MockTrustGateway('SYNTHETIC_AGENT_SERVICE','SYNTHETIC_CRM_API',
            ['SYNTHETIC_LEAD_READ']),
        new MockCrmReader(),$policy,$audit
    );
    $edge=new MockCrmReadEdgeBoundary($facade,$counters);
    $wire=MockCrmReadRequestBuilder::fromContext(
        $context,'SYNTHETIC_F221_REQUEST',['ref']
    );
    return [$edge,$counters,$audit,$context,$env,$owner,$wire,$policy];
}
function f221Denied(): string
{
    return json_encode(MockCrmPublicResponseContract::denial(),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}
function f221Read(string $bytes,MockCrmReadRequest $request,
                  TenantContext $context): array
{
    $out=MockCrmPublicResponseContract::decodeCanonicalJson(
        $bytes,$request,$context);
    f221Assert(is_array($out));
    f221Assert($out['real_authorization']===false
        && $out['external_calls']===0);
    return $out;
}

try {
    // Positive request and exact response roundtrip with an ordered projection.
    [$edge,$counts,$audit,$ctx,$env,$owner,$wire]=f221Fixture('GOOD');
    $wire['fields']=['stage','ref'];
    $request=new MockCrmReadRequest($wire);
    $raw=MockCrmReadRequest::encodeCanonicalJson($wire);
    $response=$edge->exchangeCanonicalJson($raw,$env,$owner,$ctx,15);
    $view=f221Read($response,$request,$ctx);
    f221Assert($view['state']==='READ_SYNTHETIC_ONLY');
    f221Assert($view['projection']===
        ['stage'=>'SYNTHETIC_NEW','ref'=>'SYNTHETIC_A_LEAD']);
    f221Assert($edge->recheck($wire,$env,$owner,$ctx,16));
    f221Assert($counts->snapshot()['attempts']===1 && $audit->count()===1);

    // Replayed trust proof must produce one closed denial, no replay access.
    f221Assert($edge->exchangeCanonicalJson($raw,$env,$owner,$ctx,16)===f221Denied());
    f221Assert(!$edge->recheck($wire,$env,$owner,$ctx,17));
    f221Assert($counts->snapshot()['attempts']===2
        && $counts->snapshot()['outcomes']['DENIED_OR_NOT_FOUND']===1
        && $audit->count()===2);
    [$freshEnv,$freshOwner]=f221Proof($ctx,'RECOVER');
    $recover=f221Read($edge->exchangeCanonicalJson(
        $raw,$freshEnv,$freshOwner,$ctx,17),$request,$ctx);
    f221Assert($recover['state']==='READ_SYNTHETIC_ONLY');
    f221Assert($edge->recheck($wire,$freshEnv,$freshOwner,$ctx,18));
    f221Assert($audit->count()===3);

    // A raw duplicate JSON key is rejected at the edge, consumes one schema
    // telemetry slot, and cannot carry over an earlier positive receipt.
    $duplicate=substr($raw,0,-1).',"tenant_ref":"SYNTHETIC_A"}';
    f221Assert($edge->exchangeCanonicalJson(
        $duplicate,$freshEnv,$freshOwner,$ctx,18)===f221Denied());
    f221Assert(!$edge->recheck($wire,$freshEnv,$freshOwner,$ctx,19));
    f221Assert($counts->snapshot()['attempts']===4
        && $counts->snapshot()['outcomes']['SCHEMA_DENIED']===1
        && $audit->count()===3);

    // An explicit owner-resource denial is indistinguishable from schema and
    // replay in the public serialized denial, although internal counters differ.
    [$edge,$counts,$audit,$ctx,$env,$owner,$wire]=
        f221Fixture('OWNER',false);
    $raw=MockCrmReadRequest::encodeCanonicalJson($wire);
    f221Assert($edge->exchangeCanonicalJson($raw,$env,$owner,$ctx,15)===f221Denied());
    f221Assert($audit->count()===1
        && $counts->snapshot()['outcomes']['DENIED_OR_NOT_FOUND']===1);

    // No trust evidence must always deny, never infer ownership from a body.
    [$edge,$counts,$audit,$ctx,$env,$owner,$wire]=f221Fixture('NO_TRUST');
    $raw=MockCrmReadRequest::encodeCanonicalJson($wire);
    f221Assert($edge->exchangeCanonicalJson($raw,null,$owner,$ctx,15)===f221Denied());
    f221Assert($audit->count()===1);

    // Edge capacity exhaustion returns denial before another reader call.
    [$edge,$counts,$audit,$ctx,$env,$owner,$wire]=f221Fixture('CAPACITY',true,1);
    $raw=MockCrmReadRequest::encodeCanonicalJson($wire);
    f221Assert(f221Read($edge->exchangeCanonicalJson(
        $raw,$env,$owner,$ctx,15),new MockCrmReadRequest($wire),$ctx
    )['state']==='READ_SYNTHETIC_ONLY');
    f221Assert($edge->exchangeCanonicalJson($raw,$env,$owner,$ctx,16)===f221Denied());
    f221Assert(!$edge->recheck($wire,$env,$owner,$ctx,17));
    f221Assert($counts->snapshot()['attempts']===1
        && $audit->count()===1);

    echo 'F221_CANONICAL_EXCHANGE_PASS cases=6'
        .' operational_decision=NO_GO real_authorization=false'
        .' external_calls=0 write_calls=0'.PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR,"F221_CANONICAL_EXCHANGE_DENIED operational_decision=NO_GO\n");
    exit(1);
}
