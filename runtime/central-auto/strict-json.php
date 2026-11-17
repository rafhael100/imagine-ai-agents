<?php
declare(strict_types=1);
/** Bounded JSON decoding without silently discarding duplicate object names. */
function agents_json_space(string $raw,int &$at):void {
    while(isset($raw[$at])&&str_contains(" \t\r\n",$raw[$at]))$at++;
}
function agents_json_string(string $raw,int &$at):string {
    $start=$at;
    if(($raw[$at++]??'')!=='"')throw new UnexpectedValueException('JSON_STRING');
    while(isset($raw[$at])){
        $char=$raw[$at++];
        if($char==='"'){
            $value=json_decode(substr($raw,$start,$at-$start),true,2,JSON_THROW_ON_ERROR);
            if(!is_string($value))throw new UnexpectedValueException('JSON_STRING');
            return $value;
        }
        if(ord($char)<32)throw new UnexpectedValueException('JSON_CONTROL');
        if($char==='\\'){
            $escaped=$raw[$at++]??'';
            if($escaped==='u'){
                if(!preg_match('/\A[0-9a-fA-F]{4}\z/D',substr($raw,$at,4)))
                    throw new UnexpectedValueException('JSON_ESCAPE');
                $at+=4;
            }elseif($escaped===''||!str_contains('"\\/bfnrt',$escaped)){
                throw new UnexpectedValueException('JSON_ESCAPE');
            }
        }
    }
    throw new UnexpectedValueException('JSON_UNTERMINATED');
}
function agents_json_value(string $raw,int &$at,int $depth,int $maximum):void {
    if($depth>$maximum)throw new UnexpectedValueException('JSON_DEPTH');
    agents_json_space($raw,$at);$kind=$raw[$at]??'';
    if($kind==='"'){agents_json_string($raw,$at);return;}
    if($kind==='{'||$kind==='['){
        $object=$kind==='{';$close=$object?'}':']';$at++;$seen=[];
        agents_json_space($raw,$at);
        if(($raw[$at]??'')===$close){$at++;return;}
        do{
            agents_json_space($raw,$at);
            if($object){
                $name=agents_json_string($raw,$at);
                if(array_key_exists($name,$seen))throw new UnexpectedValueException('JSON_DUPLICATE_NAME');
                $seen[$name]=true;agents_json_space($raw,$at);
                if(($raw[$at++]??'')!==':')throw new UnexpectedValueException('JSON_COLON');
            }
            agents_json_value($raw,$at,$depth+1,$maximum);agents_json_space($raw,$at);
            $separator=$raw[$at++]??'';
            if($separator===$close)return;
            if($separator!==',')throw new UnexpectedValueException('JSON_DELIMITER');
        }while(true);
    }
    $start=$at;
    while(isset($raw[$at])&&!str_contains(" \t\r\n,]}",$raw[$at]))$at++;
    if($at===$start)throw new UnexpectedValueException('JSON_VALUE');
    // PHP validates the standard JSON number/literal grammar. The scanner
    // does not normalize or constrain valid whitespace, key order or escapes.
    json_decode(substr($raw,$start,$at-$start),true,2,JSON_THROW_ON_ERROR|JSON_BIGINT_AS_STRING);
}
function agents_json_decode(string $raw,int $maximumBytes,int $maximumDepth):mixed {
    if(strlen($raw)>$maximumBytes||$maximumDepth<1||$maximumDepth>32)
        throw new UnexpectedValueException('JSON_LIMIT');
    $at=0;agents_json_value($raw,$at,1,$maximumDepth);agents_json_space($raw,$at);
    if($at!==strlen($raw))throw new UnexpectedValueException('JSON_TRAILING');
    return json_decode($raw,true,$maximumDepth,JSON_THROW_ON_ERROR|JSON_BIGINT_AS_STRING);
}
