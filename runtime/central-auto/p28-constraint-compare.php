<?php
declare(strict_types=1);

/** Pure P28 comparison of the three observed PostgreSQL 16 array-cast forms. */
final class P28ConstraintCompare
{
    public const RULE='P28_VARCHAR_LITERAL_ARRAY_CAST_V1';
    private const JSON=JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE;
    private const ALLOWED=[
        'aia_p26_native.executions.executions_check'=>2,
        'aia_p26_native.executions.executions_state_check'=>4,
        'public.aia_lab_n8n_outbox_p6.aia_lab_n8n_outbox_p6_state_check'=>4,
    ];

    public static function hash(mixed $value):string {
        return hash('sha256',json_encode($value,self::JSON));
    }

    public static function shape(string $definition):array {
        $literals=[];
        $pattern=<<<'REGEX'
~(?:\bE|\bU&)?'(?:''|\\.|[^'\\])*'~is
REGEX;
        $shape=preg_replace_callback($pattern,static function(array $match)use(&$literals):string{
            $literals[]=$match[0];return "'LITERAL'";
        },$definition);
        if(is_string($shape))$shape=preg_replace('/\s+/',' ',trim($shape));
        $withoutLiterals=is_string($shape)?str_replace("'LITERAL'",'', $shape):'';
        $withoutIdentifiers=preg_replace('/"[A-Za-z_][A-Za-z0-9_]*"/','IDENTIFIER',$withoutLiterals);
        if(!is_string($shape)||strlen($shape)>4096
            ||preg_match('/[\x00-\x1f\x7f\x80-\xff\'\$]/',$withoutLiterals)===1
            ||!is_string($withoutIdentifiers)||str_contains($withoutIdentifiers,'"'))$shape=null;
        return ['sql_shape'=>$shape,'literal_tokens_sha256'=>self::hash($literals)];
    }

    private static function form(string $identity,bool $restored,bool $expression):?string {
        $count=self::ALLOWED[$identity]??null;if($count===null)return null;
        $item=$restored?"('LITERAL'::character varying)::text":"'LITERAL'::character varying";
        $array='ARRAY['.implode(', ',array_fill(0,$count,$item)).']';
        if(!$restored)$array='('.$array.')::text[]';
        $definition=$identity==='aia_p26_native.executions.executions_check'
            ?'CHECK ((((state)::text = ANY ('.$array.')) = ((result_json IS NOT NULL) AND '
                .'(result_sha256 IS NOT NULL) AND (canonical_receipt IS NOT NULL) AND '
                .'(signature_b64 IS NOT NULL))))'
            :'CHECK (((state)::text = ANY ('.$array.')))';
        return $expression?substr($definition,7,-1):$definition;
    }

    /** No SQL cleanup: only replace this one exact, unidimensional array cast. */
    public static function rewrite(string $identity,string $sql,bool $expression=false):?string {
        if(!isset(self::ALLOWED[$identity])
            ||self::shape($sql)['sql_shape']!==self::form($identity,false,$expression))return null;
        $pattern=<<<'REGEX'
~\(ARRAY\[(('[A-Z_][A-Z0-9_]{0,31}'::character varying)(?:, '[A-Z_][A-Z0-9_]{0,31}'::character varying)*)\]\)::text\[\]~
REGEX;
        $matches=[];
        if(preg_match_all($pattern,$sql,$matches,PREG_OFFSET_CAPTURE)!==1)return null;
        $items=explode(', ',$matches[1][0][0]);
        if(count($items)!==self::ALLOWED[$identity]||count(array_unique($items))!==count($items))return null;
        $replacement='ARRAY['.implode(', ',array_map(static fn(string $item):string=>'('.$item.')::text',$items)).']';
        [$original,$offset]=$matches[0][0];
        $rewritten=substr($sql,0,$offset).$replacement.substr($sql,$offset+strlen($original));
        if(self::shape($rewritten)['sql_shape']!==self::form($identity,true,$expression)
            ||self::shape($sql)['literal_tokens_sha256']!==self::shape($rewritten)['literal_tokens_sha256'])return null;
        return $rewritten;
    }

    public static function describe(string $definition,?string $expression,array $structure):array {
        return [
            'definition_sha256'=>hash('sha256',$definition),'definition_bytes'=>strlen($definition),
            'expression_sha256'=>$expression===null?null:hash('sha256',$expression),
            'structure'=>$structure,...self::shape($definition),
        ];
    }

    private static function eligible(string $identity,array $structure):bool {
        if(!isset(self::ALLOWED[$identity])||($structure['kind']??null)!=='c')return false;
        $type=str_starts_with($identity,'aia_p26_native.')?'character varying(16)':'character varying(12)';
        $state=array_values(array_filter($structure['column_types']??[],
            static fn(array $column):bool=>($column['name']??null)==='state'));
        return count($state)===1&&$state[0]['type']===$type
            &&($state[0]['collation']??null)==='pg_catalog.default';
    }

    /** Rows are the SAME ordered projection used by the original aggregate. */
    public static function capture(array $rows,array $catalog):array {
        $details=[];$projected=$rows;$changes=[];
        foreach($rows as $i=>$row){
            $id=$row['nspname'].'.'.$row['relname'].'.'.$row['conname'];
            $context=$catalog[$id];$expression=$context['expression'];$structure=$context['structure'];
            $details[$id]=self::describe($row['definition'],$expression,$structure);
            if(!self::eligible($id,$structure)||!is_string($expression))continue;
            $definitionAfter=self::rewrite($id,$row['definition']);
            $expressionAfter=self::rewrite($id,$expression,true);
            if($definitionAfter===null||$expressionAfter===null)continue;
            $projected[$i]['definition']=$definitionAfter;
            $changes[$id]=self::describe($definitionAfter,$expressionAfter,$structure);
        }
        $rawHash=self::hash($rows);
        return ['constraints_sha256'=>$rawHash,'constraint_details'=>$details,
            'constraint_roundtrip_projection'=>[
                'schema'=>self::RULE,'source_constraints_sha256'=>$rawHash,
                'projected_constraints_sha256'=>self::hash($projected),'changes'=>$changes,
            ]];
    }

    /** null means DENY. Every field outside the observed three deltas is exact. */
    public static function compare(array $expected,array $actual):?array {
        if($expected===$actual)return ['equivalences'=>0,'rule'=>'EXACT'];
        $left=$expected;$right=$actual;
        foreach(['constraints_sha256','constraint_details','constraint_roundtrip_projection'] as $name){
            if(!array_key_exists($name,$left)||!array_key_exists($name,$right))return null;
            unset($left[$name],$right[$name]);
        }
        if($left!==$right||!is_array($expected['constraint_details'])||!is_array($actual['constraint_details'])
            ||array_keys($expected['constraint_details'])!==array_keys($actual['constraint_details']))return null;
        $projection=$expected['constraint_roundtrip_projection'];
        if(!is_array($projection)||array_keys($projection)!==[
            'schema','source_constraints_sha256','projected_constraints_sha256','changes']
            ||$projection['schema']!==self::RULE
            ||$projection['source_constraints_sha256']!==$expected['constraints_sha256']
            ||$projection['projected_constraints_sha256']!==$actual['constraints_sha256']
            ||!is_array($projection['changes'])||$projection['changes']===[])return null;
        // Target is already in the canonical observed form; another rewrite is not accepted.
        if($actual['constraint_roundtrip_projection']!==[
            'schema'=>self::RULE,'source_constraints_sha256'=>$actual['constraints_sha256'],
            'projected_constraints_sha256'=>$actual['constraints_sha256'],'changes'=>[]])return null;
        $changed=[];
        foreach($expected['constraint_details'] as $id=>$before){
            $after=$actual['constraint_details'][$id];
            if($before===$after)continue;
            if(!isset(self::ALLOWED[$id])||!is_array($before)||!is_array($after)
                ||!self::eligible($id,$before['structure']??[])
                ||($before['structure']??null)!==($after['structure']??null)
                ||($before['literal_tokens_sha256']??null)!==($after['literal_tokens_sha256']??null)
                ||($projection['changes'][$id]??null)!==$after
                ||($before['sql_shape']??null)!==self::form($id,false,false)
                ||($after['sql_shape']??null)!==self::form($id,true,false))return null;
            $changed[]=$id;
        }
        if($changed===[]||array_keys($projection['changes'])!==$changed)return null;
        return ['equivalences'=>count($changed),'rule'=>self::RULE];
    }
}
