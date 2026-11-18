<?php
declare(strict_types=1);

namespace ImagineAI\Integration\Faisca;

use ImagineAI\Contracts\AiSuggestionProvider;

/** A public editorial binding composed with the unchanged R05 synthetic execution. */
final class GroundedConversationBridge
{
    public const MAX_REQUEST_BYTES = 16384;
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
    private readonly PublicPassageCatalog $catalog;
    private readonly PublicConversationBridge $execution;
    private readonly array $policy;
    private string $phase = 'idle';
    private ?array $request = null;
    private ?array $selected = null;
    private ?array $candidate = null;
    private ?int $preparedAt = null;

    /** Both JSON documents and the optional test provider are trusted host dependencies. */
    public function __construct(string $policyJson, string $catalogJson, ?AiSuggestionProvider $provider = null)
    {
        $this->execution = new PublicConversationBridge($policyJson, $provider);
        $this->policy = json_decode($policyJson, true, 5, JSON_THROW_ON_ERROR);
        $this->catalog = new PublicPassageCatalog($catalogJson);
    }

    public function prepare(string $bytes, int $now): void
    {
        if ($this->phase !== 'idle') self::deny('unavailable');
        $this->phase = 'preparing';
        try {
            $this->request = $this->decode($bytes);
            $this->preparedAt = $now;
            $this->catalog->select($this->request['grounding']['answerId'], $this->request['grounding']['passageIds'], $now);
            $this->selected = $this->catalog->deliver($now);
            $this->candidate = $this->candidate($this->selected);
            // Reconstruct the full answer independently. Merely echoing a hash
            // would admit an unrelated answer with perfectly valid citations.
            $this->assertCandidate($this->candidate);
            $request = [
                'schema' => 'faisca.agents.request.v1',
                'invocationId' => $this->request['invocationId'],
                'requestFingerprint' => $this->request['requestFingerprint'],
                'candidateDigest' => $this->request['candidateDigest'],
                'conversationId' => $this->request['conversationId'],
                'messageId' => $this->request['messageId'], 'locale' => 'pt-BR',
                'context' => [
                    'scope' => $this->policy['scope'], 'knowledgeVersion' => $this->policy['knowledgeVersion'],
                    'knowledgeDigest' => $this->policy['knowledgeDigest'],
                    'sourceIds' => $this->catalog->legacySourceIds(), 'actionIds' => $this->catalog->actionIds(),
                ],
            ];
            $this->execution->prepare(json_encode($request, self::JSON_FLAGS), $now);
            $this->phase = 'prepared';
        } catch (\Throwable $error) {
            $this->fail();
            self::rethrow($error);
        }
    }

    public function deliver(int $now): array
    {
        if ($this->phase !== 'prepared') self::deny('unavailable');
        try {
            $grounding = $this->catalog->deliver($now);
            if ($grounding !== $this->selected) self::deny('grounding_denied');
            $candidate = $this->candidate($grounding);
            if ($candidate !== $this->candidate) self::deny('grounding_denied');
            $this->assertCandidate($candidate);
            $execution = $this->execution->deliver($now);
            $binding = [
                'catalogVersion' => PublicPassageCatalog::VERSION,
                'catalogDigest' => PublicPassageCatalog::SHA256,
                'answerId' => $grounding['answerId'],
                'candidateDigest' => $this->request['candidateDigest'],
                'passageIds' => $grounding['passageIds'],
                'passageDigests' => array_column($grounding['passages'], 'digest'),
            ];
            $grounding['bindingDigest'] = hash('sha256', json_encode($binding, self::JSON_FLAGS));
            $this->phase = 'delivered';
            $this->catalog->close();
            return ['schema' => 'faisca.grounded.result.v1', 'execution' => $execution, 'grounding' => $grounding];
        } catch (\Throwable $error) {
            $this->fail();
            self::rethrow($error);
        }
    }

    /** Trusted denial-only controls, never fields in the public request. */
    public function revokePendingContext(): void
    {
        if (!in_array($this->phase, ['preparing', 'prepared'], true)) self::deny('unavailable');
        $this->catalog->revoke();
    }

    public function disablePendingSource(): void
    {
        if (!in_array($this->phase, ['preparing', 'prepared'], true) || $this->selected === null) self::deny('unavailable');
        $this->catalog->disableSource($this->selected['passages'][0]['sourcePath']);
    }

