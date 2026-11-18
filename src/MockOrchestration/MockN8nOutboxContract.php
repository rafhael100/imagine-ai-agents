<?php
declare(strict_types=1);
namespace ImagineAI\MockOrchestration;

/**
 * P6: closed, payload-free laboratory automation envelope for a future n8n
 * outbox. Only opaque synthetic references, never PII, prompts or credentials.
 * This class is NOT a transport, signature, webhook, or authorization.
 */
final class MockN8nOutboxContract
{
    private const KEYS=[
        'contract','event_ref','tenant_ref','run_ref',
        'kind','reference_ref','occurred_at',
    ];
    private const KINDS=[
        'mock.approval.requested',
        'mock.run.completed',
        'mock.quota.threshold',
    ];

    /** @param array<string,mixed> $event */
    public static function encode(array $event): ?string
    {
        if (!self::valid($event)) return null;
        try {
            $bytes=json_encode(
                $event,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
            );
            return strlen($bytes)<=512 ? $bytes : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return ?array<string,mixed> */
    public static function decode(string $bytes): ?array
    {
        if ($bytes==='' || strlen($bytes)>512) return null;
        try {
            $body=json_decode($bytes,true,4,JSON_THROW_ON_ERROR);
            if (!is_array($body)) return null;
            $canonical=self::encode($body);
            return $canonical!==null && hash_equals($canonical,$bytes)
                ? $body : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string,mixed> $event */
    public static function valid(array $event): bool
    {
        if (array_keys($event)!==self::KEYS
            || $event['contract']!=='SYNTHETIC_AIA_AUTOMATION_V1'
            || !is_string($event['kind'])
            || !in_array($event['kind'],self::KINDS,true)
            || !is_int($event['occurred_at'])
            || $event['occurred_at']<0 || $event['occurred_at']>4102444800) {
            return false;
        }
        foreach (['event_ref','tenant_ref','run_ref','reference_ref'] as $key) {
            if (!is_string($event[$key]) || strlen($event[$key])>90
                || preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,80}\z/D',$event[$key])!==1) {
                return false;
            }
        }
        return true;
    }
}
