<?php
declare(strict_types=1);
namespace ImagineAI\Labs;

use PDO;
use Throwable;
require_once __DIR__.'/SyntheticUsageReceipt.php';

/**
 * P8: synthetic PostgreSQL16 credit reservation and reconciliation.
 * NO model API, CRM, webhook, payment or real entitlement. CI ONLY.
 *
 * Credits are technical integer test units, never a billing currency.
 */
final readonly class PgAiCreditReservationLab
{
    private const ACCOUNTS='public.aia_lab_credit_accounts_p8';
    private const REQUESTS='public.aia_lab_credit_requests_p8';

    public function __construct(
        private PDO $db,
        private ?string $trustedUsagePublicKey = null,
    ) {
        if (getenv('AIA_P8_PG_LAB')!=='true'
            || getenv('GITHUB_ACTIONS')!=='true'
            || getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
            || getenv('DB_HOST')!=='127.0.0.1'
            || getenv('DB_DATABASE')!=='imagine_agents_ci'
            || getenv('DB_USERNAME')!=='postgres'
            || $db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='pgsql') {
            throw new \RuntimeException('P8_DISPOSABLE_ENVIRONMENT_ONLY');
        }
        $row=$db->query('SELECT current_database() AS db,current_user AS name')
            ->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || $row['db']!=='imagine_agents_ci'
            || $row['name']!=='postgres') {
            throw new \RuntimeException('P8_DB_IDENTITY_DENIED');
        }
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        if ($trustedUsagePublicKey !== null && strlen($trustedUsagePublicKey)!==32) {
            throw new \RuntimeException('P11_INVALID_TRUST_ANCHOR');
        }
    }

    public function createTables(): void
    {
        $this->db->exec('CREATE TABLE '.self::ACCOUNTS.' (
            tenant_ref varchar(90) PRIMARY KEY,
            allowance integer NOT NULL CHECK (allowance BETWEEN 0 AND 1000000),
            spent integer NOT NULL DEFAULT 0 CHECK (spent>=0),
            held integer NOT NULL DEFAULT 0 CHECK (held>=0),
            frozen boolean NOT NULL DEFAULT false,
            CHECK (spent+held<=allowance)
        )');
        $this->db->exec('CREATE TABLE '.self::REQUESTS.' (
            tenant_ref varchar(90) NOT NULL,
            request_ref varchar(90) NOT NULL,
            fingerprint char(64) NOT NULL,
            estimated integer NOT NULL CHECK (estimated BETWEEN 1 AND 5000),
            state varchar(18) NOT NULL CHECK (state IN (
                \'RESERVED\',\'DISPATCHED\',\'UNCERTAIN\',\'FINALIZED\',\'CANCELED\',\'DISPUTED\'
            )),
            observed integer,
            proof_hash char(64),
            PRIMARY KEY (tenant_ref,request_ref)
        )');
    }

    public function dropTables(): void
    {
        $this->db->exec('DROP TABLE IF EXISTS '.self::REQUESTS);
        $this->db->exec('DROP TABLE IF EXISTS '.self::ACCOUNTS);
    }

    /** Fixture funding only, once per tenant, no purchase/payment. */
    public function seedSynthetic(string $tenant,int $allowance): bool
    {
        if (!self::ref($tenant) || $allowance<0 || $allowance>1000000) return false;
        try {
            $stmt=$this->db->prepare('INSERT INTO '.self::ACCOUNTS.'
                (tenant_ref,allowance) VALUES (:tenant,:budget) ON CONFLICT DO NOTHING');
            $stmt->execute(['tenant'=>$tenant,'budget'=>$allowance]);
            return $stmt->rowCount()===1;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return ?array{state:string,estimated:int}
     * A replay returns REPLAY (no new hold). Conflicting payload is denied.
     */
    public function reserve(
        string $tenant,string $request,string $purpose,int $estimated
    ): ?array {
        if (!self::ref($tenant) || !self::ref($request)
            || !self::ref($purpose) || $estimated<1 || $estimated>5000) return null;
        $fingerprint=hash('sha256',implode("\n",[
            'AIA_LAB_P8_RESERVATION_V1',$tenant,$request,$purpose,(string)$estimated,
        ]));
        try {
            $this->db->beginTransaction();
            $account=$this->accountForUpdate($tenant);
            if (!$account || self::isFrozen($account)) {
                $this->db->rollBack();
                return null;
            }
            $stmt=$this->db->prepare('SELECT fingerprint,estimated,state
                FROM '.self::REQUESTS.'
                WHERE tenant_ref=:tenant AND request_ref=:request');
            $stmt->execute(['tenant'=>$tenant,'request'=>$request]);
            $existing=$stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($existing)) {
                if (!hash_equals($existing['fingerprint'],$fingerprint)
                    // Replays after dispatch/timeout/finalization/cancel are
                    // never eligible for another dispatch or reservation.
                    || $existing['state']!=='RESERVED') {
                    $this->db->rollBack();
                    return null;
                }
                $this->db->commit();
                return ['state'=>'REPLAY','estimated'=>(int)$existing['estimated']];
            }
            $remaining=(int)$account['allowance']-(int)$account['spent']-(int)$account['held'];
            if ($estimated>$remaining) {
                $this->db->rollBack();
                return null;
            }
            $insert=$this->db->prepare('INSERT INTO '.self::REQUESTS.'
                (tenant_ref,request_ref,fingerprint,estimated,state)
                VALUES (:tenant,:request,:hash,:estimate,\'RESERVED\')');
            $insert->execute([
                'tenant'=>$tenant,'request'=>$request,
                'hash'=>$fingerprint,'estimate'=>$estimated,
            ]);
            $update=$this->db->prepare('UPDATE '.self::ACCOUNTS.'
                SET held=held+:amount WHERE tenant_ref=:tenant');
            $update->execute(['tenant'=>$tenant,'amount'=>$estimated]);
            $this->db->commit();
            return ['state'=>'RESERVED','estimated'=>$estimated];
        } catch (Throwable) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return null;
        }
    }

    /**
     * Mark the synthetic outbound handoff BEFORE a remote side effect could
     * occur. This is an in-process state marker, NOT a provider call/proof.
     * Once dispatched, neither replay nor pre-dispatch cancellation is allowed.
     */
    public function markDispatched(string $tenant,string $request): bool
    {
        return $this->transitionOnly($tenant,$request,'RESERVED','DISPATCHED');
    }

    /** A timeout or unknown provider result MUST keep the whole hold. */
    public function markUnknown(string $tenant,string $request): bool
    {
        return $this->transitionOnly($tenant,$request,'DISPATCHED','UNCERTAIN');
    }

    /**
     * Explicitly proven pre-dispatch cancellation is the ONLY supported
     * release without an observed provider measurement.
     */
    public function cancelBeforeDispatch(string $tenant,string $request): bool
    {
        if (!self::ref($tenant) || !self::ref($request)) return false;
        try {
            $this->db->beginTransaction();
            $account=$this->accountForUpdate($tenant);
            $item=$this->requestForUpdate($tenant,$request);
            if (!$account || self::isFrozen($account) || !$item
                || $item['state']!=='RESERVED') {
                $this->db->rollBack();
                return false;
            }
            $stmt=$this->db->prepare('UPDATE '.self::REQUESTS.'
                SET state=\'CANCELED\' WHERE tenant_ref=:tenant AND request_ref=:request');
            $stmt->execute(['tenant'=>$tenant,'request'=>$request]);
            $stmt=$this->db->prepare('UPDATE '.self::ACCOUNTS.'
                SET held=held-:units WHERE tenant_ref=:tenant');
            $stmt->execute(['units'=>$item['estimated'],'tenant'=>$tenant]);
            $this->db->commit();
            return true;
        } catch (Throwable) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return false;
        }
    }

    /**
     * Only a valid Ed25519 lab fixture signed by the explicitly trusted
     * verifier can enter the consumption path. A caller-supplied key,
     * numeric amount or unverified provider JSON is NEVER sufficient.
     * This does not assert a real provider identity or external authorization.
     */
    public function reconcileSigned(
        string $tenant,string $request,
        string $canonicalReceipt,string $signatureB64,int $now
    ): bool {
        if (getenv('AIA_P11_SIGNED_PROOFS')!=='true'
            || $this->trustedUsagePublicKey===null) return false;
        $proof=SyntheticUsageReceipt::verify(
            $canonicalReceipt,$signatureB64,$this->trustedUsagePublicKey,$now
        );
        if ($proof===null || $proof['tenant_ref']!==$tenant
            || $proof['request_ref']!==$request) return false;
        return $this->reconcileMeasurement(
            $tenant,$request,$proof['observed'],hash('sha256',$canonicalReceipt)
        );
    }

    /**
     * Accounting primitive reachable only after verified, scoped receipt.
     * Conflicting measurement OR receipt identity freezes the tenant.
     */
    private function reconcileMeasurement(
        string $tenant,string $request,int $observed,string $proofHash
    ): bool
    {
        if (!self::ref($tenant) || !self::ref($request)
            || $observed<0 || $observed>5000) return false;
        try {
            $this->db->beginTransaction();
            $account=$this->accountForUpdate($tenant);
            $item=$this->requestForUpdate($tenant,$request);
            if (!$account || !$item || self::isFrozen($account)) {
                $this->db->rollBack();
                return false;
            }
            if ($item['state']==='FINALIZED') {
                if ((int)$item['observed']===$observed
                    && is_string($item['proof_hash'])
                    && hash_equals($item['proof_hash'],$proofHash)) {
                    $this->db->commit();
                    return true; // Duplicate proof never charges twice.
                }
                $this->freeze($tenant,$request);
                $this->db->commit();
                return false;
            }
            if (!in_array($item['state'],['DISPATCHED','UNCERTAIN'],true)) {
                $this->db->rollBack();
                return false;
            }
            if ($observed>(int)$item['estimated']) {
                // Conservatively keep the hold and freeze; no implicit
                // additional charge or automatic refund is allowed.
                $this->freeze($tenant,$request);
                $this->db->commit();
                return false;
            }
            $stmt=$this->db->prepare('UPDATE '.self::REQUESTS.'
                SET state=\'FINALIZED\',observed=:actual,proof_hash=:proof
                WHERE tenant_ref=:tenant AND request_ref=:request');
            $stmt->execute([
                'actual'=>$observed,'proof'=>$proofHash,
                'tenant'=>$tenant,'request'=>$request,
            ]);
            $stmt=$this->db->prepare('UPDATE '.self::ACCOUNTS.'
                SET spent=spent+:actual,held=held-:reserve
                WHERE tenant_ref=:tenant');
            $stmt->execute([
                'actual'=>$observed,'reserve'=>$item['estimated'],'tenant'=>$tenant,
            ]);
            $this->db->commit();
            return true;
        } catch (Throwable) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return false;
        }
    }

    /** @return ?array{allowance:int,spent:int,held:int,available:int,frozen:bool} */
    public function snapshot(string $tenant): ?array
    {
        if (!self::ref($tenant)) return null;
        try {
            $stmt=$this->db->prepare('SELECT allowance,spent,held,frozen
                FROM '.self::ACCOUNTS.' WHERE tenant_ref=:tenant');
            $stmt->execute(['tenant'=>$tenant]);
            $r=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($r)) return null;
            return [
                'allowance'=>(int)$r['allowance'],
                'spent'=>(int)$r['spent'],
                'held'=>(int)$r['held'],
                'available'=>(int)$r['allowance']-(int)$r['spent']-(int)$r['held'],
                'frozen'=>in_array($r['frozen'],[true,1,'1','t'],true),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private function transitionOnly(
        string $tenant,string $request,string $expected,string $target
    ): bool {
        if (!self::ref($tenant) || !self::ref($request)) return false;
        try {
            $this->db->beginTransaction();
            $account=$this->accountForUpdate($tenant);
            $item=$this->requestForUpdate($tenant,$request);
            if (!$account || self::isFrozen($account) || !$item
                || $item['state']!==$expected) {
                $this->db->rollBack();
                return false;
            }
            $stmt=$this->db->prepare('UPDATE '.self::REQUESTS.'
                SET state=:state WHERE tenant_ref=:tenant AND request_ref=:request');
            $stmt->execute(['state'=>$target,'tenant'=>$tenant,'request'=>$request]);
            $this->db->commit();
            return true;
        } catch (Throwable) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return false;
        }
    }

    private function freeze(string $tenant,string $request): void
    {
        $stmt=$this->db->prepare('UPDATE '.self::ACCOUNTS.'
            SET frozen=true WHERE tenant_ref=:tenant');
        $stmt->execute(['tenant'=>$tenant]);
        $stmt=$this->db->prepare('UPDATE '.self::REQUESTS.'
            SET state=\'DISPUTED\' WHERE tenant_ref=:tenant AND request_ref=:request');
        $stmt->execute(['tenant'=>$tenant,'request'=>$request]);
    }

    /** @return ?array<string,mixed> */
    private function accountForUpdate(string $tenant): ?array
    {
        $stmt=$this->db->prepare('SELECT * FROM '.self::ACCOUNTS.'
            WHERE tenant_ref=:tenant FOR UPDATE');
        $stmt->execute(['tenant'=>$tenant]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row)?$row:null;
    }

    /** @return ?array<string,mixed> */
    private function requestForUpdate(string $tenant,string $request): ?array
    {
        $stmt=$this->db->prepare('SELECT * FROM '.self::REQUESTS.'
            WHERE tenant_ref=:tenant AND request_ref=:request FOR UPDATE');
        $stmt->execute(['tenant'=>$tenant,'request'=>$request]);
        return is_array($row=$stmt->fetch(PDO::FETCH_ASSOC))?$row:null;
    }

    /** PDO PostgreSQL normally returns a literal 't' or 'f' string. */
    private static function isFrozen(array $account): bool
    {
        return in_array($account['frozen'],[true,1,'1','t'],true);
    }

    private static function ref(string $id): bool
    {
        return strlen($id)<=90
            && preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,80}\z/D',$id)===1;
    }
}
