<?php
declare(strict_types=1);

use ImagineAI\Integration\Crm\MinimalLeadProjection;

require_once dirname(__DIR__) . '/src/Integration/Crm/MinimalLeadProjection.php';

const P65_REF = '10000000-0000-4000-8000-000000000001';
const P65_OTHER_REF = '20000000-0000-4000-8000-000000000002';

function p65Fixture(string $stage = 'new'): array
{
    return [
        'contract' => 'CRM_AIA_HOMOLOGATION_LEAD_READ_V1',
        'operation' => 'crm.lead.read_minimal',
        'status' => 'READ',
        'scope' => 'SYNTHETIC_PRIVATE_HOMOLOGATION',
        'projection' => ['ref' => P65_REF, 'stage' => $stage],
        'operational' => false,
    ];
}

function p65Json(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function p65Assert(bool $ok): void
{
    if (!$ok) {
        throw new RuntimeException('P65_CONSUMER_CONTRACT_FAILED');
    }
}

try {
    $positive = 0;
    $negative = 0;
    $last = null;
    foreach (['new', 'qualified', 'proposal', 'won', 'lost'] as $stage) {
        $last = MinimalLeadProjection::fromHttpResponse(200, p65Json(p65Fixture($stage)), P65_REF);
        p65Assert($last instanceof MinimalLeadProjection
            && get_object_vars($last) === ['ref' => P65_REF, 'stage' => $stage]);
        ++$positive;
    }
    // The successful value stores only the two minimum fields and is immutable.
    $readonlyDenied = false;
    try {
        $last->stage = 'new';
    } catch (Error) {
        $readonlyDenied = true;
    }
    p65Assert($readonlyDenied && $last->stage === 'lost');

    $valid = p65Json(p65Fixture());
    foreach ([0, 201, 204, 301, 302, 303, 307, 308, 401, 403, 404, 419, 429, 500, 503] as $status) {
        p65Assert(MinimalLeadProjection::fromHttpResponse($status, $valid, P65_REF) === null);
        ++$negative;
    }

    $mutations = [];
    foreach ([
        'contract' => 'SYNTHETIC_CRM_AIA_PUBLIC_V1',
        'operation' => 'crm.lead.update',
        'status' => 'READ_SYNTHETIC_ONLY',
        'scope' => 'PRODUCTION',
        'operational' => true,
    ] as $key => $value) {
        $body = p65Fixture();
        $body[$key] = $value;
        $mutations[] = $body;
    }
    foreach ([null, 0, 'false'] as $value) {
        $body = p65Fixture();
        $body['operational'] = $value;
        $mutations[] = $body;
    }
    foreach (array_keys(p65Fixture()) as $key) {
        $body = p65Fixture();
        unset($body[$key]);
        $mutations[] = $body;
    }
    foreach (['principal_ref', 'tenant_ref', 'permission', 'email'] as $key) {
        $body = p65Fixture();
        $body[$key] = 'UNTRUSTED';
        $mutations[] = $body;
    }
    foreach ([null, [], 'new', 1, true,
        ['ref' => P65_REF], ['stage' => 'new'],
        ['ref' => P65_REF, 'stage' => 'new', 'email' => 'fixture@example.invalid'],
        ['stage' => 'new', 'ref' => P65_REF],
    ] as $value) {
        $body = p65Fixture();
        $body['projection'] = $value;
        $mutations[] = $body;
    }
    foreach ([null, 1, true, [], ['nested' => 'new'], 'SYNTHETIC_NEW', 'NEW', 'new ', ''] as $value) {
        $body = p65Fixture();
        $body['projection']['stage'] = $value;
        $mutations[] = $body;
    }
    foreach ([P65_OTHER_REF, 'SYNTHETIC_LEAD', '', null, 1, [], str_repeat('a', 200)] as $value) {
        $body = p65Fixture();
        $body['projection']['ref'] = $value;
        $mutations[] = $body;
    }
    $body = p65Fixture();
    $mutations[] = array_reverse($body, true);
    foreach ($mutations as $body) {
        p65Assert(MinimalLeadProjection::fromHttpResponse(200, p65Json($body), P65_REF) === null);
        ++$negative;
    }

    $rawInvalid = [
        '', '{}', '[]', 'null', 'true', '123', '<html>Login</html>',
        '{', $valid . '{}', $valid . "\n", "\xEF\xBB\xBF" . $valid,
        "\xFF" . $valid, str_repeat('x', 1025),
        str_replace('"status":"READ"', '"status":"DENIED","status":"READ"', $valid),
        str_replace('"stage":"new"', '"stage":"lost","stage":"new"', $valid),
        str_replace('"stage":"new"', '"\\u0073tage":"lost","stage":"new"', $valid),
        str_replace('"stage":"new"', '"stage":"new","stage":"new"', $valid),
        str_replace('"status":"READ"', '"status":"\\u0052EAD"', $valid),
        str_replace('"stage":"new"', '"stage":"new\\u0000"', $valid),
        str_replace('"projection":{', '"projection": {', $valid),
    ];
    foreach ($rawInvalid as $body) {
        p65Assert(MinimalLeadProjection::fromHttpResponse(200, $body, P65_REF) === null);
        ++$negative;
    }
    foreach ([P65_OTHER_REF, 'SYNTHETIC_LEAD', '', str_repeat('a', 1025),
        'ABCDEF00-0000-4000-8000-000000000001'] as $expectedRef) {
        p65Assert(MinimalLeadProjection::fromHttpResponse(200, $valid, $expectedRef) === null);
        ++$negative;
    }

    $upperRef = p65Fixture();
    $upperRef['projection']['ref'] = 'ABCDEF00-0000-4000-8000-000000000001';
    p65Assert(MinimalLeadProjection::fromHttpResponse(200, p65Json($upperRef),
        'abcdef00-0000-4000-8000-000000000001') === null);
    ++$negative;
    $lowerRef = $upperRef;
    $lowerRef['projection']['ref'] = 'abcdef00-0000-4000-8000-000000000001';
    p65Assert(MinimalLeadProjection::fromHttpResponse(200, p65Json($lowerRef),
        $lowerRef['projection']['ref']) instanceof MinimalLeadProjection);
    ++$positive;

    // A refusal leaves no mutable session/cache behind and cannot poison a
    // subsequent valid read. Conversely, a prior success cannot bless a denial.
    p65Assert(MinimalLeadProjection::fromHttpResponse(200, $valid, P65_REF) instanceof MinimalLeadProjection);
    p65Assert(MinimalLeadProjection::fromHttpResponse(404, $valid, P65_REF) === null);
    p65Assert(MinimalLeadProjection::fromHttpResponse(200, $valid, P65_OTHER_REF) === null);

    fwrite(STDOUT, json_encode([
        'schema' => 'AIA_P65_MINIMAL_READ_CONSUMER_VERIFIED_V1',
        'positive_cases' => $positive,
        'negative_cases' => $negative,
        'immutable_minimal_projection' => true,
        'service_delegation_verified' => false,
        'operational' => false,
    ], JSON_THROW_ON_ERROR) . "\n");
    fwrite(STDOUT, "AIA_P65_CRM_MINIMAL_READ_CONSUMER_PASS\n");
} catch (Throwable) {
    fwrite(STDERR, "AIA_P65_CRM_MINIMAL_READ_CONSUMER_FAILED\n");
    exit(1);
}
