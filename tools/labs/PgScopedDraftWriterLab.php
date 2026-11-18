<?php
declare(strict_types=1);

namespace ImagineAI\Convergence;

use PDO;
use RuntimeException;

/** A closed disposable writer identity, never a real authenticated author. */
final class PgScopedDraftWriterLab
{
    public const TABLES=[
        'aia_p51_lab.preview_batches',
        'aia_p51_lab.review_drafts',
        'aia_p59_lab.draft_authors',
    ];
    private const SCOPES=[
        'aia_p64_ci_write_a'=>['SYNTHETIC_TENANT_A','SYNTHETIC_P64_AUTHOR_A','a'],
        'aia_p64_ci_write_b'=>['SYNTHETIC_TENANT_B','SYNTHETIC_P64_AUTHOR_B','b'],
    ];

    private function __construct(private PDO $db,private string $role){}

    public static function fromDatabase(PDO $db):self
    {
        self::environment();
        if($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='pgsql')self::deny();
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $role=$db->query('SELECT current_user')->fetchColumn();
        if(!is_string($role)||!isset(self::SCOPES[$role]))self::deny();
        $scope=new self($db,$role);
        $scope->assertConnection($db);
        return $scope;
    }

    public function tenant():string{return self::SCOPES[$this->role][0];}
    public function author():string{return self::SCOPES[$this->role][1];}

    /** The context cannot authorize another PDO, SET ROLE, or another tenant. */
    public function assertConnection(PDO $db):void
    {
        self::environment();
        if($db!==$this->db||$db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='pgsql')self::deny();
        $r=$db->query(<<<'SQL'
SELECT current_database(),current_user,session_user,r.rolsuper,r.rolbypassrls,
 r.rolcreaterole,r.rolcreatedb,r.rolinherit,r.rolreplication,
 EXISTS(SELECT 1 FROM pg_auth_members m WHERE m.member=r.oid)
FROM pg_roles r WHERE r.rolname=current_user
SQL)->fetch(PDO::FETCH_NUM);
        if(!is_array($r)||array_slice($r,0,3)!==[
            'imagine_agents_ci',$this->role,$this->role
        ]||array_slice($r,3)!==[false,false,false,false,false,false,false])self::deny();
        $this->accessProfile();
    }

    /** Caller owns the transaction; the locks outlive profile verification. */
    public function assertWriteTransaction(PDO $db):void
    {
        if($db!==$this->db||!$db->inTransaction())self::deny();
        if($db->query('SHOW transaction_isolation')->fetchColumn()!=='read committed'
            ||$db->query('SHOW transaction_read_only')->fetchColumn()!=='off')self::deny();
        // DDL cannot change a validated policy before the writes consume it.
        $db->exec('LOCK TABLE '.implode(',',self::TABLES).' IN ACCESS SHARE MODE');
        $this->assertConnection($db);
    }

    /** Serialize new P64 batches/capacity for one fixed synthetic tenant. */
    public function serializeTenant(PDO $db):void
    {
        if($db!==$this->db||!$db->inTransaction())self::deny();
        $key=$this->role==='aia_p64_ci_write_a'?1:2;
        $db->query('SELECT pg_advisory_xact_lock(640051,'.$key.')')->fetchColumn();
    }