    public function inspect(): array
    {
        return ['phase' => $this->phase, 'grounding' => $this->catalog->inspect(), 'execution' => $this->execution->inspect()];
    }

    private function candidate(array $grounding): array
    {
        return [
            'schema' => 'faisca.response.v1', 'conversationId' => $this->request['conversationId'],
            'messageId' => $this->request['messageId'], 'locale' => 'pt-BR', 'knowledgeVersion' => 'faisca-editorial-2',
            'text' => $grounding['text'],
            'sources' => array_map(static fn (string $id): array => [
                'id' => 'faisca.passage.' . $id . '@r06-1', 'version' => PublicPassageCatalog::VERSION,
            ], $grounding['passageIds']),
            'actions' => array_map(static fn (string $id): array => ['id' => $id], $this->catalog->actionIds()),
        ];
    }

    private function assertCandidate(array $candidate): void
    {
        if (!hash_equals($this->request['candidateDigest'], hash('sha256', json_encode($candidate, self::JSON_FLAGS)))) {
            self::deny('grounding_denied');
        }
    }

    private function decode(string $bytes): array
    {
        if ($bytes === '' || strlen($bytes) > self::MAX_REQUEST_BYTES) self::deny('invalid_request');
        try { $request = json_decode($bytes, true, 7, JSON_THROW_ON_ERROR); }
        catch (\Throwable) { self::deny('invalid_request'); }
        if (!self::closed($request, ['schema', 'invocationId', 'requestFingerprint', 'candidateDigest',
            'conversationId', 'messageId', 'locale', 'grounding'])
            || $request['schema'] !== 'faisca.grounded.request.v1'
            || !self::hex($request['invocationId'], 32) || !self::hex($request['requestFingerprint'], 64)
            || !self::hex($request['candidateDigest'], 64) || !self::opaque($request['conversationId'])
            || !self::opaque($request['messageId']) || $request['locale'] !== 'pt-BR'
            || !hash_equals(json_encode($request, self::JSON_FLAGS), $bytes)) self::deny('invalid_request');
        $grounding = $request['grounding'];
        if (!self::closed($grounding, ['catalogVersion', 'catalogDigest', 'answerId', 'passageIds'])
            || $grounding['catalogVersion'] !== PublicPassageCatalog::VERSION
            || $grounding['catalogDigest'] !== PublicPassageCatalog::SHA256
            || !is_string($grounding['answerId']) || strlen($grounding['answerId']) > 96
            || preg_match('/\A[a-z0-9][a-z0-9_.-]*\z/D', $grounding['answerId']) !== 1
            || !is_array($grounding['passageIds']) || !array_is_list($grounding['passageIds'])
            || count($grounding['passageIds']) < 1 || count($grounding['passageIds']) > 3) self::deny('grounding_denied');
        foreach ($grounding['passageIds'] as $id) {
            if (!is_string($id) || strlen($id) > 96 || preg_match('/\A[a-z0-9][a-z0-9_.-]*\z/D', $id) !== 1) {
                self::deny('grounding_denied');
            }
        }
        if (count(array_unique($grounding['passageIds'], SORT_STRING)) !== count($grounding['passageIds'])) self::deny('grounding_denied');
        return $request;
    }

    private function fail(): void
    {
        // Revoke the old mock grant to terminalize an already-prepared Run;
        // never claim that cancelling delivery undid the earlier suggestion.
        try {
            $this->execution->revokePendingContext();
            $this->execution->deliver($this->preparedAt ?? 0);
        } catch (\Throwable) { /* Keep only the original closed failure code. */ }
        $this->catalog->close();
        $this->phase = 'failed';
    }

    private static function closed(mixed $value, array $keys): bool
    {
        if (!is_array($value) || array_is_list($value) || count($value) !== count($keys)) return false;
        $actual = array_keys($value); sort($actual, SORT_STRING); sort($keys, SORT_STRING);
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

    private static function rethrow(\Throwable $error): never
    {
        if ($error instanceof \DomainException && in_array($error->getMessage(), [
            'invalid_request', 'context_denied', 'source_denied', 'invalid_suggestion', 'grounding_denied', 'unavailable',
        ], true)) throw $error;
        self::deny('unavailable');
    }

    private static function deny(string $code): never { throw new \DomainException($code); }
}
