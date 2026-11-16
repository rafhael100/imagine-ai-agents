<?php
declare(strict_types=1);

namespace ImagineAI\AutomationFirst;

/**
 * AUTO-IA-01: pure, offline, synthetically-scoped route selection.
 * This is NOT IAM, a gateway, consent, quota reservation or permission to call a model.
 * It has no provider SDK, network, DB, execution or billing side effect.
 */
final class AutoIaRouter
{
    private const MATRIX = [
        'imagine-portal' => [
            'service.event.notify' => 'NO_AI',
            'subscription.status.project' => 'NO_AI',
            'support.message.interpret' => 'UNSTRUCTURED_LANGUAGE',
        ],
        'imagine-crm' => [
            'lead.ingest' => 'NO_AI',
            'lead.score.rules' => 'NO_AI',
            'lead.message.interpret' => 'UNSTRUCTURED_LANGUAGE',
        ],
        'imagine-sites' => [
            'briefing.register' => 'NO_AI',
            'site.approval.notify' => 'NO_AI',
            'site.copy.propose' => 'CREATIVE_DRAFT',
        ],
        'imagine-comercial' => [
            'quote.calculate' => 'NO_AI',
            'funnel.calculate' => 'NO_AI',
            'campaign.copy.propose' => 'CREATIVE_DRAFT',
        ],
        'imagine-performance-ai' => [
            'campaign.metrics.aggregate' => 'NO_AI',
            'management.fee.calculate' => 'NO_AI',
            'ad.copy.propose' => 'CREATIVE_DRAFT',
        ],
        'imagine-universidade' => [
            'lesson.progress.record' => 'NO_AI',
            'eligibility.snapshot.check' => 'NO_AI',
            'tutor.question.interpret' => 'UNSTRUCTURED_LANGUAGE',
        ],
        'imagine-seo-engine' => [
            'hreflang.audit' => 'NO_AI',
            'sitemap.generate' => 'NO_AI',
            'meta.copy.propose' => 'CREATIVE_DRAFT',
        ],
        'imagine-ai-agents' => [
            'usage.quota.evaluate' => 'NO_AI',
            'run.status.project' => 'NO_AI',
            'document.summary.propose' => 'MULTI_SOURCE_INTERPRETATION',
        ],
    ];

    /** @return array<string,array<string,string>> Read-only business taxonomy, not a grant. */
    public static function matrix(): array
    {
        return self::MATRIX;
    }

    /**
     * Input is an exact closed six-field synthetic fixture.
     * Output has NO executable instruction, token, cost, URL or provider details.
     * @param array<string,mixed> $event
     * @return array{decision:string,reason:string,provider_calls:int,model_tokens:int}
     */
    public static function classify(array $event): array
    {
        $required = ['schema', 'product', 'operation', 'tenant_ref', 'event_ref', 'semantic_reason'];
        $keys = array_keys($event);
        sort($keys);
        $expected = $required;
        sort($expected);
        if ($keys !== $expected) return self::deny('INVALID_SCHEMA');
        foreach ($required as $key) {
            if (!is_string($event[$key])) return self::deny('INVALID_SCHEMA');
        }
        if ($event['schema'] !== 'AUTO_IA_EVENT_V1'
            || !self::syntheticRef($event['tenant_ref'])
            || !self::syntheticRef($event['event_ref'])
            || strlen($event['operation']) > 80
            || strlen($event['product']) > 80
            || strlen($event['semantic_reason']) > 64) {
            return self::deny('INVALID_SCHEMA');
        }

        $kind = self::MATRIX[$event['product']][$event['operation']] ?? null;
        if ($kind === null) return self::deny('UNRECOGNIZED_OPERATION');
        if ($kind === 'NO_AI') {
            // A caller's assertion that it "needs AI" cannot override the catalog.
            return self::out('NO_AI', 'DETERMINISTIC_OPERATION');
        }
        if ($event['semantic_reason'] !== $kind) {
            return self::deny('SEMANTIC_JUSTIFICATION_REQUIRED');
        }

        // A further independent gateway must verify real IAM, consent, budget,
        // tenant scope, provider allowlist and idempotency before any spend.
        return self::out('AI_REVIEW_REQUIRED', 'GATEWAY_AUTHORIZATION_NOT_IMPLEMENTED');
    }

    private static function syntheticRef(string $value): bool
    {
        return strlen($value) <= 64
            && preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D', $value) === 1;
    }

    /** @return array{decision:string,reason:string,provider_calls:int,model_tokens:int} */
    private static function deny(string $reason): array
    {
        return self::out('DENY', $reason);
    }

    /** @return array{decision:string,reason:string,provider_calls:int,model_tokens:int} */
    private static function out(string $decision, string $reason): array
    {
        return [
            'decision' => $decision,
            'reason' => $reason,
            'provider_calls' => 0,
            'model_tokens' => 0,
        ];
    }
}
