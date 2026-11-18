<?php
declare(strict_types=1);

/** Bounded, credential-free HTTPS client for the disposable feedback proof. */
function feedbackHttpNeed(bool $condition):void
{if(!$condition)throw new RuntimeException('FEEDBACK_HTTP_PROBE_DENIED');}

try {
    feedbackHttpNeed(PHP_SAPI==='cli' && getenv('GITHUB_ACTIONS')==='true'
        && getenv('GITHUB_REPOSITORY')==='rafhael100/imagine-ai-agents'
        && getenv('CRM_FEEDBACK_CI')==='synthetic-private-v1');
    foreach(['/run/secrets/p25-password','/certs/sender.password','/certs/execution-reader.password',
        '/run/secrets/transport-password','/run/secrets/execution-password'] as $forbidden)
        feedbackHttpNeed(!file_exists($forbidden));
    $raw=file_get_contents('php://stdin',false,null,0,20001);
    feedbackHttpNeed(is_string($raw)&&strlen($raw)<=20000);
    $input=json_decode($raw,true,8,JSON_THROW_ON_ERROR);
    feedbackHttpNeed(is_array($input)
        &&in_array($input['path']??null,['/private/events/execution','/private/events/execution?x=1'],true)
        &&in_array($input['method']??null,['GET','POST'],true)
        &&in_array($input['content_type']??null,['application/json','text/plain'],true));
    $body=base64_decode($input['body_b64']??'',true);
    feedbackHttpNeed(is_string($body)&&strlen($body)<=9000&&is_array($input['headers']??null));
    $headers=['Host: agents-ci.internal','Connection: close','Content-Type: '.$input['content_type']];
    foreach($input['headers'] as $header){
        feedbackHttpNeed(is_string($header)&&strlen($header)<=4096
            &&!str_contains($header,"\r")&&!str_contains($header,"\n"));
        $headers[]=$header;
    }
    $ssl=['cafile'=>'/certs/ca.crt','verify_peer'=>true,'verify_peer_name'=>true,
        'peer_name'=>'agents-ci.internal','allow_self_signed'=>false,'SNI_enabled'=>true];
    if(($input['client_certificate']??null)===true){
        $ssl['local_cert']='/certs/client.crt';$ssl['local_pk']='/certs/client.key';
    }
    $context=stream_context_create(['ssl'=>$ssl,'http'=>[
        'method'=>$input['method'],'header'=>$headers,'content'=>$body,'timeout'=>4.0,
        'ignore_errors'=>true,'follow_location'=>0,'max_redirects'=>0,'protocol_version'=>1.1]]);
    $http_response_header=[];
    $response=@file_get_contents('https://127.0.0.1:8443'.$input['path'],false,$context,0,4097);
    $status=0;$cache='';$type='';
    foreach($http_response_header as $header){
        if(preg_match('/\AHTTP\/1\.[01] ([0-9]{3})\b/',$header,$match))$status=(int)$match[1];
        if(str_starts_with(strtolower($header),'cache-control:'))$cache=trim(substr($header,14));
        if(str_starts_with(strtolower($header),'content-type:'))$type=trim(substr($header,13));
    }
    feedbackHttpNeed($response===false||strlen($response)<=4096);
    echo json_encode(['transport_ok'=>is_string($response),'status'=>$status,
        'cache_control'=>$cache,'content_type'=>$type,
        'body_b64'=>base64_encode(is_string($response)?$response:'')],JSON_THROW_ON_ERROR)."\n";
}catch(Throwable){fwrite(STDERR,"FEEDBACK_HTTP_PROBE_DENIED\n");exit(1);}
