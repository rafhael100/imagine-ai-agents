<?php
declare(strict_types=1);
namespace ImagineAI\Convergence;

require_once __DIR__.'/PgBoundOutboxLeaseLab.php';
require_once __DIR__.'/PgSyntheticAckEvidenceLab.php';
require_once __DIR__.'/PgOwnerExecutionGuardLab.php';

/**
 * P45 CI-only strict facade for P44-originated events.
 * Company mapping AND owner-signed operation grant are mandatory on lease
 * and on signed ACK. No public API, no real CRM identity or n8n delivery.
 */
final class PgOwnerBoundSignedDispatchLab
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
        int $now,int $tenantRevision,int $mappingRevision):array
    {
        return $this->lease->reserve(
            $tenant,$worker,$now,$tenantRevision,
            $company,$mappingRevision,true
        );
    }

    /** @return array<string,mixed> */
    public function acknowledgeSigned(
        string $receipt,string $signatureB64,string $company,
        string $tenant,string $event,string $worker,int $attempt,int $now,
        int $tenantRevision,int $mappingRevision,string $executorKeyId
    ):array {
        return $this->ack->acknowledgeWithAtomicEvidence(
            $receipt,$signatureB64,$tenant,$event,$worker,$attempt,$now,
            $tenantRevision,$executorKeyId,$company,$mappingRevision,true
        );
    }

    /** @return array{decision:string,reason:string} */
    public static function operationalReadiness():array
    {
        return [
            'decision'=>'NO_GO',
            'reason'=>'P45_ONLY_CI_SYNTHETIC_OWNER_SERVICE_SIGNING',
        ];
    }
}
