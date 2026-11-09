<?php
declare(strict_types=1);

/** Execute locally: php examples/f110-review-demo.php — invented identities only. */
spl_autoload_register(static function (string $class): void {
    $prefix = 'ImagineAI\\';
    if (!str_starts_with($class, $prefix)) return;
    require dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use ImagineAI\Domain\TenantContext;
use ImagineAI\MockEvaluation\{MockEvaluationSuite, MockEvaluationCandidate};
use ImagineAI\MockReview\{MockReviewPacket, MockReviewBoard};

$ctx = new TenantContext('SYNTHETIC_CRM', 'SYNTHETIC_A',
    'SYNTHETIC_AUTHOR', 'SYNTHETIC_EVAL', 'SYNTHETIC_AGENT');
$suite = new MockEvaluationSuite('SYNTHETIC_SUITE', 'SYNTHETIC_DATASET_V1',
    'SYNTHETIC_RULE_V1', $ctx, [
        ['id' => 'SYNTHETIC_TENANT', 'severity' => 'CRITICAL', 'expected' => 'DENY'],
        ['id' => 'SYNTHETIC_INJECTION', 'severity' => 'CRITICAL', 'expected' => 'UNTRUSTED_DATA'],
    ]);
$observed = ['SYNTHETIC_TENANT' => 'DENY', 'SYNTHETIC_INJECTION' => 'UNTRUSTED_DATA'];
$baseline = new MockEvaluationCandidate('SYNTHETIC_BASELINE', 'SYNTHETIC_V1', $suite, $observed);
$candidate = new MockEvaluationCandidate('SYNTHETIC_CANDIDATE', 'SYNTHETIC_V2', $suite, $observed);
$packet = new MockReviewPacket('SYNTHETIC_PACKET', $ctx, $suite, $baseline,
    $candidate, 'SYNTHETIC_AUTHOR', 10, 30);
$board = new MockReviewBoard();
$before = $board->status($packet, $ctx, 15);
$board->vote($packet, $ctx, $packet->digest, 'SYNTHETIC_PRODUCT_REVIEWER',
    'PRODUCT', 'ACKNOWLEDGE', 15);
$board->vote($packet, $ctx, $packet->digest, 'SYNTHETIC_SECURITY_REVIEWER',
    'SECURITY', 'ACKNOWLEDGE', 15);
$after = $board->status($packet, $ctx, 15);
if ($after['state'] !== 'READY_FOR_HUMAN_HANDOFF' || $after['promotion_allowed'] !== false) {
    throw new RuntimeException('The synthetic human-review gate failed');
}
echo json_encode([
    'mode' => 'SYNTHETIC_OFFLINE', 'before' => $before['state'],
    'after' => $after['state'], 'review_count' => $after['review_count'],
    'promotion_allowed' => $after['promotion_allowed'], 'external_calls' => 0,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
