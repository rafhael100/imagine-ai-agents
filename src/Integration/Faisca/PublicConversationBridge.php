<?php
declare(strict_types=1);

namespace ImagineAI\Integration\Faisca;

use ImagineAI\Contracts\AiSuggestionProvider;
use ImagineAI\Domain\{Run, TenantContext};
use ImagineAI\MockKnowledge\{
    MockContextEnvelope, MockKnowledgeCatalog, MockKnowledgeRetriever,
    MockKnowledgeSource, MockKnowledgeTrace, MockRetrievalGrant
};
use ImagineAI\MockOrchestration\SupervisedCoordinator;
use ImagineAI\Mocks\MockAiSuggestionProvider;

/**
 * One synthetic Faísca invocation, with no I/O and no customer identity.
 * The commercial prose remains in Comercial. This bridge processes only
 * opaque correlation, hashes and references admitted by a pinned policy.
 */
final class PublicConversationBridge
{
    public const MAX_REQUEST_BYTES = 16384;
    public const POLICY_SHA256 = '1ae54bb9f1b8113ca4444c27a229d0359d9181290a5c788daa8c6854f12163ec';
    private const RESOURCE = 'SYNTHETIC_FAISCA_PUBLIC';
    private const KNOWLEDGE_VERSION = 'SYNTHETIC_FAISCA_EDITORIAL_2';
    private const KNOWLEDGE_BASE = 'SYNTHETIC_FAISCA_PUBLIC_BASE';
    private const CONTEXT_LIFETIME = 30;
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
    private readonly array $policy;
    private readonly AiSuggestionProvider $provider;
    private string $phase = 'idle';
    private ?array $request = null;
    private ?array $suggestion = null;
    private ?array $selectedKnowledge = null;
    private ?TenantContext $context = null;
    private ?MockKnowledgeCatalog $catalog = null;
    private ?MockKnowledgeSource $source = null;
    private ?MockKnowledgeTrace $knowledgeTrace = null;
    private ?MockRetrievalGrant $grant = null;
    private ?MockContextEnvelope $pendingContext = null;
    private ?DenyOnlyEffectGateway $gateway = null;
    private ?SupervisedCoordinator $coordinator = null;
    private ?Run $run = null;
    private ?int $preparedAt = null;

