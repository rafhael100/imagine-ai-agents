<?php
declare(strict_types=1);

namespace ImagineAI\MockBridge;

/**
 * F239: validate a uniform synthetic CRM 404 DENIAL receipt. Pure bytes only.
 *
 * A matching response is evidence of a denial, NOT response authenticity,
 * a verified TLS channel, successful data read or permission to call CRM.
 * No network, secrets, business records, logging or persistence.
 */
final class MockSyntheticHttpDenialReceipt
{
    /**
     * @param array<string,mixed> $headers Strict lab-only HTTP headers.
     * @return bool True denotes exact public denial structure ONLY.
     */
    public static function matches(
        int $status,
        array $headers,
        string $responseBody
    ): bool {
        try {
            if ($status !== 404
                || array_keys($headers) !== ['content-type', 'cache-control']
                || !is_string($headers['content-type'])
                || !in_array(
                    $headers['content-type'],
                    ['application/json', 'application/json; charset=utf-8'],
                    true
                )
                || $headers['cache-control'] !== 'no-store, private'
                || $responseBody === ''
                || strlen($responseBody) > 512) {
                return false;
            }
            // Byte exact, ordered, canonical denial. A purported positive,
            // redirect, login page, projection, or hidden extra field DENIES.
            $expected = json_encode(
                MockCrmPublicResponseContract::denial(),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
            return is_string($expected) && hash_equals($expected, $responseBody);
        } catch (\Throwable) {
            return false;
        }
    }
}