    private function accessProfile():void
    {
        foreach(['aia_p51_lab','aia_p59_lab'] as $schema){
            $q=$this->db->prepare(<<<'SQL'
SELECT has_schema_privilege(current_user,?,'USAGE'),
 has_schema_privilege(current_user,?,'CREATE')
SQL);
            $q->execute([$schema,$schema]);
            if($q->fetch(PDO::FETCH_NUM)!==[true,false])self::deny();
        }
        $tenant=$this->tenant();
        $suffix=self::SCOPES[$this->role][2];
        $tenantExpr="((tenant_ref)::text = '{$tenant}'::text)";
        foreach(self::TABLES as $table){
            $q=$this->db->prepare(<<<'SQL'
SELECT c.relrowsecurity,c.relforcerowsecurity,
 c.relowner<>(SELECT oid FROM pg_roles WHERE rolname=current_user),
 row_security_active(c.oid),
 has_table_privilege(current_user,c.oid,'SELECT'),
 has_table_privilege(current_user,c.oid,'INSERT'),
 has_table_privilege(current_user,c.oid,'UPDATE,DELETE,TRUNCATE,REFERENCES,TRIGGER'),
 has_any_column_privilege(current_user,c.oid,'REFERENCES'),
 has_table_privilege(current_user,c.oid,'SELECT WITH GRANT OPTION,INSERT WITH GRANT OPTION'),
 has_any_column_privilege(current_user,c.oid,
   'SELECT WITH GRANT OPTION,INSERT WITH GRANT OPTION,UPDATE WITH GRANT OPTION,REFERENCES WITH GRANT OPTION')
FROM pg_class c WHERE c.oid=CAST(? AS regclass)
SQL);
            $q->execute([$table]);
            if($q->fetch(PDO::FETCH_NUM)!==[
                true,true,true,true,true,true,false,false,false,false
            ])self::deny();
            $q=$this->db->prepare(<<<'SQL'
SELECT a.attname,has_column_privilege(current_user,a.attrelid,a.attnum,'UPDATE WITH GRANT OPTION')
FROM pg_attribute a WHERE a.attrelid=CAST(? AS regclass)
 AND a.attnum>0 AND NOT a.attisdropped
 AND has_column_privilege(current_user,a.attrelid,a.attnum,'UPDATE')
ORDER BY a.attname
SQL);
            $q->execute([$table]);
            if($q->fetchAll(PDO::FETCH_NUM)!==[['tenant_ref',false]])self::deny();
            $q=$this->db->prepare(<<<'SQL'
SELECT p.polname,p.polcmd,p.polpermissive,
 cardinality(p.polroles)=1 AND p.polroles[1]=r.oid,
 pg_get_expr(p.polqual,p.polrelid),pg_get_expr(p.polwithcheck,p.polrelid)
FROM pg_policy p JOIN pg_roles r ON r.rolname=current_user
WHERE p.polrelid=CAST(? AS regclass)
 AND (0=ANY(p.polroles) OR r.oid=ANY(p.polroles))
ORDER BY p.polname
SQL);
            $q->execute([$table]);
            $insertExpr=$tenantExpr;
            if($table==='aia_p59_lab.draft_authors'){
                $insertExpr="({$tenantExpr} AND ((author_ref)::text = '".
                    $this->author()."'::text))";
            }
            $expected=[
                ['p64_write_'.$suffix.'_insert','a',true,true,null,$insertExpr],
                ['p64_write_'.$suffix.'_lock','w',true,true,$tenantExpr,'false'],
                ['p64_write_'.$suffix.'_select','r',true,true,$tenantExpr,null],
            ];
            // Exact PG16 deparser values; literals are never normalized.
            if($q->fetchAll(PDO::FETCH_NUM)!==$expected)self::deny();
        }
        // A draft writer never receives reviewer keys, observations or their grants.
        foreach(['aia_p57_lab.reviewers','aia_p57_lab.signed_observations'] as $table){
            $q=$this->db->prepare(<<<'SQL'
SELECT has_table_privilege(current_user,c.oid,
 'SELECT,INSERT,UPDATE,DELETE,TRUNCATE,REFERENCES,TRIGGER'),
 has_any_column_privilege(current_user,c.oid,'SELECT,INSERT,UPDATE,REFERENCES')
FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace
WHERE n.nspname='aia_p57_lab' AND c.relname=?
SQL);
            $q->execute([substr($table,strlen('aia_p57_lab.'))]);
            if($q->fetch(PDO::FETCH_NUM)!==[false,false])self::deny();
        }
    }

    private static function environment():void
    {
        if(getenv('GITHUB_ACTIONS')!=='true'
            ||getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
            ||getenv('AIA_P64_CREATE_LAB')!=='true'
            ||getenv('DB_HOST')!=='127.0.0.1'
            ||getenv('DB_DATABASE')!=='imagine_agents_ci')self::deny();
    }
    private static function deny():never{throw new RuntimeException('P64_WRITER_PROFILE_DENIED');}
}
