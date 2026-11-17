<?php
declare(strict_types=1);
require_once __DIR__.'/p28-constraint-compare.php';

// Pure PHP mutation tests. No PostgreSQL, credentials, schema writes or network.
$tests=0;
function p28Test(bool $pass,string $label):void {
    global $tests;$tests++;
    if(!$pass)throw new RuntimeException('P28_COMPARE_TEST_'.$label);
}
function p28Fixture():array {
    $cases=[
        ['aia_p26_native.executions.executions_check',
         "CHECK ((((state)::text = ANY ((ARRAY['RESULT'::character varying, 'ACKED'::character varying])::text[])) = ((result_json IS NOT NULL) AND (result_sha256 IS NOT NULL) AND (canonical_receipt IS NOT NULL) AND (signature_b64 IS NOT NULL))))",
         "CHECK ((((state)::text = ANY (ARRAY[('RESULT'::character varying)::text, ('ACKED'::character varying)::text])) = ((result_json IS NOT NULL) AND (result_sha256 IS NOT NULL) AND (canonical_receipt IS NOT NULL) AND (signature_b64 IS NOT NULL))))"],
        ['aia_p26_native.executions.executions_state_check',
         "CHECK (((state)::text = ANY ((ARRAY['STARTED'::character varying, 'RESULT'::character varying, 'ACKED'::character varying, 'UNKNOWN'::character varying])::text[])))",
         "CHECK (((state)::text = ANY (ARRAY[('STARTED'::character varying)::text, ('RESULT'::character varying)::text, ('ACKED'::character varying)::text, ('UNKNOWN'::character varying)::text])))"],
        ['public.aia_lab_n8n_outbox_p6.aia_lab_n8n_outbox_p6_state_check',
         "CHECK (((state)::text = ANY ((ARRAY['PENDING'::character varying, 'LEASED'::character varying, 'ACKED'::character varying, 'DEAD'::character varying])::text[])))",
         "CHECK (((state)::text = ANY (ARRAY[('PENDING'::character varying)::text, ('LEASED'::character varying)::text, ('ACKED'::character varying)::text, ('DEAD'::character varying)::text])))"],
        ['auto_ia_lab.company_links.company_links_revision_check',
         'CHECK ((revision > 0))','CHECK ((revision > 0))'],
    ];
    $source=[];$target=[];$sourceCatalog=[];$targetCatalog=[];
    foreach($cases as [$id,$before,$after]){
        [$schema,$table,$name]=explode('.',$id);
        $base=['nspname'=>$schema,'relname'=>$table,'conname'=>$name];
        $source[]=[...$base,'definition'=>$before];$target[]=[...$base,'definition'=>$after];
        $column=$schema==='auto_ia_lab'?'revision':'state';
        $structure=['kind'=>'c','validated'=>true,'deferrable'=>false,'initially_deferred'=>false,
            'local'=>true,'no_inherit'=>false,'inherited_count'=>0,'columns'=>[$column],
            'column_types'=>[['name'=>$column,'type'=>$column==='revision'?'integer':
                ($schema==='public'?'character varying(12)':'character varying(16)'),
                'collation'=>$column==='state'?'pg_catalog.default':null]],
            'referenced_table'=>null,'referenced_columns'=>[],'referenced_column_types'=>[],
            'match_type'=>null,'on_update'=>null,'on_delete'=>null,'index_name'=>null,'extension'=>null];
        if($name==='executions_check'){
            foreach(['result_json'=>'text','result_sha256'=>'character(64)',
                'canonical_receipt'=>'character varying(1024)','signature_b64'=>'character varying(88)'] as $field=>$type){
                $structure['columns'][]=$field;
                $structure['column_types'][]=['name'=>$field,'type'=>$type,'collation'=>'pg_catalog.default'];
            }
        }
        $sourceCatalog[$id]=['expression'=>substr($before,7,-1),'structure'=>$structure];
        $targetCatalog[$id]=['expression'=>substr($after,7,-1),'structure'=>$structure];
    }
    return [$source,$sourceCatalog,$target,$targetCatalog];
}
function p28SnapshotFixture(array $rows,array $catalog):array {
    return ['schema'=>'P28_DATABASE_SNAPSHOT_V1','tables'=>['fixture'=>['rows'=>1,'sha256'=>str_repeat('a',64)]],
        'objects_sha256'=>str_repeat('b',64),'functions_sha256'=>str_repeat('c',64),
        ...P28ConstraintCompare::capture($rows,$catalog)];
}
function p28MutateSql(array &$rows,array &$catalog,int $index,callable $mutate):void {
    $row=&$rows[$index];$id=$row['nspname'].'.'.$row['relname'].'.'.$row['conname'];
    $row['definition']=$mutate($row['definition']);
    $catalog[$id]['expression']=substr($row['definition'],7,-1);
}

