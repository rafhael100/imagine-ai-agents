<?php
declare(strict_types=1);
namespace ImagineAI\Convergence;

require_once __DIR__.'/PgBoundOutboxLeaseLab.php';
require_once __DIR__.'/PgSyntheticAckEvidenceLab.php';

/**
 * P43 mandatory-lab facade for P42-originated events.
 * A non-null company and its *current* revision must be rechecked while
 * the underlying P28/P30 transaction owns the rows. Earlier P42 stage
 * alone never grants future transport or execution permission.
 */
final class PgCompanyBoundSignedDispatchLab
{
    private PgBoundOutboxLeaseLab $lease;
    private PgAtomicSignedEvidenceAckLab $ack;

    public function __construct(\PDO $db)
    {
        $this->lease=new PgBoundOutboxLeaseLab($db);
        $this->ack=new PgAtomicSignedEvidenceAckLab($db);
    }

    /** @return array<string,mixed> */
    public function reserve(string $company,string $tenant,string $worker,
        int $now,int $tenantRevision,int $companyRevision):array
    {
        return $this->lease->reserve(
            $tenant,$worker,$now,$tenantRevision,$company,$companyRevision
        );
    }

    /** @return array<string,mixed> */
    public function acknowledgeSigned(string $receipt,string $signatureB64,
        string $company,string $tenant,string $event,string $worker,
        int $attempt,int $now,int $tenantRevision,int $companyRevision,
        string $trustedKeyId):array
    {
        return $this->ack->acknowledgeWithAtomicEvidence(
            $receipt,$signatureB64,$tenant,$event,$worker,$attempt,$now,
            $tenantRevision,$trustedKeyId,$company,$companyRevision
        );
    }

    /** @return array{decision:string,reason:string} */
    public static function operationalReadiness():array
    {
        return [
            'decision'=>'NO_GO',
            'reason'=>'P43_IS_CI_ONLY_NEEDS_REAL_CALLER_AUTHORIZATION',
        ];
    }
}
