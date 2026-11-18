<?php
declare(strict_types=1);

// Offline contract parity only: no PDO connection, ledger mutation or execution.
if(PHP_SAPI!=='cli')exit(2);
require_once __DIR__.'/PgNativeExecutionLedgerLab.php';

use ImagineAI\NativeLab\PgNativeExecutionLedgerLab;

try{
    $input=json_decode(stream_get_contents(STDIN,262145),true,64,JSON_THROW_ON_ERROR);
    if(!is_array($input)||array_keys($input)!==['context','observation']
        ||!is_array($input['context']))throw new DomainException('NATIVE_PARITY_INPUT');
    $context=(new ReflectionMethod(PgNativeExecutionLedgerLab::class,'context'))
        ->invoke(null,$input['context']);
    $valid=(new ReflectionMethod(PgNativeExecutionLedgerLab::class,'validResult'))
        ->invoke(null,$input['context'],$input['observation']);
    $reply=['context_valid'=>true,'context_json'=>$context,
        'inputs'=>PgNativeExecutionLedgerLab::inputs($input['context']),'result_valid'=>$valid];
}catch(Throwable){$reply=['context_valid'=>false,'result_valid'=>false];}
echo json_encode($reply,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),"\n";
