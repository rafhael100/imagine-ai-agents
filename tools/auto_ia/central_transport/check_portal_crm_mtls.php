<?php
declare(strict_types=1);

/**
 * CENTRAL-AUTO-P5 — real PHP Portal class consumes state via actual mTLS
 * to a separate local Python lab server. NO production CRM HTTP endpoint.
 */
require_once __DIR__.'/../../../_portal_contract_pin/apps/portal/app/Domain/AutomationAdministration/AutomationStateReader.php';
use App\Domain\AutomationAdministration\AutomationStateReader;

if (getenv('GITHUB_ACTIONS')!=='true' ||
    getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents') {
    throw new RuntimeException('CI_ONLY');
}
const TEST_SECRET='SYNTHETIC_CENTRAL_AUTO_P5_ONLY_TEST_KEY_NOT_REAL';
const COMPANY_A='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
const COMPANY_B='bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
const TEST_ISSUER='portal-automation-admin';
const TEST_AUDIENCE='crm-automation-state';
$certDir=(string)getenv('CENTRAL_AUTO_P5_CERT_DIR');
$checks=0;$seq=0;
function ok(bool $pass,string $name):void {
    global $checks; $checks++;
    if (!$pass) throw new RuntimeException('CENTRAL_AUTO_P5_FAIL_'.$name);
}
function denied(callable $fn,string $label):void {
    global $checks;
    try { $fn(); } catch (\Throwable) { $checks++; return; }
    throw new RuntimeException('CENTRAL_AUTO_P5_NOT_DENIED_'.$label);
}
function requestFixture(string $company=COMPANY_A,array $changes=[]):array {
    global $seq; $seq++;
    $req=[
        'schema'=>'CENTRAL_AUTO_MTLS_REQUEST_V1',
        'issuer'=>TEST_ISSUER,
        'audience'=>TEST_AUDIENCE,
        'company_id'=>$company,
        'request_id'=>'SYNTHETIC_REQUEST_'.$seq,
        'nonce'=>'SYNTHETIC_NONCE_'.$seq,
        'issued_at'=>time(),
        'mac'=>'',
    ];
    foreach($changes as $name=>$value)$req[$name]=$value;
    $req['mac']=hash_hmac('sha256',implode('|',array_map(
        'strval',array_values(array_diff_key($req,['mac'=>true]))
    )),TEST_SECRET);
    return $req;
}
/** @return array{status:int,body:?array} */
function postRequest(array $request,?string $clientName='portal',?string $caName='ca.crt'):array {
    global $certDir;
    $ssl=[
        'verify_peer'=>true,'verify_peer_name'=>true,
        'peer_name'=>'localhost',
        'cafile'=>$certDir.'/'.$caName,
        'allow_self_signed'=>false,
    ];
    if ($clientName!==null) {
        $ssl['local_cert']=$certDir.'/'.$clientName.'.crt';
        $ssl['local_pk']=$certDir.'/'.$clientName.'.key';
    }
    $ctx=stream_context_create([
        'ssl'=>$ssl,
        'http'=>[
            'method'=>'POST',
            'header'=>"Content-Type: application/json\r\nConnection: close\r\n",
            'content'=>json_encode($request,JSON_THROW_ON_ERROR),
            'timeout'=>5,
            'ignore_errors'=>true,
        ],
    ]);
    $raw=@file_get_contents('https://localhost:18787/private/automation-state',false,$ctx);
    if ($raw===false) throw new RuntimeException('TLS_OR_NETWORK_REJECTED');
    $status=0;
    foreach(($http_response_header??[]) as $line) {
        if (preg_match('/^HTTP\/\S+\s+([0-9]{3})\b/',$line,$m)) {
            $status=(int)$m[1];
            break;
        }
    }
    $obj=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
    if (!is_array($obj)) throw new RuntimeException('TRANSPORT_NOT_JSON_OBJECT');
    return ['status'=>$status,'body'=>$obj];
}
function unpackState(array $response,array $sent):array {
    if ($response['status']!==200) throw new RuntimeException('TRANSPORT_NOT_AUTHORIZED');
    $out=$response['body'];
    $keys=array_keys($out);sort($keys);
    if ($keys!==['body_b64','company_id','mac','nonce','request_id','schema'] ||
        $out['schema']!=='CENTRAL_AUTO_MTLS_RESPONSE_V1' ||
        $out['request_id']!==$sent['request_id'] ||
        $out['nonce']!==$sent['nonce'] ||
        $out['company_id']!==$sent['company_id'] ||
        !is_string($out['body_b64']) ||
        !is_string($out['mac']) ||
        preg_match('/\A[a-f0-9]{64}\z/D',$out['mac'])!==1) {
        throw new RuntimeException('TRANSPORT_CLOSED_SCHEMA_DENIED');
    }
    $mac=hash_hmac('sha256',implode('|',array_map(
        'strval',array_values(array_diff_key($out,['mac'=>true]))
    )),TEST_SECRET);
    // The MAC order is explicit and must not depend on object key ordering.
    $canonical=implode('|',array_map(
        fn($key)=>(string)$out[$key],
        ['schema','request_id','nonce','company_id','body_b64']
    ));
    $expected=hash_hmac('sha256',$canonical,TEST_SECRET);
    if(!hash_equals($expected,$out['mac']))throw new RuntimeException('TRANSPORT_RESPONSE_MAC_DENIED');
    $raw=base64_decode($out['body_b64'],true);
    if ($raw===false || strlen($raw)>8192) throw new RuntimeException('TRANSPORT_RESPONSE_SIZE');
    $state=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
    if (!is_array($state))throw new RuntimeException('TRANSPORT_RESPONSE_BODY');
    return $state;
}

