<?php
declare(strict_types=1);

namespace ImagineAI\MockBridge;

/**
 * F232: deny-first, OFFLINE-only scope comparison.
 *
 * The externally supplied expected scope is synthetic test configuration,
 * NOT CRM IAM. Even a true result is never permission to read a CRM lead.
 * No database, network, storage, user credentials or side effects.
 */
final class MockSyntheticScopePreflight
{
    private const SCOPE_KEYS = [
        'tenant_ref', 'principal_ref', 'resource_ref', 'purpose_ref', 'fields',
    ];

    /**
     * @param array<string,mixed> $expected Trusted synthetic fixture, never client claims.
     * @return bool A matching fixture only; never an operational grant.
     */
    public static function matches(string $canonicalRequest, array $expected): bool
    {
        try {
            if (array_keys($expected) !== self::SCOPE_KEYS
                || !is_array($expected['fields'])
                || !array_is_list($expected['fields'])
                || count($expected['fields']) < 1
                || count($expected['fields']) > 2) {
                return false;
            }
            foreach (['tenant_ref', 'principal_ref', 'resource_ref', 'purpose_ref'] as $key) {
                if (!is_string($expected[$key])
                    || strlen($expected[$key]) > 90
                    || preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,80}\z/D', $expected[$key]) !== 1) {
                    return false;
                }
            }
            foreach ($expected['fields'] as $field) {
                if (!is_string($field) || !in_array($field, ['ref','stage'], true)) {
                    return false;
                }
            }
            if (count(array_unique($expected['fields'])) !== count($expected['fields'])) {
                return false;
            }
            $request = MockCrmReadRequest::decodeCanonicalJson($canonicalRequest);
            foreach (['tenant_ref', 'principal_ref', 'resource_ref', 'purpose_ref'] as $key) {
                if (!hash_equals($expected[$key], $request[$key])) {
                    return false;
                }
            }
            // A request may ask only for fields permitted by the fixture.
            // It cannot add columns, use an empty set or duplicate fields.
            foreach ($request['fields'] as $field) {
                if (!in_array($field, $expected['fields'], true)) {
                    return false;
                }
            }
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
