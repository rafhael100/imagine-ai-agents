<?php
declare(strict_types=1);
require_once __DIR__.'/strict-json.php';
function p29_check(bool $condition,string $code):void {
    if(!$condition)throw new RuntimeException('P29_'.$code);
}
try{
    p29_check(PHP_SAPI==='cli','CLI_ONLY');
    $valid=[
        '{"company_id": "aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa", "schema":"CRM_LEAD_EVENT_V1"}',
        " \n { \"b\" : [true, false, null, 1e3, -0, -12.5e-2], \"a\": {\"x\":\"y\"} } \t",
        '{"\\u0073chema":"CRM_LEAD_EVENT_V1","event_id":"11111111-1111-4111-8111-111111111111"}',
        json_encode(['quote"name'=>'quote"value','slash'=>'a\\b','unicode'=>'ação'],JSON_THROW_ON_ERROR),
        '[{"a":1},{"a":2},1,1]',
        '{"large":999999999999999999999999999999}',
    ];
    foreach($valid as $raw)
        p29_check(agents_json_decode($raw,8192,12)===json_decode($raw,true,12,JSON_THROW_ON_ERROR|JSON_BIGINT_AS_STRING),'VALID_JSON_CHANGED');
    $invalid=[
        '{"a":1,"a":1}', '{"a":1,"\\u0061":1}',
        '{"envelope":{"schema":"a","schema":"a"}}',
        '[{"x":{"a":1,"\\u0061":2}}]',
        '{'.json_encode('a"b',JSON_THROW_ON_ERROR).':1,"a\\u0022b":2}',
        '{"a":1,}', '[1,]', '{"a":01}', '{"a":1..2}', '{"a":NaN}',
        '{"a":"\\uDEAD"}', '{"a":"\\x41"}', '{"a":"unterminated}',
        '{"a":1} trailing', "{\"a\":\"\x01\"}", "{\"a\":\"\xff\"}",
    ];
    foreach($invalid as $raw){
        $denied=false;
        try{agents_json_decode($raw,8192,12);}catch(Throwable){$denied=true;}
        p29_check($denied,'INVALID_JSON_ACCEPTED');
    }
    foreach([[str_repeat('[',20).'0'.str_repeat(']',20),8192,12],['{"a":1}',6,12]] as [$raw,$limit,$depth]){
        $denied=false;
        try{agents_json_decode($raw,$limit,$depth);}catch(Throwable){$denied=true;}
        p29_check($denied,'LIMIT_NOT_ENFORCED');
    }
    echo json_encode(['schema'=>'P29_JSON_PARSER_VERIFIED_V1','valid_cases'=>count($valid),
        'negative_cases'=>count($invalid)+2,'canonical_serialization_required'=>false],JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $error){
    $code=$error instanceof RuntimeException&&preg_match('/\AP29_[A-Z0-9_]+\z/D',$error->getMessage())
        ?$error->getMessage():strtoupper(str_replace('\\','_',get_class($error)));
    fwrite(STDERR,'P29_JSON_DENIED_'.$code."\n");exit(1);
}