// Actual Portal reader invokes a different TLS server process. A local grant
// is a synthetic fixture and intentionally not derived from browser identity.
$grantActive=true;$grantCalls=0;$sourceCalls=0;
$grant=function(int $admin,string $company)use(&$grantActive,&$grantCalls):bool {
    $grantCalls++;
    return $grantActive && $admin===7 && $company===COMPANY_A;
};
$source=function(string $company)use(&$sourceCalls):array {
    $sourceCalls++;
    $req=requestFixture($company);
    return unpackState(postRequest($req),$req);
};
$reader=new AutomationStateReader($source,$grant);
$accepted=$reader->read(7,COMPANY_A);
ok($accepted['schema']==='CRM_AUTOMATION_STATE_V1' &&
   $accepted['counts']['completed']===1 &&
   $accepted['ai_provider_calls']===0 &&
   $accepted['operational_authorization']===false,
   'original_portal_reader_verified_https_state');
ok($grantCalls===2 && $sourceCalls===1,'scope_checked_before_and_after_tls_read');

$grantActive=false;
denied(fn()=>$reader->read(7,COMPANY_A),'revoked_before_http');
ok($sourceCalls===1,'revocation_skips_network');

$grantActive=true;
denied(fn()=>$reader->read(7,COMPANY_B),'foreign_company_denied_before_http');
ok($sourceCalls===1,'tenant_not_sent_to_transport');
$grantActive=true;$auth=0;
$unstableReader=new AutomationStateReader(
    function(string $company)use(&$grantActive,$source) {
        $result=$source($company);
        $grantActive=false;
        return $result;
    },
    $grant
);
denied(fn()=>$unstableReader->read(7,COMPANY_A),'revoked_during_real_tls');
$grantActive=true;

// Transport authentication, company and replay controls independently tested.
$req=requestFixture();
$first=postRequest($req);
ok($first['status']===200,'signed_http_transport_first');
ok(postRequest($req)['status']===409,'nonce_replay_denied');
$wrongTenant=requestFixture(COMPANY_B);
ok(postRequest($wrongTenant)['status']===403,'signed_but_other_company_denied');
$wrongAudience=requestFixture(COMPANY_A,['audience'=>'other-service']);
ok(postRequest($wrongAudience)['status']===403,'signed_wrong_audience_denied');
$expired=requestFixture(COMPANY_A,['issued_at'=>time()-180]);
ok(postRequest($expired)['status']===403,'expired_request_denied');
$badMac=requestFixture();$badMac['company_id']=COMPANY_B;
ok(postRequest($badMac)['status']===403,'modified_company_mac_denied');
$rogue=requestFixture();
ok(postRequest($rogue,'rogue')['status']===403,'trusted_ca_but_wrong_client_identity_denied');
denied(fn()=>postRequest(requestFixture(),null),'client_cert_required');
denied(fn()=>postRequest(requestFixture(),'portal','rogue-ca.crt'),'wrong_ca_denied');

// Signed response must bind body and request, not only TLS channel.
$valid=requestFixture();
$reply=postRequest($valid);
ok(unpackState($reply,$valid)['company_id']===COMPANY_A,'signed_response_bound');
$modified=$reply;$modified['body']['body_b64']=base64_encode('{"fake":"data"}');
denied(fn()=>unpackState($modified,$valid),'modified_response_body');
$modified=$reply;$modified['body']['request_id']='SYNTHETIC_UNRELATED';
denied(fn()=>unpackState($modified,$valid),'replaced_request_id');

echo "CENTRAL_AUTO_P5_MTLS_PASS checks={$checks} original_portal_reader=1 original_crm_classes=1 trusted_cert_only=1 no_ai=1 no_billing=1 no_vps=1 NO_GO\n";
