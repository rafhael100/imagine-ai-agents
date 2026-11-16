<?php
declare(strict_types=1);

namespace ImagineAI\MockBridge;

/**
 * F238: construct an inert, synthetic-only HTTP-shaped test fixture.
 *
 * There is deliberately NO host, URI, socket, request dispatcher, transport,
 * credential discovery or grant. The service identity, tenant scope and
 * in-memory replay check all run BEFORE a fixture can be assembled.
 * A returned array never authorizes transmission or reading a CRM lead.
 */
final class MockSyntheticHttpEnvelope
{
    /**
     * @param array<string,mixed> $trustedPublicKeys Trusted PUBLIC laboratory keys.
     * @param array<string,mixed> $trustedScope Synthetic fixture, not CRM IAM.
     * @return ?array<string,mixed> Offline wire shape; never a live HTTP request.
     */
    public static function assemble(
        MockSyntheticEvidenceGate $evidence,
        string $requestJson,
        string $claimsJson,
        string $signatureBase64,
        array $trustedPublicKeys,
        array $trustedScope,
        string $expectedTenant,
        int $now
    ): ?array {
        try {
            if ($requestJson === '' || strlen($requestJson) > 1024
                || $claimsJson === '' || strlen($claimsJson) > 1024
                || strlen($signatureBase64) !== 88
                || str_contains($requestJson, "\r")
                || str_contains($requestJson, "\n")
                || str_contains($claimsJson, "\r")
                || str_contains($claimsJson, "\n")
                || str_contains($signatureBase64, "\r")
                || str_contains($signatureBase64, "\n")) {
                return null;
            }

            // F235/F231 performs original Ed25519 verification, raw JSON
            // binding, exact synthetic tenant scope, and local replay checks.
            // This is evidence of a mock fixture only, not service permission.
            if (!$evidence->checkEvidenceOnce(
                $requestJson, $claimsJson, $signatureBase64,
                $trustedPublicKeys, $trustedScope, $expectedTenant, $now
            )) {
                return null;
            }

            return [
                'method' => 'POST',
                'headers' => [
                    'content-type' => 'application/json',
                    'x-aia-claims' => $claimsJson,
                    'x-aia-signature' => $signatureBase64,
                ],
                'body' => $requestJson,
                'transmission_authorized' => false,
                'real_authorization' => false,
                'operational_decision' => 'NO_GO',
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
