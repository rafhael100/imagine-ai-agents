<?php
declare(strict_types=1);

namespace ImagineAI\MockReview;

use ImagineAI\Domain\{MockError, TenantContext};
use ImagineAI\MockCatalog\MockAgentVersionSnapshot;

/** Ephemeral, fake separation-of-duties review. Not real authentication or an approval engine. */
final class MockReviewBoard
{
    /** @var array<string, array<string, array{reviewer:string,decision:string}>> */
    private array $votes = [];

    public function vote(
        MockReviewPacket $packet,
        TenantContext $context,
        string $expectedDigest,
        string $reviewerRef,
        string $role,
        string $decision,
        int $now,
    ): void {
        $packet->assertValid($context, $now);
        MockAgentVersionSnapshot::syntheticRef($reviewerRef);
        if (!preg_match('/^[0-9a-f]{64}$/D', $expectedDigest)
            || !hash_equals($packet->digest, $expectedDigest)) {
            MockError::fail('REVIEW_STALE');
        }
        if (!in_array($role, ['PRODUCT', 'SECURITY'], true)
            || !in_array($decision, ['ACKNOWLEDGE', 'REJECT'], true)) {
            MockError::fail('REVIEW_SCHEMA_DENIED');
        }
        if ($reviewerRef === $packet->authorRef) {
            MockError::fail('REVIEW_SEPARATION_DENIED');
        }
        $existing = $this->votes[$packet->digest] ?? [];
        if (isset($existing[$role])) {
            if ($existing[$role] === ['reviewer' => $reviewerRef, 'decision' => $decision]) return;
            MockError::fail('REVIEW_CONFLICT');
        }
        foreach ($existing as $vote) {
            if ($vote['decision'] === 'REJECT') MockError::fail('REVIEW_REJECTED');
            if ($vote['reviewer'] === $reviewerRef) MockError::fail('REVIEW_SEPARATION_DENIED');
        }
        if ($decision === 'ACKNOWLEDGE' && $packet->reportGate !== 'REVIEW_REQUIRED') {
            MockError::fail('REVIEW_INELIGIBLE');
        }
        $this->votes[$packet->digest][$role] = ['reviewer' => $reviewerRef, 'decision' => $decision];
    }

    public function status(MockReviewPacket $packet, TenantContext $context, int $now): array
    {
        $packet->assertValid($context, $now);
        $votes = $this->votes[$packet->digest] ?? [];
        $rejected = false;
        foreach ($votes as $vote) $rejected = $rejected || $vote['decision'] === 'REJECT';
        $state = $rejected ? 'REJECTED'
            : ($packet->reportGate !== 'REVIEW_REQUIRED' ? 'BLOCKED_EVALUATION'
                : (isset($votes['PRODUCT'], $votes['SECURITY'])
                    ? 'READY_FOR_HUMAN_HANDOFF' : 'AWAITING_INDEPENDENT_REVIEWS'));
        return [
            'mode' => 'SYNTHETIC_ONLY', 'packet_ref' => $packet->ref,
            'packet_digest' => $packet->digest, 'report_gate' => $packet->reportGate,
            'state' => $state, 'review_count' => count($votes),
            'required_roles' => ['PRODUCT', 'SECURITY'],
            'promotion_allowed' => false, 'real_authorization' => false,
        ];
    }
}
