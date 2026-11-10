<?php
declare(strict_types=1);

namespace ImagineAI\MockBridge;

use ImagineAI\Domain\{MockError, TenantContext};

/**
 * Completely offline CRM-owner policy fixture, independent of the agent's
 * prior scope evidence. This is NOT real IAM, membership, ACL or an API.
 */
final class MockCrmOwnerPolicy
{
    /** @var array<string,list<string>> */
    private array $grants = [];
    /** @var array<string,bool> */
    private array $suspendedTenants = [];
    /** @var array<string,bool> */
    private array $suspendedMembers = [];
    private bool $available = true;
    // One monotonic epoch for this local owner fixture. A revoke/regrant,
    // suspend/resume or outage/recovery must invalidate prior read receipts.
    // Global scope deliberately fails closed across tenants.
    private int $revision = 0;

    public function revision(): int { return $this->revision; }

    public function assertRevision(int $expected): void
    {
        if ($this->revision !== $expected) MockError::fail('RESOURCE_DENIED_OR_MISSING');
    }

    private function touch(): void
    {
        if ($this->revision === PHP_INT_MAX) MockError::fail('OWNER_POLICY_UNAVAILABLE');
        ++$this->revision;
    }

    /** Configure an explicit permit for exactly one fictional resource/context. */
    public function grantFixture(TenantContext $context, array $fields): void
    {
        if (!self::validFixtureContext($context) || !self::validFields($fields)) {
            MockError::fail('OWNER_POLICY_SCHEMA_DENIED');
        }
        $this->touch();
        $this->grants[$context->key()] = $fields;
    }

    public function revokeFixture(TenantContext $context): void
    {
        if (!self::validFixtureContext($context)) MockError::fail('OWNER_POLICY_SCHEMA_DENIED');
        $this->touch();
        unset($this->grants[$context->key()]);
    }

    public function setTenantSuspended(string $tenant, bool $suspended): void
    {
        if (!in_array($tenant, ['SYNTHETIC_A', 'SYNTHETIC_B'], true)) {
            MockError::fail('OWNER_POLICY_SCHEMA_DENIED');
        }
        $this->touch();
        if ($suspended) $this->suspendedTenants[$tenant] = true;
        else unset($this->suspendedTenants[$tenant]);
    }

    public function setMemberSuspended(string $tenant, string $principal, bool $suspended): void
    {
        if (!in_array($tenant, ['SYNTHETIC_A', 'SYNTHETIC_B'], true)
            || $principal !== 'SYNTHETIC_PRINCIPAL') {
            MockError::fail('OWNER_POLICY_SCHEMA_DENIED');
        }
        $key = $tenant . ':' . $principal;
        $this->touch();
        if ($suspended) $this->suspendedMembers[$key] = true;
        else unset($this->suspendedMembers[$key]);
    }

    public function setAvailable(bool $available): void
    {
        $this->touch();
        $this->available = $available;
    }

    /**
     * Re-check a provider-owned fictional policy at each read.
     * Missing, cross-tenant, revoked and field-denied use the same error.
     */
    public function assertAllowed(TenantContext $context, array $fields): void
    {
        if (!$this->available) MockError::fail('OWNER_POLICY_UNAVAILABLE');

        $key = $context->key();
        if (!self::validFixtureContext($context)
            || !self::validFields($fields)
            || isset($this->suspendedTenants[$context->tenant])
            || isset($this->suspendedMembers[$context->tenant . ':' . $context->principal])
            || !array_key_exists($key, $this->grants)) {
            MockError::fail('RESOURCE_DENIED_OR_MISSING');
        }
        foreach ($fields as $field) {
            if (!in_array($field, $this->grants[$key], true)) {
                MockError::fail('RESOURCE_DENIED_OR_MISSING');
            }
        }
    }

    private static function validFixtureContext(TenantContext $context): bool
    {
        return $context->product === 'SYNTHETIC_CRM'
            && in_array($context->tenant, ['SYNTHETIC_A', 'SYNTHETIC_B'], true)
            && $context->principal === 'SYNTHETIC_PRINCIPAL'
            && $context->purpose === 'SYNTHETIC_SUMMARY'
            && $context->resource === $context->tenant . '_LEAD';
    }

    private static function validFields(array $fields): bool
    {
        if (!array_is_list($fields) || count($fields) < 1 || count($fields) > 2
            || count(array_unique($fields)) !== count($fields)) return false;
        foreach ($fields as $field) {
            if (!is_string($field) || !in_array($field, ['ref', 'stage'], true)) return false;
        }
        return true;
    }
}
