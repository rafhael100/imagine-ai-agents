<?php
declare(strict_types=1);

namespace ImagineAI\MockBridge;

use ImagineAI\Domain\TenantContext;

/**
 * Consumer-side invariant for the fictional minimal CRM read projection.
 * No API, CRM permission, identity check, token, or real customer record.
 */
final class MockReadProjectionInvariant
{
    /** @param array<string,mixed> $projection @param list<string> $fields
     *  @return ?array<string,string> Null means an unsafe or inconsistent projection.
     */
    public static function canonicalize(
        array $projection,
        array $fields,
        TenantContext $context,
    ): ?array {
        // Count before examining keys or values: never traverse an oversized
        // response produced by an inconsistent future CrmReader adapter.
        if (count($fields) < 1 || count($fields) > 2 || !array_is_list($fields)
            || count($projection) !== count($fields)) {
            return null;
        }

        $ordered = [];
        foreach ($fields as $field) {
            if (!in_array($field, ['ref', 'stage'], true)
                || array_key_exists($field, $ordered)
                || !array_key_exists($field, $projection)) {
                return null;
            }
            $value = $projection[$field];
            // Limit an arbitrary adapter value before a regex can inspect it.
            if (!is_string($value) || strlen($value) > 90
                || preg_match('/^SYNTHETIC_[A-Z0-9_]{1,80}$/D', $value) !== 1
                || ($field === 'ref' && $value !== $context->resource)
                || ($field === 'stage' && $value !== 'SYNTHETIC_NEW')) {
                return null;
            }
            $ordered[$field] = $value;
        }
        return $ordered;
    }

    /** Public-facing projections must already be in exact request order. */
    public static function isCanonical(
        array $projection,
        array $fields,
        TenantContext $context,
    ): bool {
        $ordered = self::canonicalize($projection, $fields, $context);
        return $ordered !== null && $projection === $ordered;
    }
}
