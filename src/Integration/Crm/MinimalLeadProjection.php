<?php
declare(strict_types=1);

namespace ImagineAI\Integration\Crm;

/**
 * A minimal projection from the approved CRM private homologation read.
 *
 * This is a value validator, not service authentication or delegated authority.
 * The caller must obtain the response through the CRM-authenticated session and
 * supply the expected resource independently of the response. No grant, user,
 * company, credential, raw body or operational permission is retained here.
 */
final class MinimalLeadProjection
{
    private const RESPONSE_KEYS = [
        'contract', 'operation', 'status', 'scope', 'projection', 'operational',
    ];
    private const STAGES = ['new', 'qualified', 'proposal', 'won', 'lost'];

    private function __construct(
        public readonly string $ref,
        public readonly string $stage,
    ) {}

    /**
     * Accept only the closed, byte-canonical HTTP 200 contract.
     *
     * Redirects, HTML, synthetic mock responses and all non-200 responses are
     * denied. A canonical byte comparison also rejects duplicate JSON names,
     * including escaped aliases that json_decode would otherwise collapse.
     */
    public static function fromHttpResponse(
        int $httpStatus,
        string $body,
        string $expectedRef,
    ): ?self {
        if ($httpStatus !== 200 || !self::isReference($expectedRef)
            || $body === '' || strlen($body) > 1024) {
            return null;
        }

        try {
            $response = json_decode($body, true, 4, JSON_THROW_ON_ERROR);
            if (!is_array($response)
                || array_keys($response) !== self::RESPONSE_KEYS
                || $response['contract'] !== 'CRM_AIA_HOMOLOGATION_LEAD_READ_V1'
                || $response['operation'] !== 'crm.lead.read_minimal'
                || $response['status'] !== 'READ'
                || $response['scope'] !== 'SYNTHETIC_PRIVATE_HOMOLOGATION'
                || $response['operational'] !== false
                || !is_array($response['projection'])
                || array_keys($response['projection']) !== ['ref', 'stage']) {
                return null;
            }

            $projection = $response['projection'];
            if (!is_string($projection['ref'])
                || !self::isReference($projection['ref'])
                || !hash_equals($expectedRef, $projection['ref'])
                || !is_string($projection['stage'])
                || !in_array($projection['stage'], self::STAGES, true)) {
                return null;
            }

            $canonical = json_encode(
                $response,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
            if (!hash_equals($canonical, $body)) {
                return null;
            }

            return new self($projection['ref'], $projection['stage']);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function isReference(string $ref): bool
    {
        return strlen($ref) === 36
            && preg_match('/\A[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}\z/D', $ref) === 1;
    }
}