try{
    [$rows,$catalog,$restored,$restoredCatalog]=p28Fixture();
    $before=p28SnapshotFixture($rows,$catalog);$after=p28SnapshotFixture($restored,$restoredCatalog);
    p28Test(P28ConstraintCompare::compare($before,$before)===['equivalences'=>0,'rule'=>'EXACT'],'IDENTICAL');
    p28Test(P28ConstraintCompare::compare($before,$after)===['equivalences'=>3,'rule'=>P28ConstraintCompare::RULE],
        'THREE_OBSERVED_CASTS');
    p28Test($before['constraints_sha256']!==$after['constraints_sha256']
        &&$before['constraint_roundtrip_projection']['projected_constraints_sha256']===$after['constraints_sha256'],
        'RAW_AGGREGATE_PROJECTION');
    foreach([0,1,2] as $index){
        $id=$rows[$index]['nspname'].'.'.$rows[$index]['relname'].'.'.$rows[$index]['conname'];
        p28Test(P28ConstraintCompare::rewrite($id,$rows[$index]['definition'])===$restored[$index]['definition'],
            'OBSERVED_TEMPLATE_'.$index);
    }
    $mutations=[
        'LITERAL'=>static fn(string $s):string=>str_replace("'RESULT'","'REVOKED'",$s),
        'ORDER'=>static fn(string $s):string=>strtr($s,["'RESULT'"=>"'ACKED'","'ACKED'"=>"'RESULT'"]),
        'DUPLICATE'=>static fn(string $s):string=>str_replace("'ACKED'","'RESULT'",$s),
        'OPERATOR'=>static fn(string $s):string=>str_replace('= ANY','<> ANY',$s),
        'VARCHAR_TYPMOD'=>static fn(string $s):string=>str_replace('::character varying','::character varying(16)',$s),
        'CHAR'=>static fn(string $s):string=>str_replace('::character varying','::character',$s),
        'BPCHAR'=>static fn(string $s):string=>str_replace('::character varying','::bpchar',$s),
        'DESTINATION_TYPE'=>static fn(string $s):string=>str_replace(')::text',')::varchar',$s),
        'COLLATION'=>static fn(string $s):string=>str_replace('::text,','::text COLLATE "C",',$s),
        'FUNCTION'=>static fn(string $s):string=>str_replace("('RESULT'::character varying)::text","lower('RESULT')",$s),
        'NULL'=>static fn(string $s):string=>str_replace("('RESULT'::character varying)::text",'NULL',$s),
        'TAIL'=>static fn(string $s):string=>str_replace('signature_b64 IS NOT NULL','signature_b64 IS NULL',$s),
        'WHITESPACE_OUTSIDE_ARRAY'=>static fn(string $s):string=>str_replace(' = ANY','  = ANY',$s),
    ];
    foreach($mutations as $label=>$mutate){
        $changed=$restored;$changedCatalog=$restoredCatalog;
        p28MutateSql($changed,$changedCatalog,0,$mutate);
        p28Test(P28ConstraintCompare::compare($before,p28SnapshotFixture($changed,$changedCatalog))===null,$label);
    }
    $id='aia_p26_native.executions.executions_check';
    foreach(['validated','deferrable','initially_deferred','local','no_inherit'] as $flag){
        $changed=$restoredCatalog;$changed[$id]['structure'][$flag]=!$changed[$id]['structure'][$flag];
        p28Test(P28ConstraintCompare::compare($before,p28SnapshotFixture($restored,$changed))===null,'FLAG_'.$flag);
    }
    foreach(['type'=>'character(16)','collation'=>'pg_catalog.C'] as $field=>$value){
        $changed=$restoredCatalog;$changed[$id]['structure']['column_types'][0][$field]=$value;
        p28Test(P28ConstraintCompare::compare($before,p28SnapshotFixture($restored,$changed))===null,'COLUMN_'.$field);
    }
    $changed=$restoredCatalog;$changed[$id]['expression']=$catalog[$id]['expression'];
    p28Test(P28ConstraintCompare::compare($before,p28SnapshotFixture($restored,$changed))===null,'EXPRESSION_ONLY');
    $changed=$restored;$changed[0]['definition']=$rows[0]['definition'];
    p28Test(P28ConstraintCompare::compare($before,p28SnapshotFixture($changed,$restoredCatalog))===null,'DEFINITION_ONLY');
    $newId='aia_p26_native.executions.unreviewed_check';
    $unknownRows=$rows;$unknownTarget=$restored;$unknownCatalog=$catalog;$unknownTargetCatalog=$restoredCatalog;
    $unknownRows[0]['conname']='unreviewed_check';$unknownTarget[0]['conname']='unreviewed_check';
    $unknownCatalog[$newId]=$unknownCatalog[$id];unset($unknownCatalog[$id]);
    $unknownTargetCatalog[$newId]=$unknownTargetCatalog[$id];unset($unknownTargetCatalog[$id]);
    p28Test(P28ConstraintCompare::compare(p28SnapshotFixture($unknownRows,$unknownCatalog),
        p28SnapshotFixture($unknownTarget,$unknownTargetCatalog))===null,'UNKNOWN_CONSTRAINT');
    foreach(['constraints_sha256','objects_sha256','functions_sha256'] as $field){
        $changed=$after;$changed[$field]=str_repeat('d',64);
        p28Test(P28ConstraintCompare::compare($before,$changed)===null,'HASH_'.$field);
    }
    $changed=$after;$changed['tables']['fixture']['rows']=2;
    p28Test(P28ConstraintCompare::compare($before,$changed)===null,'DATA_CHANGED');
    $changed=$after;$changed['constraint_details'][$id]['literal_tokens_sha256']=str_repeat('e',64);
    p28Test(P28ConstraintCompare::compare($before,$changed)===null,'LITERAL_HASH_CHANGED');
    $changed=$before;$changed['constraint_roundtrip_projection']['source_constraints_sha256']=str_repeat('f',64);
    p28Test(P28ConstraintCompare::compare($changed,$after)===null,'SOURCE_AGGREGATE_BINDING');
    $changed=$before;$changed['constraint_roundtrip_projection']['changes']['unreviewed']=$after['constraint_details'][$id];
    p28Test(P28ConstraintCompare::compare($changed,$after)===null,'UNKNOWN_PROJECTION_ENTRY');
    echo json_encode(['schema'=>'P28_CONSTRAINT_COMPARE_TESTS_V1','tests'=>$tests,'passed'=>true,
        'observed_equivalences'=>3,'network_calls'=>0,'database_writes'=>0],JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $error){
    echo json_encode(['schema'=>'P28_CONSTRAINT_COMPARE_TESTS_DENIED_V1',
        'reason'=>$error instanceof RuntimeException?$error->getMessage():'P28_COMPARE_TEST_INTERNAL'],JSON_THROW_ON_ERROR)."\n";
    exit(1);
}