    /** The policy and optional test provider are trusted host dependencies. */
    public function __construct(string $policyJson, ?AiSuggestionProvider $provider = null)
    {
        if (strlen($policyJson) > 4096
            || !hash_equals(self::POLICY_SHA256, hash('sha256', $policyJson))) {
            self::deny('context_denied');
        }
        try {
            $policy = json_decode($policyJson, true, 5, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            self::deny('context_denied');
        }
        if (!self::closedObject($policy, ['schema', 'scope', 'knowledgeVersion', 'knowledgeDigest', 'sourceIds', 'actionIds'])
            || $policy['schema'] !== 'faisca.public-context.v1'
            || $policy['scope'] !== 'public_comercial'
            || $policy['knowledgeVersion'] !== 'faisca-editorial-2'
            || !self::hex($policy['knowledgeDigest'], 64)
            || !is_array($policy['sourceIds']) || !array_is_list($policy['sourceIds'])
            || !is_array($policy['actionIds']) || !array_is_list($policy['actionIds'])) {
            self::deny('context_denied');
        }
        $this->policy = $policy;
        $this->provider = $provider ?? new MockAiSuggestionProvider();
    }

    /** Execute the existing PHP suggestion once; delivery is a separate recheck. */
    public function prepare(string $requestJson, int $now): void
    {
        if ($this->phase !== 'idle') self::deny('unavailable');
        $this->phase = 'preparing';
        try {
            $this->request = $this->decodeRequest($requestJson);
            if ($now < 0 || $now > 4102444700) self::deny('context_denied');
            $this->preparedAt = $now;
            $this->context = new TenantContext(
                'SYNTHETIC_FAISCA_COMERCIAL', 'SYNTHETIC_FAISCA_PUBLIC',
                'SYNTHETIC_FAISCA_LAB_HOST', 'SYNTHETIC_FAISCA_PUBLIC_GUIDANCE',
                self::RESOURCE,
            );
            // A synthetic passage binds the admitted reference set. It is not
            // a retrieved commercial document, claim, price or customer record.
            $referenceDigest = strtoupper(hash('sha256', json_encode([
                'policy' => $this->policy['knowledgeDigest'],
                'sourceIds' => $this->request['context']['sourceIds'],
                'actionIds' => $this->request['context']['actionIds'],
            ], self::JSON_FLAGS)));
            $this->source = new MockKnowledgeSource(
                'SYNTHETIC_FAISCA_CONTEXT_' . $referenceDigest,
                self::KNOWLEDGE_VERSION, $this->context, self::KNOWLEDGE_BASE,
                'SYNTHETIC_FAISCA_REVIEWED_REFERENCES', 'SYNTHETIC_FAISCA_EDITORIAL_OWNER',
                'SYNTHETIC_FAISCA_REFERENCE_DIGEST', 'SYNTHETIC_' . $referenceDigest,
                'SYNTHETIC_FAISCA_CONTEXT_' . $referenceDigest,
                $now, $now + self::CONTEXT_LIFETIME, 'OFFICIAL_PRODUCT', 'PUBLIC_MOCK',
            );
            $this->catalog = new MockKnowledgeCatalog();
            $this->catalog->add($this->source);
            $this->knowledgeTrace = new MockKnowledgeTrace();
            $this->grant = new MockRetrievalGrant(
                'SYNTHETIC_FAISCA_PUBLIC_GRANT', $this->context, self::KNOWLEDGE_BASE,
                self::KNOWLEDGE_VERSION, [$this->source->ref],
                $now, $now + self::CONTEXT_LIFETIME,
            );
            $retriever = new MockKnowledgeRetriever($this->catalog, $this->knowledgeTrace);
            $this->pendingContext = $retriever->select($this->grant, $this->context, [$this->source->ref], $now);
            $this->selectedKnowledge = $this->pendingContext->deliver($this->context, $now);
            $this->assertKnowledge($this->selectedKnowledge);

            $this->gateway = new DenyOnlyEffectGateway();
            $this->coordinator = new SupervisedCoordinator($this->context, $this->gateway, $this->provider);
            $runId = 'SYNTHETIC_' . strtoupper($this->request['invocationId']);
            $this->coordinator->assertPilotRunReady($runId, $this->context);
            $this->run = $this->coordinator->start($runId);
            $this->coordinator->step($this->run, $runId . '_STEP');
            try {
                $this->suggestion = $this->coordinator->suggest($this->run, [
                    'ref' => 'SYNTHETIC_' . strtoupper($this->request['requestFingerprint']),
                    'stage' => 'SYNTHETIC_' . strtoupper($this->request['candidateDigest']),
                    'text' => $this->selectedKnowledge['passages'][0]['text'],
                ]);
                $this->assertSuggestion();
            } catch (\Throwable) {
                self::deny('invalid_suggestion');
            }
            $this->phase = 'prepared';
        } catch (\Throwable $error) {
            $this->failRun();
            $this->phase = 'failed';
            $this->closeContext();
            if ($error instanceof \DomainException && in_array($error->getMessage(), [
                'invalid_request', 'context_denied', 'source_denied', 'invalid_suggestion',
            ], true)) throw $error;
            self::deny('unavailable');
        }
    }

    /** Recheck the actual grant/source at delivery, including after a host delay. */
    public function deliver(int $now): array
    {
        if ($this->phase !== 'prepared') self::deny('unavailable');
        try {
            try {
                if ($now < $this->preparedAt) self::deny('context_denied');
                $knowledge = $this->pendingContext->deliver($this->context, $now);
                $this->assertKnowledge($knowledge);
                if ($knowledge !== $this->selectedKnowledge) self::deny('context_denied');
            } catch (\Throwable) {
                self::deny('context_denied');
            }
            $this->assertSuggestion();
            $this->coordinator->transition($this->run, 'CONCLUIDA');
            $events = $this->coordinator->eventsForRun($this->run);
            $codes = array_column($events, 'code');
            $suggestions = count(array_filter($codes, static fn (string $code): bool => $code === 'SYNTHETIC_SUGGESTED'));
            $effects = count(array_filter($codes, static fn (string $code): bool => in_array($code, [
                'SYNTHETIC_SUBMITTED', 'SYNTHETIC_CONFIRMADO', 'SYNTHETIC_PENDENTE', 'SYNTHETIC_INDETERMINADO',
            ], true)));
            if ($codes !== ['SYNTHETIC_STARTED', 'SYNTHETIC_STEP_CREATED', 'SYNTHETIC_SUGGESTED', 'SYNTHETIC_RUN_CHANGED']
                || $suggestions !== 1 || $effects !== 0 || $this->gateway->attemptCount() !== 0
                || $this->run->state() !== 'CONCLUIDA') self::deny('invalid_suggestion');
            $result = [
                'schema' => 'faisca.agents.result.v1',
                'invocationId' => $this->request['invocationId'],
                'requestFingerprint' => $this->request['requestFingerprint'],
                'candidateDigest' => $this->request['candidateDigest'],
                'conversationId' => $this->request['conversationId'],
                'messageId' => $this->request['messageId'],
                'locale' => 'pt-BR',
                'context' => [
                    'scope' => $this->policy['scope'],
                    'knowledgeVersion' => $this->policy['knowledgeVersion'],
                    'knowledgeDigest' => $this->policy['knowledgeDigest'],
                ],
                'suggestion' => $this->suggestion,
                'evidence' => [
                    'runState' => $this->run->state(), 'suggestions' => $suggestions,
                    'effects' => $effects, 'knowledgePassages' => count($knowledge['passages']),
                ],
                'operationalEffects' => false,
            ];
            $this->phase = 'delivered';
            $this->closeContext();
            return $result;
        } catch (\Throwable $error) {
            $this->failRun();
            $this->phase = 'failed';
            $this->closeContext();
            if ($error instanceof \DomainException && in_array($error->getMessage(), [
                'context_denied', 'invalid_suggestion',
            ], true)) throw $error;
            self::deny('unavailable');
        }
    }

    /** Trusted fixture control: can only remove the pending mock permission. */
    public function revokePendingContext(): void
    {
        if (!in_array($this->phase, ['preparing', 'prepared'], true) || $this->grant === null) self::deny('unavailable');
        $this->grant->revoke();
    }

    /** Trusted test control, never a request field or public tool. */
    public function disablePendingSource(): void
    {
        if (!in_array($this->phase, ['preparing', 'prepared'], true) || $this->catalog === null || $this->source === null) {
            self::deny('unavailable');
        }
        $this->catalog->disable($this->source->ref, $this->source->version, $this->context);
    }

    /** Local diagnostic counts only: no identifiers, hashes or request content. */
    public function inspect(): array
    {
        $codes = array_column($this->coordinator?->events() ?? [], 'code');
        $knowledgeCodes = array_column($this->knowledgeTrace?->events() ?? [], 'code');
        return [
            'phase' => $this->phase, 'runState' => $this->run?->state(),
            'suggestions' => count(array_filter($codes, static fn (string $code): bool => $code === 'SYNTHETIC_SUGGESTED')),
            'gatewayAttempts' => $this->gateway?->attemptCount() ?? 0,
            'knowledgeDeliveries' => count(array_filter($knowledgeCodes, static fn (string $code): bool => $code === 'SYNTHETIC_DELIVERED')),
        ];
    }

    private function decodeRequest(string $bytes): array
    {
        if ($bytes === '' || strlen($bytes) > self::MAX_REQUEST_BYTES) self::deny('invalid_request');
        try {
            $value = json_decode($bytes, true, 7, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            self::deny('invalid_request');
        }
        if (!self::closedObject($value, [
            'schema', 'invocationId', 'requestFingerprint', 'candidateDigest',
            'conversationId', 'messageId', 'locale', 'context',
        ]) || $value['schema'] !== 'faisca.agents.request.v1'
            || !self::hex($value['invocationId'], 32)
            || !self::hex($value['requestFingerprint'], 64)
            || !self::hex($value['candidateDigest'], 64)
            || !self::opaque($value['conversationId']) || !self::opaque($value['messageId'])
            || $value['locale'] !== 'pt-BR') self::deny('invalid_request');
        // Duplicate names, alternate escapes and trailing data/whitespace do
        // not survive this byte comparison. Key order may follow the sender.
        if (!hash_equals(json_encode($value, self::JSON_FLAGS), $bytes)) self::deny('invalid_request');
        $context = $value['context'];
        if (!self::closedObject($context, ['scope', 'knowledgeVersion', 'knowledgeDigest', 'sourceIds', 'actionIds'])
            || $context['scope'] !== $this->policy['scope']
            || $context['knowledgeVersion'] !== $this->policy['knowledgeVersion']
            || $context['knowledgeDigest'] !== $this->policy['knowledgeDigest']) self::deny('context_denied');
        self::references($context['sourceIds'], $this->policy['sourceIds'], 4);
        self::references($context['actionIds'], $this->policy['actionIds'], 6);
        return $value;
    }

    private function assertSuggestion(): void
    {
        if ($this->suggestion !== [
            'kind' => 'SYNTHETIC_SUGGESTION', 'version' => 'mock-v1',
            'run' => $this->run->id, 'source' => self::RESOURCE,
            'text' => 'SYNTHETIC summary proposal only',
        ]) self::deny('invalid_suggestion');
    }

    private function assertKnowledge(array $knowledge): void
    {
        if (array_keys($knowledge) !== ['status', 'trust', 'passages']
            || $knowledge['status'] !== 'READY' || $knowledge['trust'] !== 'UNTRUSTED_DATA'
            || !is_array($knowledge['passages']) || !array_is_list($knowledge['passages'])
            || count($knowledge['passages']) !== 1
            || $knowledge['passages'][0] !== [
                'source_ref' => $this->source->ref, 'source_version' => $this->source->version,
                'section_ref' => $this->source->sectionRef, 'owner_ref' => $this->source->ownerRef,
                'digest' => $this->source->digest, 'trust' => 'UNTRUSTED_DATA', 'text' => $this->source->text,
            ]) self::deny('context_denied');
    }

    private static function closedObject(mixed $value, array $keys): bool
    {
        if (!is_array($value) || array_is_list($value) || count($value) !== count($keys)) return false;
        $actual = array_keys($value);
        sort($actual, SORT_STRING);
        sort($keys, SORT_STRING);
        return $actual === $keys;
    }

    private static function hex(mixed $value, int $length): bool
    {
        return is_string($value) && strlen($value) === $length && preg_match('/\A[a-f0-9]+\z/D', $value) === 1;
    }

    private static function opaque(mixed $value): bool
    {
        return is_string($value) && strlen($value) >= 8 && strlen($value) <= 96
            && preg_match('/\A[A-Za-z0-9_-]+\z/D', $value) === 1;
    }

    private static function references(mixed $values, array $allowed, int $limit): void
    {
        if (!is_array($values) || !array_is_list($values) || count($values) > $limit) self::deny('source_denied');
        foreach ($values as $value) {
            if (!is_string($value) || strlen($value) > 96 || !in_array($value, $allowed, true)) self::deny('source_denied');
        }
        if (count(array_unique($values, SORT_STRING)) !== count($values)) self::deny('source_denied');
    }

    private function failRun(): void
    {
        try {
            if ($this->run !== null && $this->run->state() === 'EXECUTANDO') {
                $this->coordinator->transition($this->run, 'FALHOU');
            }
        } catch (\Throwable) { /* Never disclose a secondary diagnostic failure. */ }
    }

    private function closeContext(): void
    {
        try { $this->pendingContext?->close(); } catch (\Throwable) { /* The envelope still closes fail-closed. */ }
    }

    private static function deny(string $code): never { throw new \DomainException($code); }
}
