<?php
declare(strict_types=1);

/**
 * F220: validate real synthetic facade fixture after canonical JSON framing.
 * No HTTP, service identity, CRM IAM, model inference or external calls.
 */
use ImagineAI\Domain\TenantContext;
use ImagineAI\MockBridge\{MockCrmReadRequest, MockCrmReadRequestBuilder, MockCrmPublicResponseContract};

try {
    ob_start();
    require dirname(__DIR__) . '/tools/op01_php_public_response_probe.php';
    $content = ob_get_clean();
    if (!is_string($content) || strlen($content) > 16384) {
        throw new RuntimeException('F220_PROBE_MISSING');
    }
    $probe = json_decode($content, true, 8, JSON_THROW_ON_ERROR);
    if (!is_array($probe) || !is_array($probe['cases'] ?? null)
        || count($probe['cases']) !== 6) {
        throw new RuntimeException('F220_PROBE_DRIFT');
    }
    $ctx = new TenantContext(
        'SYNTHETIC_CRM', 'SYNTHETIC_A', 'SYNTHETIC_PRINCIPAL',
        'SYNTHETIC_SUMMARY', 'SYNTHETIC_A_LEAD'
    );
    $accepted = 0;
    $denied = 0;
    $goodBody = null;
    $goodRequest = null;
    foreach ($probe['cases'] as $case) {
        $fields = ($case['response']['state'] ?? null) === 'READ_SYNTHETIC_ONLY'
            ? $case['fields'] : ['ref'];
        $wire = MockCrmReadRequestBuilder::fromContext(
            $ctx, 'SYNTHETIC_F220_REQUEST', $fields
        );
        $request = new MockCrmReadRequest($wire);
        $out = $case['response'];
        $json = MockCrmPublicResponseContract::encodeCanonicalJson(
            $out, $request, $ctx
        );
        if (!is_string($json) || strlen($json) > 512
            || MockCrmPublicResponseContract::decodeCanonicalJson(
                $json, $request, $ctx
            ) !== $out) {
            throw new RuntimeException('F220_BAD_ROUNDTRIP');
        }
        if (MockCrmPublicResponseContract::isAcceptedRead(
            $out, $request, $ctx
        )) {
            ++$accepted;
            if ($fields === ['stage', 'ref']) {
                $goodBody = $json;
                $goodRequest = $request;
            }
        } else {
            ++$denied;
            if ($out !== MockCrmPublicResponseContract::denial()) {
                throw new RuntimeException('F220_BAD_DENIAL');
            }
        }
    }
    if ($accepted !== 2 || $denied !== 4
        || !is_string($goodBody) || $goodRequest === null) {
        throw new RuntimeException('F220_COVERAGE_DRIFT');
    }

    $good = json_decode($goodBody, true, 4, JSON_THROW_ON_ERROR);
    $extra = $good; $extra['is_admin'] = true;
    $wrongState = $good; $wrongState['state'] = 'ALLOW';
    $wrongAuth = $good; $wrongAuth['real_authorization'] = true;
    $wrongCalls = $good; $wrongCalls['external_calls'] = 1;
    $personal = $good;
    $personal['projection']['email'] = 'someone@example.test';
    $private = $good;
    $private['projection']['ref'] = 'someone@example.test';
    $wrongOrder = $good;
    $wrongOrder['projection'] = [
        'ref' => 'SYNTHETIC_A_LEAD', 'stage' => 'SYNTHETIC_NEW'
    ];
    $missing = $good; unset($missing['projection']);
    $deniedWithProjection = MockCrmPublicResponseContract::denial();
    $deniedWithProjection['projection'] = $good['projection'];
    $bad = [
        'DUPLICATE_ROOT' => substr($goodBody, 0, -1) .
            ',"state":"READ_SYNTHETIC_ONLY"}',
        'DUPLICATE_PROJECTION' => str_replace(
            '"ref":"SYNTHETIC_A_LEAD"',
            '"ref":"SYNTHETIC_A_LEAD","ref":"SYNTHETIC_A_LEAD"',
            $goodBody
        ),
        'WHITESPACE' => str_replace(',"state"', ', "state"', $goodBody),
        'ESCAPED' => str_replace(
            'SYNTHETIC_CRM_AIA_PUBLIC_V1',
            '\u0053YNTHETIC_CRM_AIA_PUBLIC_V1',
            $goodBody
        ),
        'REORDER' => json_encode(
            array_reverse($good, true), JSON_THROW_ON_ERROR
        ),
        'EXTRA' => json_encode($extra, JSON_THROW_ON_ERROR),
        'BAD_STATE' => json_encode($wrongState, JSON_THROW_ON_ERROR),
        'AUTH_TRUE' => json_encode($wrongAuth, JSON_THROW_ON_ERROR),
        'EXTERNAL_CALL' => json_encode($wrongCalls, JSON_THROW_ON_ERROR),
        'PERSONAL_FIELD' => json_encode($personal, JSON_THROW_ON_ERROR),
        'PRIVATE_VALUE' => json_encode($private, JSON_THROW_ON_ERROR),
        'FIELD_ORDER' => json_encode($wrongOrder, JSON_THROW_ON_ERROR),
        'MISSING_KEY' => json_encode($missing, JSON_THROW_ON_ERROR),
        'DENIAL_PROJECTION' => json_encode(
            $deniedWithProjection, JSON_THROW_ON_ERROR
        ),
        'TRAILING' => $goodBody . '{}',
        'OVER_SIZE' => str_repeat('X', 1024),
        'INVALID_UTF8' => $goodBody . "\xFF",
        'ROOT_NULL' => 'null',
        'ROOT_ARRAY' => '[]',
        'EMPTY' => '',
    ];
    $negatives = 0;
    foreach ($bad as $label => $bytes) {
        if (MockCrmPublicResponseContract::decodeCanonicalJson(
            $bytes, $goodRequest, $ctx
        ) !== null) throw new RuntimeException('F220_UNSAFE_' . $label);
        ++$negatives;
    }
    $swap = MockCrmReadRequestBuilder::fromContext(
        $ctx, 'SYNTHETIC_F220_SWAP', ['ref','stage']
    );
    $differentRequest = new MockCrmReadRequest($swap);
    if (MockCrmPublicResponseContract::decodeCanonicalJson(
        $goodBody, $differentRequest, $ctx
    ) !== null) throw new RuntimeException('F220_WRONG_FIELD_ORDER');
    if (MockCrmPublicResponseContract::isAcceptedRead(
        MockCrmPublicResponseContract::denial(),
        $goodRequest, $ctx
    )) throw new RuntimeException('F220_DENIAL_AS_SUCCESS');

    echo 'F220_CANONICAL_RESPONSE_PASS facade_cases=6 successes=2 denials=4'
        . ' mutations=' . $negatives
        . ' operational_decision=NO_GO real_authorization=false'
        . ' external_calls=0' . PHP_EOL;
} catch (Throwable) {
    if (ob_get_level() > 0) ob_end_clean();
    fwrite(STDERR,
        "F220_CANONICAL_RESPONSE_DENIED operational_decision=NO_GO\n");
    exit(1);
}
