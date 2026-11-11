<?php
declare(strict_types=1);

namespace ImagineAI\MockBridge;

use ImagineAI\Domain\TenantContext;

/**
 * One closed, synthetic-only response contract for the owner facade, OP-01
 * edge, READ tool and read-only pilot. Never an HTTP decoder, CRM authorization
 * or permission token. See CRM PR #57 (OPEN/DRAFT, not ratified).
 */
final class MockCrmPublicResponseContract
{
    private const KEYS = [
        'contract', 'state', 'projection', 'real_authorization', 'external_calls',
    ];
    private const CONTRACT = 'SYNTHETIC_CRM_AIA_PUBLIC_V1';

    /** A denied response cannot reveal tenant, resource or policy details. */
    public static function denial(): array
    {
        return [
            'contract' => self::CONTRACT,
            'state' => 'DENIED_OR_NOT_FOUND',
            'projection' => null,
            'real_authorization' => false,
            'external_calls' => 0,
        ];
    }

    /**
     * Closed validator for an in-process, PHP-array CRM-owner-like response.
     * Distinguishes a safe uniform denial from a successful fictional read.
     * A safe denial is NEVER permission for the caller to use its projection.
     */
    public static function isSafe(array $body, MockCrmReadRequest $request,
        TenantContext $context): bool
    {
        if (count($body) !== 5
            || array_keys($body) !== self::KEYS
            || $body['contract'] !== self::CONTRACT
            || $body['real_authorization'] !== false
            || $body['external_calls'] !== 0) {
            return false;
        }
        try {
            $request->assertMatches($context);
        } catch (\Throwable) {
            return false;
        }
        if ($body['state'] === 'DENIED_OR_NOT_FOUND') {
            return $body['projection'] === null;
        }
        if ($body['state'] !== 'READ_SYNTHETIC_ONLY'
            || !is_array($body['projection'])) {
            return false;
        }
        return MockReadProjectionInvariant::isCanonical(
            $body['projection'], $request->fields, $context);
    }

    /** Exactly the owner's fictional minimal read, never just a safe denial. */
    public static function isAcceptedRead(array $body, MockCrmReadRequest $request,
        TenantContext $context): bool
    {
        return ($body['state'] ?? null) === 'READ_SYNTHETIC_ONLY'
            && self::isSafe($body, $request, $context);
    }
}
