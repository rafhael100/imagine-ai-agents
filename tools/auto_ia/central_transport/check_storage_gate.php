<?php
declare(strict_types=1);
/**
 * CENTRAL-AUTO-P8: a failed durable nonce write must yield HTTP 503, never
 * success. Restoring the grant admits the SAME nonce once and then denies replay.
 */
if (getenv('GITHUB_ACTIONS')!=='true'
 || getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents') {
 throw new RuntimeException('CI_ONLY');
}
$expect=(string)($argv[1]??'');
if (!in_array($expect,['503','200','409'],true)) {
 throw new RuntimeException('EXPECTED_STATUS_REQUIRED');
}
$dir=(string)getenv('CENTRAL_AUTO_P5_CERT_DIR');
$input=[
 'schema'=>'CENTRAL_AUTO_MTLS_REQUEST_V1',
 'issuer'=>'portal-automation-admin',
 'audience'=>'crm-automation-state',
 'company_id'=>'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
 'request_id'=>'SYNTHETIC_STORAGE_REQUEST',
 'nonce'=>'SYNTHETIC_STORAGE_NONCE',
 'issued_at'=>time(),
 'mac'=>'',
];
$parts=array_values(array_diff_key($input,['mac'=>true]));
$input['mac']=hash_hmac('sha256',implode('|',array_map('strval',$parts)),
 'SYNTHETIC_CENTRAL_AUTO_P5_ONLY_TEST_KEY_NOT_REAL');
$context=stream_context_create([
 'ssl'=>[
  'verify_peer'=>true,'verify_peer_name'=>true,'peer_name'=>'localhost',
  'cafile'=>$dir.'/ca.crt','local_cert'=>$dir.'/portal.crt',
  'local_pk'=>$dir.'/portal.key','allow_self_signed'=>false,
 ],
 'http'=>[
  'method'=>'POST',
  'header'=>"Content-Type: application/json\r\nConnection: close\r\n",
  'content'=>json_encode($input,JSON_THROW_ON_ERROR),
  'timeout'=>5,'ignore_errors'=>true,
 ]
]);
$raw=@file_get_contents('https://localhost:18787/private/automation-state',false,$context);
if ($raw===false) throw new RuntimeException('MTLS_FAILED');
$status=0;
foreach(($http_response_header??[]) as $line) {
 if (preg_match('/^HTTP\/\S+\s+([0-9]{3})\b/',$line,$m)) {
   $status=(int)$m[1];break;
 }
}
if ($status!==(int)$expect) {
 throw new RuntimeException('STORAGE_GATE_STATUS_EXPECTED_'.$expect.'_GOT_'.$status);
}
$data=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
if(!is_array($data))throw new RuntimeException('INVALID_RESPONSE');
if ($status===503 && ($data['error']??null)!=='LAB_STORAGE_DENIED') {
 throw new RuntimeException('STORAGE_DENY_MUST_HIDE_BUSINESS_DATA');
}
if ($status===409 && ($data['error']??null)!=='LAB_REPLAY') {
 throw new RuntimeException('STORAGE_REPLAY_MUST_FAIL');
}
if ($status===200) {
 if (($data['schema']??null)!=='CENTRAL_AUTO_MTLS_RESPONSE_V1'
     || ($data['company_id']??null)!==$input['company_id']
     || !isset($data['mac']) || !isset($data['body_b64'])
 ) throw new RuntimeException('STORAGE_RECOVERY_RESPONSE_INVALID');
}
echo "CENTRAL_AUTO_P8_HTTP_".$status."_PASS signed_company=1 no_ai=1 no_spend=1\n";
