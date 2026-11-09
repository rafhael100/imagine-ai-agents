<?php
declare(strict_types=1);

namespace ImagineAI\MockTrust;

use ImagineAI\Domain\{MockError, TenantContext};
use ImagineAI\MockCatalog\MockAgentVersionSnapshot;

/** Read-only mock trust boundary. No networking, issuer verification or real IAM. */
final class MockTrustGateway
{
    private array $seenNonces = [];
    private readonly array $allowedScopes;

    /** @param list<string> $allowedScopes */
    public function __construct(
        public readonly string $issuerRef,
        public readonly string $audienceRef,
        array $allowedScopes,
    ) {
        MockAgentVersionSnapshot::syntheticRef($issuerRef);
        MockAgentVersionSnapshot::syntheticRef($audienceRef);
        if (!array_is_list($allowedScopes) || count($allowedScopes) === 0
            || count($allowedScopes) > 2) {
            MockError::fail('TRUST_SCHEMA_DENIED');
        }
        foreach ($allowedScopes as $scope) {
            if (!is_string($scope) || !in_array($scope, [
                'SYNTHETIC_LEAD_READ', 'SYNTHETIC_SUMMARY_DRAFT',
            ], true)) MockError::fail('TRUST_SCHEMA_DENIED');
        }
        if (count(array_unique($allowedScopes)) !== count($allowedScopes)) {
            MockError::fail('TRUST_SCHEMA_DENIED');
        }
        $this->allowedScopes = $allowedScopes;
    }

    public function authorize(?MockTrustEnvelope $envelope, ?MockOwnerScopeEvidence $owner,
        TenantContext $context, string $scope, int $now): array
    {
        if ($envelope === null || $owner === null) MockError::fail('TRUST_DENIED');
        $envelope->assertValid($context, $now);
        if ($envelope->issuerRef !== $this->issuerRef || $envelope->audienceRef !== $this->audienceRef
            || !in_array($scope, $this->allowedScopes, true)
            || !in_array($scope, $envelope->scopes, true)) {
            MockError::fail('TRUST_DENIED');
        }
        $owner->assertValid($envelope, $context, $scope, $now);
        if (isset($this->seenNonces[$envelope->nonceRef])) MockError::fail('TRUST_REPLAY_DENIED');
        $this->seenNonces[$envelope->nonceRef] = $envelope->digest;
        return [
            'mode' => 'SYNTHETIC_ONLY', 'state' => 'SYNTHETIC_READ_ONLY',
            'digest' => $envelope->digest, 'scope' => $scope,
            'real_authorization' => false, 'external_calls' => 0,
        ];
    }
}
