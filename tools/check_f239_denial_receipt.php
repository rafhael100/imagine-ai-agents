<?php
declare(strict_types=1);

/** F239: denial-only HTTP response contract; no transport. */
use ImagineAI\MockBridge\{MockCrmPublicResponseContract,MockSyntheticHttpDenialReceipt};

$root = dirname(__DIR__);
spl_autoload_register(static function(string $class) use ($root): void {
    $prefix = 'ImagineAI\\';
    if (str_starts_with($class,$prefix)) {
        require $root.'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    }
});
function f239Assert(bool $ok): void {
    if (!$ok) throw new RuntimeException('F239_DENIED');
}
try {
    $headers=[
        'content-type'=>'application/json',
        'cache-control'=>'no-store, private',
    ];
    $body=json_encode(MockCrmPublicResponseContract::denial(),
        JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    f239Assert(MockSyntheticHttpDenialReceipt::matches(404,$headers,$body));
    f239Assert(MockSyntheticHttpDenialReceipt::matches(
        404,array_replace($headers,['content-type'=>'application/json; charset=utf-8']),$body
    ));

    $f248Headers=[
        'content-type'=>'application/json',
        'cache-control'=>'private, no-store, max-age=0',
        'x-robots-tag'=>'noindex, nofollow, noarchive',
        'x-content-type-options'=>'nosniff',
    ];
    f239Assert(MockSyntheticHttpDenialReceipt::matches(404,$f248Headers,$body));

    f239Assert(MockSyntheticHttpDenialReceipt::matches(
        404,array_replace($f248Headers,['cache-control'=>'max-age=0, no-store, private']),$body
    ));
    $cases=[];
    foreach([200,201,202,204,301,302,307,308,400,401,403,405,409,422,429,500,503,0] as $status) {
        $cases[]=[$status,$headers,$body];
    }
    $cases[]=[404,['content-type'=>'application/json'],$body];
    $cases[]=[404,['cache-control'=>'no-store, private'],$body];
    $cases[]=[404,array_replace($headers,['content-type'=>'text/html']),$body];
    $cases[]=[404,array_replace($headers,['content-type'=>'application/json; charset=ISO-8859-1']),$body];
    $cases[]=[404,array_replace($headers,['cache-control'=>'private']),$body];
    $cases[]=[404,array_replace($headers,['cache-control'=>'no-store']),$body];
    $cases[]=[404,array_merge($headers,['set-cookie'=>'session=FAKE']),$body];
    $cases[]=[404,array_merge($headers,['location'=>'/login']),$body];
    $cases[]=[404,array_reverse($headers,true),$body];
    $cases[]=[404,array_replace($headers,['content-type'=>'Application/JSON']),$body];

    $cases[]=[404,$headers,''];
    $cases[]=[404,$headers,$body."\n"];
    $cases[]=[404,$headers,"\xEF\xBB\xBF".$body];
    $cases[]=[404,$headers,'{}'];
    $cases[]=[404,$headers,str_repeat('X',513)];
    $cases[]=[404,$headers,substr($body,0,-1).',"allow":true}'];
    $cases[]=[404,$headers,str_replace('"DENIED_OR_NOT_FOUND"','"READ_SYNTHETIC_ONLY"',$body)];
    $cases[]=[404,$headers,str_replace('"real_authorization":false','"real_authorization":true',$body)];
    $cases[]=[404,$headers,str_replace('"projection":null','"projection":{"email":"fake"}',$body)];
    $cases[]=[404,$headers,str_replace('"external_calls":0','"external_calls":1',$body)];
    $cases[]=[404,$headers,str_replace('"state":"DENIED_OR_NOT_FOUND"', '"state":"DENIED_OR_NOT_FOUND","state":"READ_SYNTHETIC_ONLY"',$body)];
    $cases[]=[404,$headers,'{"state":"DENIED_OR_NOT_FOUND","contract":"SYNTHETIC_CRM_AIA_PUBLIC_V1","projection":null,"real_authorization":false,"external_calls":0}'];
    $cases[]=[404,array_replace($f248Headers,['cache-control'=>'no-store, private']),$body];
    $cases[]=[404,array_replace($f248Headers,['x-content-type-options'=>'unsafe']),$body];
    $cases[]=[404,array_replace($f248Headers,['x-robots-tag'=>'index, follow']),$body];
    $cases[]=[404,array_merge($f248Headers,['set-cookie'=>'synthetic=1']),$body];
    $cases[]=[404,array_reverse($f248Headers,true),$body];
    $cases[]=[404,['content-type'=>'application/json','cache-control'=>'private, no-store, max-age=0'],$body];
    f239Assert(count($cases)===46);
    foreach ($cases as [$status,$meta,$response]) {
        f239Assert(!MockSyntheticHttpDenialReceipt::matches($status,$meta,$response));
    }
    echo "F239_DENIAL_RECEIPT_PASS positive=4 negatives=46 accepted_read=false operational_decision=NO_GO\n";
} catch (Throwable) {
    fwrite(STDERR,"F239_DENIAL_RECEIPT_DENIED operational_decision=NO_GO\n");
    exit(1);
}
