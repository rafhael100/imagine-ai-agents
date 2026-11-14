<?php
declare(strict_types=1);

namespace ImagineAI\MockTelemetry;

use ImagineAI\Domain\TenantContext;
use ImagineAI\MockBridge\{MockCrmReadPublicFacade, MockCrmReadRequest, MockCrmPublicResponseContract};
use ImagineAI\MockTrust\{MockTrustEnvelope, MockOwnerScopeEvidence};

/**
 * Synthetic pre-parser edge wrapper for F1-17.
 * This object consumes PHP arrays, NEVER an HTTP request or a real CRM response.
 */
final class MockCrmReadEdgeBoundary
{
    public function __construct(
        private readonly MockCrmReadPublicFacade $facade,
        private readonly MockCrmReadEdgeCounters $counters,
    ) {}

    /**
     * F217: discard the only local read receipt after an abandoned pilot.
     * No new read, audit, nonce, side effect, or real authorization.
     */
    public function discardReadReceipt(): void
    {
        $this->facade->discardReadReceipt();
    }

    /**
     * Recheck current synthetic permission without consuming a second nonce
     * or a new edge/audit slot; never re-read or reveal policy detail.
     * Only valid for the proof that the caller already used in a read.
     * @param array<string,mixed> $wire
     */
    public function recheck(
        array $wire,
        ?MockTrustEnvelope $envelope,
        ?MockOwnerScopeEvidence $owner,
        TenantContext $trustedContext,
        int $now,
    ): bool {
        return $this->facade->recheck(
            $wire, $envelope, $owner, $trustedContext, $now);
    }

    /**
     * Offline JSON-byte adapter for the fictional OP-01 contract. No HTTP.
     * Failures consume the ordinary bounded SCHEMA_DENIED edge slot; an
     * invalid new attempt must also discard every prior local read receipt.
     */
    public function handleCanonicalJson(
        string $body,
        ?MockTrustEnvelope $envelope,
        ?MockOwnerScopeEvidence $owner,
        TenantContext $trustedContext,
        int $now,
    ): array {
        $this->discardReadReceipt();
        try {
            $wire = MockCrmReadRequest::decodeCanonicalJson($body);
        } catch (\Throwable) {
            return $this->handle([], $envelope, $owner, $trustedContext, $now);
        }
        return $this->handle($wire, $envelope, $owner, $trustedContext, $now);
    }

    /**
     * F221: a complete in-process, byte-to-byte fictional exchange.
     * Never an HTTP endpoint, credential verifier, IAM or real CRM transport.
     * Every attempt traverses the existing edge before a reply is encoded.
     *
     * Positive responses remain READ_SYNTHETIC_ONLY with
     * real_authorization=false and confer no operational rights.
     */
    public function exchangeCanonicalJson(
        string $requestBytes,
        ?MockTrustEnvelope $envelope,
        ?MockOwnerScopeEvidence $owner,
        TenantContext $trustedContext,
        int $now,
    ): string {
        // Handles schema-denial counters, replay and receipt invalidation,
        // including when the bytes cannot be decoded as a request.
        $response = $this->handleCanonicalJson(
            $requestBytes, $envelope, $owner, $trustedContext, $now
        );
        try {
            $wire = MockCrmReadRequest::decodeCanonicalJson($requestBytes);
            $request = new MockCrmReadRequest($wire);
            $bytes = MockCrmPublicResponseContract::encodeCanonicalJson(
                $response, $request, $trustedContext
            );
            if ($bytes === null) {
                throw new \LogicException('UNSAFE_SYNTHETIC_EXCHANGE');
            }
            return $bytes;
        } catch (\Throwable) {
            // Failed framing must revoke even a formerly successful mock
            // receipt; return only the fixed, privacy-minimal denial.
            $this->discardReadReceipt();
            return json_encode(
                MockCrmPublicResponseContract::denial(),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
            );
        }
    }

    /** @param array<string,mixed> $wire Fake local values only. */
    public function handle(
        array $wire,
        ?MockTrustEnvelope $envelope,
        ?MockOwnerScopeEvidence $owner,
        TenantContext $trustedContext,
        int $now,
    ): array {
        // The edge owns admission: even a malformed or capacity-denied new
        // attempt must not leave an earlier positive facade receipt reusable.
        // Also taints an in-flight reader if it re-enters this edge.
        $this->facade->discardReadReceipt();
        try {
            $ticket = $this->counters->begin();
        } catch (\Throwable) {
            return MockCrmPublicResponseContract::denial();
        }

        try {
            $request = new MockCrmReadRequest($wire);
        } catch (\Throwable) {
            // Parsing failed BEFORE the F1-13 audit decorator; count category only.
            try { $this->counters->finish($ticket, 'SCHEMA_DENIED'); } catch (\Throwable) {}
            return MockCrmPublicResponseContract::denial();
        }

        try {
            $result = $this->facade->handle($wire, $envelope, $owner, $trustedContext, $now);
            if (!MockCrmPublicResponseContract::isSafe($result, $request, $trustedContext)) {
                throw new \LogicException('UNSAFE_SYNTHETIC_EDGE_RESULT');
            }
            $outcome = $result['state'] === 'READ_SYNTHETIC_ONLY'
                ? 'READ_SYNTHETIC_ONLY' : 'DENIED_OR_NOT_FOUND';
            $this->counters->finish($ticket, $outcome);
            return $result;
        } catch (\Throwable) {
            // A facade success is not a successful edge read if counters fail
            // after it. Revoke the temporary receipt before returning denial.
            $this->facade->discardReadReceipt();
            // Never return potentially unsafe data if telemetry cannot be completed.
            try { $this->counters->finish($ticket, 'INTERNAL_DENIED'); } catch (\Throwable) {}
            return MockCrmPublicResponseContract::denial();
        }
    }

}
