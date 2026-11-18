<?php
declare(strict_types=1);
namespace ImagineAI\Labs;
use PDO;
use Throwable;

/** Disposable CI-only PostgreSQL-backed commercial workbench. NO IAM, LLM or CRM. */
final readonly class PgSalesWorkbenchLab
{
    public const LIMIT = 8;
    private const USAGE='public.aia_lab_sales_usage_p4';
    private const EVENTS='public.aia_lab_sales_events_p4';
    private const REVIEWS='public.aia_lab_sales_reviews_p4';

    public function __construct(private PDO $db)
    {
        if (getenv('AIA_P4_PG_LAB')!=='true'
            || getenv('GITHUB_ACTIONS')!=='true'
            || getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
            || getenv('DB_HOST')!=='127.0.0.1'
            || getenv('DB_DATABASE')!=='imagine_agents_ci'
            || getenv('DB_USERNAME')!=='postgres'
            || $db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='pgsql') {
            throw new \RuntimeException('P4_NON_DISPOSABLE_ENVIRONMENT_DENIED');
        }
        $row=$db->query('SELECT current_database() AS dbname,current_user AS username')
            ->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || $row['dbname']!=='imagine_agents_ci'
            || $row['username']!=='postgres') {
            throw new \RuntimeException('P4_DB_IDENTITY_DENIED');
        }
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    }

    public static function isSynthetic(string $ref): bool
    {
        return strlen($ref)<=90
            && preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,80}\z/D',$ref)===1;
    }

    public function createTables(): void
    {
        $this->db->exec('CREATE TABLE '.self::USAGE.' (
            tenant_ref varchar(90) PRIMARY KEY,
            used smallint NOT NULL DEFAULT 0 CHECK (used BETWEEN 0 AND 8))');
        $this->db->exec('CREATE TABLE '.self::EVENTS.' (
            tenant_ref varchar(90) NOT NULL,
            request_ref varchar(90) NOT NULL,
            input_sha256 char(64) NOT NULL,
            run_number smallint NOT NULL,
            approval_ref varchar(90),
            PRIMARY KEY (tenant_ref,request_ref),
            UNIQUE (tenant_ref,run_number))');
        $this->db->exec('CREATE TABLE '.self::REVIEWS.' (
            tenant_ref varchar(90) NOT NULL,
            approval_ref varchar(90) NOT NULL,
            request_ref varchar(90) NOT NULL,
            state varchar(30) NOT NULL CHECK (
                state IN (\'PENDENTE\',\'APROVADO_EM_ENSAIO\',\'REJEITADO_EM_ENSAIO\')),
            PRIMARY KEY (tenant_ref,approval_ref),
            UNIQUE (tenant_ref,request_ref))');
    }

    public function dropTables(): void
    {
        $this->db->exec('DROP TABLE IF EXISTS '.self::REVIEWS);
        $this->db->exec('DROP TABLE IF EXISTS '.self::EVENTS);
        $this->db->exec('DROP TABLE IF EXISTS '.self::USAGE);
    }

    /** @return ?array{state:string,used:int,approval_ref:?string} */
    public function run(string $tenant,string $request,string $agent,string $lead,string $goal): ?array
    {
        if (!self::isSynthetic($tenant) || !self::isSynthetic($request)
            || !self::isSynthetic($lead)
            || !($agent==='PRESET_SALES' || self::isSynthetic($agent))
            || !in_array($goal,['next','summary'],true)) return null;

        $digest=hash('sha256',implode("\n",[
            'AIA_LAB_P4_V1',$tenant,$agent,$lead,$goal,
        ]));
        try {
            $this->db->beginTransaction();
            $stmt=$this->db->prepare('INSERT INTO '.self::USAGE.' (tenant_ref,used)
                VALUES (:tenant,0) ON CONFLICT (tenant_ref) DO NOTHING');
            $stmt->execute(['tenant'=>$tenant]);
            $stmt=$this->db->prepare('SELECT used FROM '.self::USAGE.'
                WHERE tenant_ref=:tenant FOR UPDATE');
            $stmt->execute(['tenant'=>$tenant]);
            $quota=$stmt->fetchColumn();
            if ($quota===false) throw new \RuntimeException('MISSING_QUOTA');
            $used=(int)$quota;
            $stmt=$this->db->prepare('SELECT input_sha256,approval_ref FROM '.self::EVENTS.'
                WHERE tenant_ref=:tenant AND request_ref=:request');
            $stmt->execute(['tenant'=>$tenant,'request'=>$request]);
            $existing=$stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($existing)) {
                if (!hash_equals($existing['input_sha256'],$digest)) {
                    $this->db->rollBack();
                    return null;
                }
                $this->db->commit();
                return ['state'=>'REPLAY','used'=>$used,
                    'approval_ref'=>$existing['approval_ref'] ?: null];
            }
            if ($used>=self::LIMIT) {
                $this->db->rollBack();
                return null;
            }
            $number=$used+1;
            $review=$goal==='next'
                ? 'SYNTHETIC_REVIEW_'.str_pad((string)$number,2,'0',STR_PAD_LEFT)
                : null;
            $stmt=$this->db->prepare('INSERT INTO '.self::EVENTS.'
                (tenant_ref,request_ref,input_sha256,run_number,approval_ref)
                VALUES (:tenant,:request,:digest,:number,:review)');
            $stmt->execute([
                'tenant'=>$tenant,'request'=>$request,'digest'=>$digest,
                'number'=>$number,'review'=>$review,
            ]);
            if ($review!==null) {
                $stmt=$this->db->prepare('INSERT INTO '.self::REVIEWS.'
                    (tenant_ref,approval_ref,request_ref,state)
                    VALUES (:tenant,:review,:request,\'PENDENTE\')');
                $stmt->execute(['tenant'=>$tenant,'review'=>$review,'request'=>$request]);
            }
            $stmt=$this->db->prepare('UPDATE '.self::USAGE.'
                SET used=:used WHERE tenant_ref=:tenant');
            $stmt->execute(['used'=>$number,'tenant'=>$tenant]);
            $this->db->commit();
            return ['state'=>'SIMULATED','used'=>$number,'approval_ref'=>$review];
        } catch (Throwable) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return null;
        }
    }

    public function decide(string $tenant,string $review,string $decision): bool
    {
        if (!self::isSynthetic($tenant) || !self::isSynthetic($review)
            || !in_array($decision,['APROVADO_EM_ENSAIO','REJEITADO_EM_ENSAIO'],true)) {
            return false;
        }
        try {
            $stmt=$this->db->prepare('UPDATE '.self::REVIEWS.' SET state=:decision
                WHERE tenant_ref=:tenant AND approval_ref=:review AND state=\'PENDENTE\'');
            $stmt->execute([
                'decision'=>$decision,'tenant'=>$tenant,'review'=>$review,
            ]);
            return $stmt->rowCount()===1;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return ?array{used:int,events:int,pending:int,approved:int,rejected:int} */
    public function stats(string $tenant): ?array
    {
        if (!self::isSynthetic($tenant)) return null;
        try {
            $stmt=$this->db->prepare('SELECT used FROM '.self::USAGE.'
                WHERE tenant_ref=:tenant');
            $stmt->execute(['tenant'=>$tenant]);
            $used=$stmt->fetchColumn();
            if ($used===false) return null;
            $stmt=$this->db->prepare('SELECT state,COUNT(*) AS total FROM '.self::REVIEWS.'
                WHERE tenant_ref=:tenant GROUP BY state');
            $stmt->execute(['tenant'=>$tenant]);
            $stats=['PENDENTE'=>0,'APROVADO_EM_ENSAIO'=>0,'REJEITADO_EM_ENSAIO'=>0];
            while ($row=$stmt->fetch(PDO::FETCH_ASSOC)) {
                if (array_key_exists($row['state'],$stats)) {
                    $stats[$row['state']]=(int)$row['total'];
                }
            }
            $stmt=$this->db->prepare('SELECT COUNT(*) FROM '.self::EVENTS.'
                WHERE tenant_ref=:tenant');
            $stmt->execute(['tenant'=>$tenant]);
            return [
                'used'=>(int)$used,'events'=>(int)$stmt->fetchColumn(),
                'pending'=>$stats['PENDENTE'],
                'approved'=>$stats['APROVADO_EM_ENSAIO'],
                'rejected'=>$stats['REJEITADO_EM_ENSAIO'],
            ];
        } catch (Throwable) {
            return null;
        }
    }
}
