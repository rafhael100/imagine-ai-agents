<?php
declare(strict_types=1);

namespace ImagineAI\Convergence;

require_once __DIR__ . '/MockPolicyBoundIngress.php';
require_once __DIR__ . '/../../_auto_ia_pinned_source/tools/auto_ia/convergence/MockP7ToAutoIaAdapter.php';

use ImagineAI\AutomationFirst\MockP7ToAutoIaAdapter;
use PDO;
use Throwable;

/**
 * P16: ephemeral PostgreSQL 16 atomic replay ledger over P15 policy verification.
 * Database accepts no PII and never authorizes S2S transport, n8n, billing or AI.
 */
final class PgSyntheticReplayGate
{
    public function __construct(private PDO $db)
    {
        // Never instantiate the SQL-writing lab against an unexpected database,
        // even when the environment variables were accidentally copied.
        if (!self::labOnly()) {
            throw new \RuntimeException('P16_NON_CI_ENVIRONMENT_DENIED');
        }
        try {
            if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
                throw new \RuntimeException('P16_WRONG_DATABASE_DRIVER');
            }
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $identity = $db->query(
                'SELECT current_database() AS db, current_user AS username'
            )->fetch(PDO::FETCH_ASSOC);
            if (!is_array($identity)
                || $identity['db'] !== 'imagine_agents_ci'
                || $identity['username'] !== 'postgres') {
                throw new \RuntimeException('P16_WRONG_DATABASE_IDENTITY');
            }
        } catch (Throwable $e) {
            throw new \RuntimeException('P16_DATABASE_IDENTITY_DENIED', 0, $e);
        }
    }

    private static function labOnly(): bool
    {
        return getenv('GITHUB_ACTIONS') === 'true'
            && getenv('GITHUB_REPOSITORY') === 'rafhael100/imagine-ai-agents'
            && getenv('AIA_P16_PG_LAB') === 'true'
            && getenv('DB_HOST') === '127.0.0.1'
            && getenv('DB_DATABASE') === 'imagine_agents_ci';
    }

    public function createSchema(): void
    {
        if (!self::labOnly()) throw new \RuntimeException('P16_CI_ONLY');
        $this->db->exec('CREATE SCHEMA IF NOT EXISTS aia_p16_lab');
        $this->db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS aia_p16_lab.event_replay (
 tenant_ref VARCHAR(64) NOT NULL,
 event_ref VARCHAR(64) NOT NULL,
 request_ref VARCHAR(64) NOT NULL,
 nonce_ref VARCHAR(64) NOT NULL,
 payload_hash CHAR(64) NOT NULL,
 observed_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
 PRIMARY KEY (tenant_ref, event_ref),
 UNIQUE (tenant_ref, request_ref),
 UNIQUE (tenant_ref, nonce_ref),
 CONSTRAINT p27_replay_tenant_ref CHECK (tenant_ref ~ '^SYNTHETIC_[A-Z0-9_]{1,48}
    }

    /**
     * Validates against original pinned adapter, then claims unique tenant-scoped event,
     * nonce and request IDs atomically. A successful claim is NOT event delivery.
     * @param array<string,mixed> $envelope
     * @param array<string,mixed> $trustedPolicy
     * @param array<string,string> $trustedKeys
     * @param array<string,string> $seen Process-local P15 replay memory only
     * @return array{decision:string,reason:string,ai_calls:int,service_effects:int}
     */
    public function inspectAndClaim(
        string $p7,
        array $envelope,
        array $trustedPolicy,
        int $now,
        array $trustedKeys,
        array &$seen
    ): array {
        if (!self::labOnly()) return self::deny('CI_ONLY');
        $validation = MockPolicyBoundIngress::inspect(
            $p7, $envelope, $trustedPolicy, $now, $trustedKeys, $seen
        );
        if ($validation['decision'] !== 'LAB_POLICY_MATCH_NO_EFFECT') {
            return self::deny('POLICY_BOUND_INPUT_DENIED');
        }
        $result = MockP7ToAutoIaAdapter::propose(
            $p7, $trustedPolicy['tenant_ref'], $now
        );
        if ($result['decision'] !== 'LAB_NO_AI_PROPOSAL_NOT_SIGNED') {
            return self::deny('CANONICAL_PROPOSAL_DENIED');
        }
        $p = $result['proposal'];
        foreach (['tenant_ref','event_ref','request_ref','nonce_ref','payload_hash'] as $field) {
            if (!isset($p[$field]) || !is_string($p[$field])) {
                return self::deny('INVALID_PERSISTENCE_BINDING');
            }
        }
        try {
            $stmt = $this->db->prepare(<<<'SQL'
INSERT INTO aia_p16_lab.event_replay
 (tenant_ref, event_ref, request_ref, nonce_ref, payload_hash)
VALUES (:tenant, :event, :request, :nonce, :digest)
ON CONFLICT DO NOTHING
RETURNING event_ref
SQL);
            $stmt->execute([
                ':tenant' => $p['tenant_ref'],
                ':event' => $p['event_ref'],
                ':request' => $p['request_ref'],
                ':nonce' => $p['nonce_ref'],
                ':digest' => $p['payload_hash'],
            ]);
            if ($stmt->fetchColumn() === false) {
                return self::deny('DURABLE_REPLAY_OR_IDEMPOTENCY_CONFLICT');
            }
            return [
                'decision' => 'LAB_ATOMIC_CLAIM_NO_EFFECT',
                'reason' => 'NO_REAL_IAM_NO_TRANSPORT_NO_DELIVERY',
                'ai_calls' => 0,
                'service_effects' => 0,
            ];
        } catch (Throwable) {
            // Fail closed on timeout, SQL error, lost DB or schema mismatch.
            return self::deny('POSTGRESQL_UNAVAILABLE_OR_ERROR');
        }
    }

    /** @return array{decision:string,reason:string,ai_calls:int,service_effects:int} */
    private static function deny(string $reason): array
    {
        return [
            'decision' => 'DENY',
            'reason' => $reason,
            'ai_calls' => 0,
            'service_effects' => 0,
        ];
    }
}
),
 CONSTRAINT p27_replay_event_ref CHECK (event_ref ~ '^SYNTHETIC_[A-Z0-9_]{1,48}
    }

    /**
     * Validates against original pinned adapter, then claims unique tenant-scoped event,
     * nonce and request IDs atomically. A successful claim is NOT event delivery.
     * @param array<string,mixed> $envelope
     * @param array<string,mixed> $trustedPolicy
     * @param array<string,string> $trustedKeys
     * @param array<string,string> $seen Process-local P15 replay memory only
     * @return array{decision:string,reason:string,ai_calls:int,service_effects:int}
     */
    public function inspectAndClaim(
        string $p7,
        array $envelope,
        array $trustedPolicy,
        int $now,
        array $trustedKeys,
        array &$seen
    ): array {
        if (!self::labOnly()) return self::deny('CI_ONLY');
        $validation = MockPolicyBoundIngress::inspect(
            $p7, $envelope, $trustedPolicy, $now, $trustedKeys, $seen
        );
        if ($validation['decision'] !== 'LAB_POLICY_MATCH_NO_EFFECT') {
            return self::deny('POLICY_BOUND_INPUT_DENIED');
        }
        $result = MockP7ToAutoIaAdapter::propose(
            $p7, $trustedPolicy['tenant_ref'], $now
        );
        if ($result['decision'] !== 'LAB_NO_AI_PROPOSAL_NOT_SIGNED') {
            return self::deny('CANONICAL_PROPOSAL_DENIED');
        }
        $p = $result['proposal'];
        foreach (['tenant_ref','event_ref','request_ref','nonce_ref','payload_hash'] as $field) {
            if (!isset($p[$field]) || !is_string($p[$field])) {
                return self::deny('INVALID_PERSISTENCE_BINDING');
            }
        }
        try {
            $stmt = $this->db->prepare(<<<'SQL'
INSERT INTO aia_p16_lab.event_replay
 (tenant_ref, event_ref, request_ref, nonce_ref, payload_hash)
VALUES (:tenant, :event, :request, :nonce, :digest)
ON CONFLICT DO NOTHING
RETURNING event_ref
SQL);
            $stmt->execute([
                ':tenant' => $p['tenant_ref'],
                ':event' => $p['event_ref'],
                ':request' => $p['request_ref'],
                ':nonce' => $p['nonce_ref'],
                ':digest' => $p['payload_hash'],
            ]);
            if ($stmt->fetchColumn() === false) {
                return self::deny('DURABLE_REPLAY_OR_IDEMPOTENCY_CONFLICT');
            }
            return [
                'decision' => 'LAB_ATOMIC_CLAIM_NO_EFFECT',
                'reason' => 'NO_REAL_IAM_NO_TRANSPORT_NO_DELIVERY',
                'ai_calls' => 0,
                'service_effects' => 0,
            ];
        } catch (Throwable) {
            // Fail closed on timeout, SQL error, lost DB or schema mismatch.
            return self::deny('POSTGRESQL_UNAVAILABLE_OR_ERROR');
        }
    }

    /** @return array{decision:string,reason:string,ai_calls:int,service_effects:int} */
    private static function deny(string $reason): array
    {
        return [
            'decision' => 'DENY',
            'reason' => $reason,
            'ai_calls' => 0,
            'service_effects' => 0,
        ];
    }
}
),
 CONSTRAINT p27_replay_request_ref CHECK (request_ref ~ '^SYNTHETIC_[A-Z0-9_]{1,48}
    }

    /**
     * Validates against original pinned adapter, then claims unique tenant-scoped event,
     * nonce and request IDs atomically. A successful claim is NOT event delivery.
     * @param array<string,mixed> $envelope
     * @param array<string,mixed> $trustedPolicy
     * @param array<string,string> $trustedKeys
     * @param array<string,string> $seen Process-local P15 replay memory only
     * @return array{decision:string,reason:string,ai_calls:int,service_effects:int}
     */
    public function inspectAndClaim(
        string $p7,
        array $envelope,
        array $trustedPolicy,
        int $now,
        array $trustedKeys,
        array &$seen
    ): array {
        if (!self::labOnly()) return self::deny('CI_ONLY');
        $validation = MockPolicyBoundIngress::inspect(
            $p7, $envelope, $trustedPolicy, $now, $trustedKeys, $seen
        );
        if ($validation['decision'] !== 'LAB_POLICY_MATCH_NO_EFFECT') {
            return self::deny('POLICY_BOUND_INPUT_DENIED');
        }
        $result = MockP7ToAutoIaAdapter::propose(
            $p7, $trustedPolicy['tenant_ref'], $now
        );
        if ($result['decision'] !== 'LAB_NO_AI_PROPOSAL_NOT_SIGNED') {
            return self::deny('CANONICAL_PROPOSAL_DENIED');
        }
        $p = $result['proposal'];
        foreach (['tenant_ref','event_ref','request_ref','nonce_ref','payload_hash'] as $field) {
            if (!isset($p[$field]) || !is_string($p[$field])) {
                return self::deny('INVALID_PERSISTENCE_BINDING');
            }
        }
        try {
            $stmt = $this->db->prepare(<<<'SQL'
INSERT INTO aia_p16_lab.event_replay
 (tenant_ref, event_ref, request_ref, nonce_ref, payload_hash)
VALUES (:tenant, :event, :request, :nonce, :digest)
ON CONFLICT DO NOTHING
RETURNING event_ref
SQL);
            $stmt->execute([
                ':tenant' => $p['tenant_ref'],
                ':event' => $p['event_ref'],
                ':request' => $p['request_ref'],
                ':nonce' => $p['nonce_ref'],
                ':digest' => $p['payload_hash'],
            ]);
            if ($stmt->fetchColumn() === false) {
                return self::deny('DURABLE_REPLAY_OR_IDEMPOTENCY_CONFLICT');
            }
            return [
                'decision' => 'LAB_ATOMIC_CLAIM_NO_EFFECT',
                'reason' => 'NO_REAL_IAM_NO_TRANSPORT_NO_DELIVERY',
                'ai_calls' => 0,
                'service_effects' => 0,
            ];
        } catch (Throwable) {
            // Fail closed on timeout, SQL error, lost DB or schema mismatch.
            return self::deny('POSTGRESQL_UNAVAILABLE_OR_ERROR');
        }
    }

    /** @return array{decision:string,reason:string,ai_calls:int,service_effects:int} */
    private static function deny(string $reason): array
    {
        return [
            'decision' => 'DENY',
            'reason' => $reason,
            'ai_calls' => 0,
            'service_effects' => 0,
        ];
    }
}
),
 CONSTRAINT p27_replay_nonce_ref CHECK (nonce_ref ~ '^SYNTHETIC_[A-Z0-9_]{1,48}
    }

    /**
     * Validates against original pinned adapter, then claims unique tenant-scoped event,
     * nonce and request IDs atomically. A successful claim is NOT event delivery.
     * @param array<string,mixed> $envelope
     * @param array<string,mixed> $trustedPolicy
     * @param array<string,string> $trustedKeys
     * @param array<string,string> $seen Process-local P15 replay memory only
     * @return array{decision:string,reason:string,ai_calls:int,service_effects:int}
     */
    public function inspectAndClaim(
        string $p7,
        array $envelope,
        array $trustedPolicy,
        int $now,
        array $trustedKeys,
        array &$seen
    ): array {
        if (!self::labOnly()) return self::deny('CI_ONLY');
        $validation = MockPolicyBoundIngress::inspect(
            $p7, $envelope, $trustedPolicy, $now, $trustedKeys, $seen
        );
        if ($validation['decision'] !== 'LAB_POLICY_MATCH_NO_EFFECT') {
            return self::deny('POLICY_BOUND_INPUT_DENIED');
        }
        $result = MockP7ToAutoIaAdapter::propose(
            $p7, $trustedPolicy['tenant_ref'], $now
        );
        if ($result['decision'] !== 'LAB_NO_AI_PROPOSAL_NOT_SIGNED') {
            return self::deny('CANONICAL_PROPOSAL_DENIED');
        }
        $p = $result['proposal'];
        foreach (['tenant_ref','event_ref','request_ref','nonce_ref','payload_hash'] as $field) {
            if (!isset($p[$field]) || !is_string($p[$field])) {
                return self::deny('INVALID_PERSISTENCE_BINDING');
            }
        }
        try {
            $stmt = $this->db->prepare(<<<'SQL'
INSERT INTO aia_p16_lab.event_replay
 (tenant_ref, event_ref, request_ref, nonce_ref, payload_hash)
VALUES (:tenant, :event, :request, :nonce, :digest)
ON CONFLICT DO NOTHING
RETURNING event_ref
SQL);
            $stmt->execute([
                ':tenant' => $p['tenant_ref'],
                ':event' => $p['event_ref'],
                ':request' => $p['request_ref'],
                ':nonce' => $p['nonce_ref'],
                ':digest' => $p['payload_hash'],
            ]);
            if ($stmt->fetchColumn() === false) {
                return self::deny('DURABLE_REPLAY_OR_IDEMPOTENCY_CONFLICT');
            }
            return [
                'decision' => 'LAB_ATOMIC_CLAIM_NO_EFFECT',
                'reason' => 'NO_REAL_IAM_NO_TRANSPORT_NO_DELIVERY',
                'ai_calls' => 0,
                'service_effects' => 0,
            ];
        } catch (Throwable) {
            // Fail closed on timeout, SQL error, lost DB or schema mismatch.
            return self::deny('POSTGRESQL_UNAVAILABLE_OR_ERROR');
        }
    }

    /** @return array{decision:string,reason:string,ai_calls:int,service_effects:int} */
    private static function deny(string $reason): array
    {
        return [
            'decision' => 'DENY',
            'reason' => $reason,
            'ai_calls' => 0,
            'service_effects' => 0,
        ];
    }
}
),
 CONSTRAINT p27_replay_payload_hash CHECK (payload_hash ~ '^[a-f0-9]{64}
    }

    /**
     * Validates against original pinned adapter, then claims unique tenant-scoped event,
     * nonce and request IDs atomically. A successful claim is NOT event delivery.
     * @param array<string,mixed> $envelope
     * @param array<string,mixed> $trustedPolicy
     * @param array<string,string> $trustedKeys
     * @param array<string,string> $seen Process-local P15 replay memory only
     * @return array{decision:string,reason:string,ai_calls:int,service_effects:int}
     */
    public function inspectAndClaim(
        string $p7,
        array $envelope,
        array $trustedPolicy,
        int $now,
        array $trustedKeys,
        array &$seen
    ): array {
        if (!self::labOnly()) return self::deny('CI_ONLY');
        $validation = MockPolicyBoundIngress::inspect(
            $p7, $envelope, $trustedPolicy, $now, $trustedKeys, $seen
        );
        if ($validation['decision'] !== 'LAB_POLICY_MATCH_NO_EFFECT') {
            return self::deny('POLICY_BOUND_INPUT_DENIED');
        }
        $result = MockP7ToAutoIaAdapter::propose(
            $p7, $trustedPolicy['tenant_ref'], $now
        );
        if ($result['decision'] !== 'LAB_NO_AI_PROPOSAL_NOT_SIGNED') {
            return self::deny('CANONICAL_PROPOSAL_DENIED');
        }
        $p = $result['proposal'];
        foreach (['tenant_ref','event_ref','request_ref','nonce_ref','payload_hash'] as $field) {
            if (!isset($p[$field]) || !is_string($p[$field])) {
                return self::deny('INVALID_PERSISTENCE_BINDING');
            }
        }
        try {
            $stmt = $this->db->prepare(<<<'SQL'
INSERT INTO aia_p16_lab.event_replay
 (tenant_ref, event_ref, request_ref, nonce_ref, payload_hash)
VALUES (:tenant, :event, :request, :nonce, :digest)
ON CONFLICT DO NOTHING
RETURNING event_ref
SQL);
            $stmt->execute([
                ':tenant' => $p['tenant_ref'],
                ':event' => $p['event_ref'],
                ':request' => $p['request_ref'],
                ':nonce' => $p['nonce_ref'],
                ':digest' => $p['payload_hash'],
            ]);
            if ($stmt->fetchColumn() === false) {
                return self::deny('DURABLE_REPLAY_OR_IDEMPOTENCY_CONFLICT');
            }
            return [
                'decision' => 'LAB_ATOMIC_CLAIM_NO_EFFECT',
                'reason' => 'NO_REAL_IAM_NO_TRANSPORT_NO_DELIVERY',
                'ai_calls' => 0,
                'service_effects' => 0,
            ];
        } catch (Throwable) {
            // Fail closed on timeout, SQL error, lost DB or schema mismatch.
            return self::deny('POSTGRESQL_UNAVAILABLE_OR_ERROR');
        }
    }

    /** @return array{decision:string,reason:string,ai_calls:int,service_effects:int} */
    private static function deny(string $reason): array
    {
        return [
            'decision' => 'DENY',
            'reason' => $reason,
            'ai_calls' => 0,
            'service_effects' => 0,
        ];
    }
}
)
)
SQL);
    }

    /**
     * Validates against original pinned adapter, then claims unique tenant-scoped event,
     * nonce and request IDs atomically. A successful claim is NOT event delivery.
     * @param array<string,mixed> $envelope
     * @param array<string,mixed> $trustedPolicy
     * @param array<string,string> $trustedKeys
     * @param array<string,string> $seen Process-local P15 replay memory only
     * @return array{decision:string,reason:string,ai_calls:int,service_effects:int}
     */
    public function inspectAndClaim(
        string $p7,
        array $envelope,
        array $trustedPolicy,
        int $now,
        array $trustedKeys,
        array &$seen
    ): array {
        if (!self::labOnly()) return self::deny('CI_ONLY');
        $validation = MockPolicyBoundIngress::inspect(
            $p7, $envelope, $trustedPolicy, $now, $trustedKeys, $seen
        );
        if ($validation['decision'] !== 'LAB_POLICY_MATCH_NO_EFFECT') {
            return self::deny('POLICY_BOUND_INPUT_DENIED');
        }
        $result = MockP7ToAutoIaAdapter::propose(
            $p7, $trustedPolicy['tenant_ref'], $now
        );
        if ($result['decision'] !== 'LAB_NO_AI_PROPOSAL_NOT_SIGNED') {
            return self::deny('CANONICAL_PROPOSAL_DENIED');
        }
        $p = $result['proposal'];
        foreach (['tenant_ref','event_ref','request_ref','nonce_ref','payload_hash'] as $field) {
            if (!isset($p[$field]) || !is_string($p[$field])) {
                return self::deny('INVALID_PERSISTENCE_BINDING');
            }
        }
        try {
            $stmt = $this->db->prepare(<<<'SQL'
INSERT INTO aia_p16_lab.event_replay
 (tenant_ref, event_ref, request_ref, nonce_ref, payload_hash)
VALUES (:tenant, :event, :request, :nonce, :digest)
ON CONFLICT DO NOTHING
RETURNING event_ref
SQL);
            $stmt->execute([
                ':tenant' => $p['tenant_ref'],
                ':event' => $p['event_ref'],
                ':request' => $p['request_ref'],
                ':nonce' => $p['nonce_ref'],
                ':digest' => $p['payload_hash'],
            ]);
            if ($stmt->fetchColumn() === false) {
                return self::deny('DURABLE_REPLAY_OR_IDEMPOTENCY_CONFLICT');
            }
            return [
                'decision' => 'LAB_ATOMIC_CLAIM_NO_EFFECT',
                'reason' => 'NO_REAL_IAM_NO_TRANSPORT_NO_DELIVERY',
                'ai_calls' => 0,
                'service_effects' => 0,
            ];
        } catch (Throwable) {
            // Fail closed on timeout, SQL error, lost DB or schema mismatch.
            return self::deny('POSTGRESQL_UNAVAILABLE_OR_ERROR');
        }
    }

    /** @return array{decision:string,reason:string,ai_calls:int,service_effects:int} */
    private static function deny(string $reason): array
    {
        return [
            'decision' => 'DENY',
            'reason' => $reason,
            'ai_calls' => 0,
            'service_effects' => 0,
        ];
    }
}
