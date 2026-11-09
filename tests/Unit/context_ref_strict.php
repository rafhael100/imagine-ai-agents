<?php
declare(strict_types=1);

use ImagineAI\Domain\TenantContext;

/** Contract alignment tests for the synthetic context envelope (not IAM). */
return [
    'SEC02-01 canonical fictitious context fields accepted' => function (): void {
        $ctx = context();
        check($ctx->product === 'SYNTHETIC_CRM'
            && $ctx->tenant === 'SYNTHETIC_A'
            && $ctx->resource === 'SYNTHETIC_A_LEAD');
    },
    'SEC02-02 maximal 80-character suffix permitted' => function (): void {
        $valid = 'SYNTHETIC_' . str_repeat('A', 80);
        $ctx = new TenantContext('SYNTHETIC_CRM', $valid, 'SYNTHETIC_USER',
            'SYNTHETIC_SUMMARY', 'SYNTHETIC_RESOURCE');
        check($ctx->tenant === $valid);
    },
    'SEC02-03 empty synthetic suffix is not an identity' => function (): void {
        denied('INVALID_SYNTHETIC_REF', fn() => new TenantContext('SYNTHETIC_CRM',
            'SYNTHETIC_', 'SYNTHETIC_USER', 'SYNTHETIC_SUMMARY', 'SYNTHETIC_RESOURCE'));
    },
    'SEC02-04 lower-case and mixed-case suffix denied' => function (): void {
        foreach (['SYNTHETIC_user', 'SYNTHETIC_AbC'] as $ref) {
            denied('INVALID_SYNTHETIC_REF', fn() => new TenantContext('SYNTHETIC_CRM',
                $ref, 'SYNTHETIC_USER', 'SYNTHETIC_SUMMARY', 'SYNTHETIC_RESOURCE'));
        }
    },
    'SEC02-05 hyphens spaces and punctuation denied' => function (): void {
        foreach (['SYNTHETIC_A-B', 'SYNTHETIC_A B', 'SYNTHETIC_A.B', 'SYNTHETIC_A/B'] as $ref) {
            denied('INVALID_SYNTHETIC_REF', fn() => new TenantContext('SYNTHETIC_CRM',
                $ref, 'SYNTHETIC_USER', 'SYNTHETIC_SUMMARY', 'SYNTHETIC_RESOURCE'));
        }
    },
    'SEC02-06 newline null and control characters denied' => function (): void {
        foreach (["SYNTHETIC_A\n", "SYNTHETIC_A\0", "SYNTHETIC_A\r", "SYNTHETIC_\t"] as $ref) {
            denied('INVALID_SYNTHETIC_REF', fn() => new TenantContext('SYNTHETIC_CRM',
                $ref, 'SYNTHETIC_USER', 'SYNTHETIC_SUMMARY', 'SYNTHETIC_RESOURCE'));
        }
    },
    'SEC02-07 Unicode in fictitious references denied without normalization' => function (): void {
        foreach (['SYNTHETIC_Á', 'SYNTHETIC_é', 'SYNTHETIC_K'] as $ref) {
            denied('INVALID_SYNTHETIC_REF', fn() => new TenantContext('SYNTHETIC_CRM',
                $ref, 'SYNTHETIC_USER', 'SYNTHETIC_SUMMARY', 'SYNTHETIC_RESOURCE'));
        }
    },
    'SEC02-08 overlong 81-character suffix denied' => function (): void {
        denied('INVALID_SYNTHETIC_REF', fn() => new TenantContext('SYNTHETIC_CRM',
            'SYNTHETIC_' . str_repeat('A', 81), 'SYNTHETIC_USER',
            'SYNTHETIC_SUMMARY', 'SYNTHETIC_RESOURCE'));
    },
    'SEC02-09 every one of the five context dimensions is validated' => function (): void {
        $valid = ['SYNTHETIC_CRM', 'SYNTHETIC_A', 'SYNTHETIC_USER',
            'SYNTHETIC_SUMMARY', 'SYNTHETIC_RESOURCE'];
        for ($index = 0; $index < 5; $index++) {
            $values = $valid;
            $values[$index] = 'NOT_SYNTHETIC';
            denied('INVALID_SYNTHETIC_REF', fn() => new TenantContext(...$values));
        }
    },
    'SEC02-10 unexpected logical context version still rejected' => function (): void {
        denied('VERSION_UNSUPPORTED', fn() => new TenantContext('SYNTHETIC_CRM',
            'SYNTHETIC_A', 'SYNTHETIC_USER', 'SYNTHETIC_SUMMARY', 'SYNTHETIC_RESOURCE', 'mock-v2'));
    },
    'SEC02-11 canonical context serializes predictably' => function (): void {
        $first = context();
        $second = context();
        check($first->key() === $second->key());
        check(!str_contains($first->key(), "\n"));
    },
    'SEC02-12 distinct fictitious tenant remains isolated by equality assertion' => function (): void {
        $a = context('SYNTHETIC_A');
        $b = context('SYNTHETIC_B');
        denied('CONTEXT_DENIED', fn() => $a->assertSame($b));
    },
];
