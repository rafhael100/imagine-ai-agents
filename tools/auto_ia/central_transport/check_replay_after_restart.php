<?php
declare(strict_types=1);
/** PostgreSQL replay proof AFTER killing/restarting separate mTLS server. */
if (getenv('GITHUB_ACTIONS')!=='true' ||
    getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents') {
    throw new RuntimeException('CI_ONLY');
}
$dir=getenv('CENTRAL_AUTO_P5_CERT_DIR');
$r=[
 'schema'=>'CENTRAL_AUTO_MTLS_REQUEST_V1',
 'issuer'=>'portal-automation-admin',
 'audience'=>'crm-automation-state',
 'company_id'=>'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
 'request_id'=>'SYNTHETIC_REQUEST_1',
 'nonce'=>'SYNTHETIC_NONCE_1',
 'issued_at'=>time(),
 'mac'=>'',
];
$fields=array_values(array_diff_key($r,['mac'=>true]));
$r['mac']=hash_hmac('sha256',implode('|',array_map('strval',$fields)),
    'SYNTHETIC_CENTRAL_AUTO_P5_ONLY_TEST_KEY_NOT_REAL');
$ctx=stream_context_create([
 'ssl'=>[
   'verify_peer'=>true,'verify_peer_name'=>true,
   'peer_name'=>'localhost','cafile'=>$dir.'/ca.crt',
   'local_cert'=>$dir.'/portal.crt','local_pk'=>$dir.'/portal.key',
   'allow_self_signed'=>false,
 ],
 'http'=>[
   'method'=>'POST',
   'header'=>"Content-Type: application/json\r\nConnection: close\r\n",
   'content'=>json_encode($r,JSON_THROW_ON_ERROR),
   'timeout'=>5,'ignore_errors'=>true,
 ],
]);
$raw=@file_get_contents('https://localhost:18787/private/automation-state',false,$ctx);
if ($raw===false) throw new RuntimeException('RESTART_TLS_UNAVAILABLE');
$line=$http_response_header[0]??'';
if (!preg_match('/\s409\s/',$line)) {
 throw new RuntimeException('NONCE_ACCEPTED_AFTER_RESTART: '.$line);
}
$response=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
if (($response['error']??null)!=='LAB_REPLAY')throw new RuntimeException('UNEXPECTED_REPLAY_RESPONSE');
echo "CENTRAL_AUTO_P6_RESTART_REPLAY_DENIED existing_nonce=1 provider_calls=0\n";
