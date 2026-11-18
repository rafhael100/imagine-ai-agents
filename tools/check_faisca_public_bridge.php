<?php
declare(strict_types=1);

/** Dependency-free R05 checks. Every CLI case starts this checkout's real PHP worker. */
spl_autoload_register(static function (string $class): void {
    $prefix = 'ImagineAI\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});

use ImagineAI\Contracts\AiSuggestionProvider;
use ImagineAI\Domain\{Intent, Run, TenantContext};
use ImagineAI\Integration\Faisca\{DenyOnlyEffectGateway, PublicConversationBridge};
use ImagineAI\Mocks\MockAiSuggestionProvider;

$faiscaChecks = 0;
$faiscaCliCases = 0;

function faiscaAssert(bool $condition, string $name): void
{
    global $faiscaChecks;
    ++$faiscaChecks;
    if (!$condition) throw new RuntimeException('CHECK_' . $name);
}

function faiscaDenied(string $code, callable $operation, string $name): void
{
    try {
        $operation();
    } catch (DomainException $error) {
        faiscaAssert($error->getMessage() === $code, $name . '_code');
        return;
    }
    throw new RuntimeException('CHECK_' . $name . '_not_denied');
}

function faiscaJson(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function faiscaRequest(array $policy): array
{
    return [
        'schema' => 'faisca.agents.request.v1',
        'invocationId' => str_repeat('a', 32),
        'requestFingerprint' => str_repeat('b', 64),
        'candidateDigest' => str_repeat('c', 64),
        'conversationId' => 'synthetic_conversation_01',
        'messageId' => 'synthetic_message_01',
        'locale' => 'pt-BR',
        'context' => [
            'scope' => 'public_comercial', 'knowledgeVersion' => $policy['knowledgeVersion'],
            'knowledgeDigest' => $policy['knowledgeDigest'],
            'sourceIds' => [$policy['sourceIds'][0]], 'actionIds' => ['sell', 'faq'],
        ],
    ];
}

function faiscaExpected(array $request): array
{
    return [
        'schema' => 'faisca.agents.result.v1',
        'invocationId' => $request['invocationId'],
        'requestFingerprint' => $request['requestFingerprint'],
        'candidateDigest' => $request['candidateDigest'],
        'conversationId' => $request['conversationId'],
        'messageId' => $request['messageId'], 'locale' => 'pt-BR',
        'context' => [
            'scope' => 'public_comercial', 'knowledgeVersion' => 'faisca-editorial-2',
            'knowledgeDigest' => 'fe7daf5ab310acc5a0a600803254344ed02eff7bbd860b731cdf033dcbe4ead7',
        ],
        'suggestion' => [
            'kind' => 'SYNTHETIC_SUGGESTION', 'version' => 'mock-v1',
            'run' => 'SYNTHETIC_' . strtoupper($request['invocationId']),
            'source' => 'SYNTHETIC_FAISCA_PUBLIC', 'text' => 'SYNTHETIC summary proposal only',
        ],
        'evidence' => ['runState' => 'CONCLUIDA', 'suggestions' => 1, 'effects' => 0, 'knowledgePassages' => 1],
        'operationalEffects' => false,
    ];
}

final class FaiscaTestProvider implements AiSuggestionProvider
{
    public int $calls = 0;
    public array $fields = [];
    public ?PublicConversationBridge $bridge = null;
    public function __construct(private readonly string $mode = 'success') {}
    public function suggest(Run $run, array $fields): array
    {
        ++$this->calls;
        $this->fields = $fields;
        if ($this->mode === 'throw') throw new RuntimeException('SYNTHETIC_PRIVATE_EXCEPTION_DETAILS');
        if ($this->mode === 'mutate_run') {
            try { $run->transition('CONCLUIDA'); } catch (Throwable) { /* Swallowing must still deny the outer call. */ }
        }
        if ($this->mode === 'create_step') {
            try { $run->step('SYNTHETIC_CALLBACK_STEP'); } catch (Throwable) { /* Same sticky guard. */ }
        }
        if ($this->mode === 'revoke') $this->bridge->revokePendingContext();
        if ($this->mode === 'disable_source') $this->bridge->disablePendingSource();
        $result = (new MockAiSuggestionProvider())->suggest($run, $fields);
        if ($this->mode === 'wrong_source') $result['source'] = 'SYNTHETIC_CRM_RESOURCE';
        if ($this->mode === 'wrong_run') $result['run'] = 'SYNTHETIC_DIFFERENT_RUN';
        if ($this->mode === 'wrong_text') $result['text'] = 'SYNTHETIC arbitrary unreviewed proposal';
        if ($this->mode === 'wrong_version') $result['version'] = 'mock-v2';
        if ($this->mode === 'wrong_kind') $result['kind'] = 'SYNTHETIC_EFFECT';
        if ($this->mode === 'extra') $result['operationalEffects'] = true;
        return $result;
    }
}

/** argv is an array, no shell. The child inherits no credential-bearing environment. */
function faiscaCli(string $body, array $overrides = [], array $arguments = []): array
{
    global $faiscaCliCases;
    ++$faiscaCliCases;
    $environment = ['IMAGINE_FAISCA_AGENTS_LAB' => '1', 'IMAGINE_FAISCA_AGENTS_SCENARIO' => 'success'];
    foreach ($overrides as $key => $value) {
        if ($value === null) unset($environment[$key]);
        else $environment[$key] = $value;
    }
    $command = [PHP_BINARY, '-n', '-d', 'display_errors=0', '-d', 'log_errors=0',
        __DIR__ . '/faisca_public_bridge.php', ...$arguments];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes, dirname(__DIR__), $environment, ['bypass_shell' => true]);
    if (!is_resource($process)) throw new RuntimeException('CHECK_cli_spawn');
    $started = microtime(true);
    @fwrite($pipes[0], $body);
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    try {
        do {
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);
            if (strlen($stdout) > 32768 || strlen($stderr) > 4096) throw new RuntimeException('CHECK_cli_output_limit');
            $status = proc_get_status($process);
            if (!$status['running']) break;
            if (microtime(true) - $started > 5) throw new RuntimeException('CHECK_cli_timeout');
            usleep(10000);
        } while (true);
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $closed = proc_close($process);
        return ['code' => $status['exitcode'] >= 0 ? $status['exitcode'] : $closed,
            'stdout' => $stdout, 'stderr' => $stderr, 'elapsed' => microtime(true) - $started];
    } catch (Throwable $error) {
        proc_terminate($process, 9);
        foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
        proc_close($process);
        throw $error;
    }
}

function faiscaCliError(string $body, string $code, array $environment = [], array $arguments = []): void
{
    $result = faiscaCli($body, $environment, $arguments);
    faiscaAssert($result['code'] === 2 && $result['stderr'] === '', 'cli_error_exit');
    faiscaAssert($result['stdout'] === faiscaJson(['schema' => 'faisca.agents.error.v1', 'code' => $code]) . "\n", 'cli_error_shape');
}

try {
    $policyBytes = file_get_contents(dirname(__DIR__) . '/contracts/faisca/public-context-v1.json');
    faiscaAssert(is_string($policyBytes) && hash('sha256', $policyBytes) === PublicConversationBridge::POLICY_SHA256, 'policy_exact');
    $policy = json_decode($policyBytes, true, 5, JSON_THROW_ON_ERROR);
    $request = faiscaRequest($policy);
    $body = faiscaJson($request);

    $bridge = new PublicConversationBridge($policyBytes);
    $bridge->prepare($body, 1000);
    faiscaAssert($bridge->inspect() === ['phase' => 'prepared', 'runState' => 'EXECUTANDO',
        'suggestions' => 1, 'gatewayAttempts' => 0, 'knowledgeDeliveries' => 1], 'actual_prepare_counts');
    faiscaDenied('unavailable', fn () => $bridge->prepare($body, 1000), 'prepare_once');
    faiscaAssert($bridge->deliver(1001) === faiscaExpected($request), 'native_result_exact');
    faiscaAssert($bridge->inspect() === ['phase' => 'delivered', 'runState' => 'CONCLUIDA',
        'suggestions' => 1, 'gatewayAttempts' => 0, 'knowledgeDeliveries' => 2], 'actual_delivery_counts');
    faiscaDenied('unavailable', fn () => $bridge->deliver(1001), 'delivery_once');

    $spy = new FaiscaTestProvider();
    $observed = new PublicConversationBridge($policyBytes, $spy);
    $observed->prepare($body, 1000);
    $observed->deliver(1000);
    faiscaAssert($spy->calls === 1 && array_keys($spy->fields) === ['ref', 'stage', 'text'], 'provider_once_closed_fields');
    foreach ($spy->fields as $field) faiscaAssert(str_starts_with($field, 'SYNTHETIC_') && strlen($field) <= 256, 'provider_only_synthetic');
    faiscaAssert(!str_contains(faiscaJson($spy->fields), $request['conversationId']), 'no_conversation_as_authority');

    $empty = $request;
    $empty['context']['sourceIds'] = [];
    $empty['context']['actionIds'] = [];
    $full = $request;
    $full['context']['sourceIds'] = $policy['sourceIds'];
    $full['context']['actionIds'] = array_slice($policy['actionIds'], 0, 6);
    $edgeIds = $request;
    $edgeIds['invocationId'] = str_repeat('d', 32);
    $edgeIds['requestFingerprint'] = str_repeat('e', 64);
    $edgeIds['candidateDigest'] = str_repeat('f', 64);
    $edgeIds['conversationId'] = str_repeat('_', 96);
    $edgeIds['messageId'] = '--------';
    foreach ([$empty, $full, $edgeIds] as $valid) {
        $sample = new PublicConversationBridge($policyBytes);
        $sample->prepare(faiscaJson($valid), 1000);
        faiscaAssert($sample->deliver(1029) === faiscaExpected($valid), 'valid_boundary_correlation');
    }
    foreach ($policy['actionIds'] as $action) {
        $valid = $empty;
        $valid['context']['actionIds'] = [$action];
        $sample = new PublicConversationBridge($policyBytes);
        $sample->prepare(faiscaJson($valid), 1000);
        faiscaAssert($sample->deliver(1000) === faiscaExpected($valid), 'all_curated_actions');
    }

    $invalidBodies = ['', '{}', '[]', 'null', 'false', '"text"', '{', $body . "\n", ' ' . $body,
        "\xEF\xBB\xBF" . $body, $body . $body, str_repeat('x', 16385),
        str_replace('"schema":', '"schema":"duplicate","schema":', $body),
        str_replace('"schema"', '"sch\\u0065ma"', $body),
        str_replace('"scope":', '"scope":"duplicate","scope":', $body),
    ];
    foreach (array_keys($request) as $key) {
        $bad = $request;
        unset($bad[$key]);
        $invalidBodies[] = faiscaJson($bad);
    }
    foreach (['text', 'input', 'token', 'provider', 'tenant', 'principal', 'scenario', '__proto__'] as $key) {
        $bad = $request;
        $bad[$key] = 'SYNTHETIC_UNTRUSTED';
        $invalidBodies[] = faiscaJson($bad);
    }
    foreach ([
        ['schema', 'faisca.agents.request.v2'], ['locale', 'en-US'], ['invocationId', str_repeat('A', 32)],
        ['invocationId', str_repeat('a', 31)], ['requestFingerprint', str_repeat('b', 63)],
        ['candidateDigest', str_repeat('g', 64)], ['conversationId', 'short'],
        ['conversationId', str_repeat('a', 97)], ['messageId', '../synthetic'], ['messageId', "synthetic\nmessage"],
        ['messageId', 'sintético'], ['locale', ['pt-BR']], ['invocationId', 123],
    ] as [$key, $value]) {
        $bad = $request;
        $bad[$key] = $value;
        $invalidBodies[] = faiscaJson($bad);
    }
    foreach ($invalidBodies as $invalid) {
        $spy = new FaiscaTestProvider();
        $sample = new PublicConversationBridge($policyBytes, $spy);
        faiscaDenied('invalid_request', fn () => $sample->prepare($invalid, 1000), 'invalid_request');
        faiscaAssert($spy->calls === 0 && $sample->inspect()['knowledgeDeliveries'] === 0, 'deny_before_suggestion');
    }

    $badContexts = [];
    foreach (['scope' => 'customer_private', 'knowledgeVersion' => 'faisca-editorial-1',
        'knowledgeDigest' => str_repeat('0', 64), 'tenant' => 'SYNTHETIC_OTHER',
        'principal' => 'SYNTHETIC_ADMIN', 'provider' => 'external'] as $key => $value) {
        $bad = $request;
        $bad['context'][$key] = $value;
        $badContexts[] = $bad;
    }
    foreach ([null, [], 'public_comercial', ['scope' => 'public_comercial']] as $value) {
        $bad = $request;
        $bad['context'] = $value;
        $badContexts[] = $bad;
    }
    foreach ($badContexts as $bad) {
        $spy = new FaiscaTestProvider();
        $sample = new PublicConversationBridge($policyBytes, $spy);
        faiscaDenied('context_denied', fn () => $sample->prepare(faiscaJson($bad), 1000), 'context_denied');
        faiscaAssert($spy->calls === 0, 'context_before_provider');
    }
    foreach ([
        ['sourceIds', ['private.customer']], ['sourceIds', array_fill(0, 5, $policy['sourceIds'][0])],
        ['sourceIds', [$policy['sourceIds'][0], $policy['sourceIds'][0]]], ['sourceIds', null],
        ['sourceIds', [['nested']]], ['actionIds', ['execute_sql']], ['actionIds', ['sell', 'sell']],
        ['actionIds', array_slice($policy['actionIds'], 0, 7)], ['actionIds', 'sell'], ['actionIds', [1]],
    ] as [$key, $value]) {
        $bad = $request;
        $bad['context'][$key] = $value;
        $spy = new FaiscaTestProvider();
        $sample = new PublicConversationBridge($policyBytes, $spy);
        faiscaDenied('source_denied', fn () => $sample->prepare(faiscaJson($bad), 1000), 'references_denied');
        faiscaAssert($spy->calls === 0, 'references_before_provider');
    }
    foreach (['', $policyBytes . ' ', str_replace('public_comercial', 'customer_private', $policyBytes)] as $badPolicy) {
        faiscaDenied('context_denied', fn () => new PublicConversationBridge($badPolicy), 'pinned_policy');
    }

    foreach (['wrong_source', 'wrong_run', 'wrong_text', 'wrong_version', 'wrong_kind', 'extra',
        'throw', 'mutate_run', 'create_step'] as $mode) {
        $provider = new FaiscaTestProvider($mode);
        $sample = new PublicConversationBridge($policyBytes, $provider);
        faiscaDenied('invalid_suggestion', fn () => $sample->prepare($body, 1000), 'provider_output_guard');
        faiscaAssert($provider->calls === 1 && $sample->inspect()['runState'] === 'FALHOU'
            && $sample->inspect()['gatewayAttempts'] === 0, 'provider_failure_terminal');
    }
    foreach (['revoke', 'disable_source'] as $mode) {
        $provider = new FaiscaTestProvider($mode);
        $sample = new PublicConversationBridge($policyBytes, $provider);
        $provider->bridge = $sample;
        $sample->prepare($body, 1000);
        faiscaDenied('context_denied', fn () => $sample->deliver(1000), 'callback_context_recheck');
        faiscaAssert($sample->inspect()['runState'] === 'FALHOU' && $sample->inspect()['suggestions'] === 1, 'recheck_after_real_suggest');
    }
    foreach ([999, 1030] as $deliveryTime) {
        $sample = new PublicConversationBridge($policyBytes);
        $sample->prepare($body, 1000);
        faiscaDenied('context_denied', fn () => $sample->deliver($deliveryTime), 'delivery_clock');
        faiscaAssert($sample->inspect()['runState'] === 'FALHOU', 'expired_run_terminal');
    }
    $sample = new PublicConversationBridge($policyBytes);
    faiscaDenied('context_denied', fn () => $sample->prepare($body, -1), 'invalid_initial_clock');

    $context = new TenantContext('SYNTHETIC_FAISCA_COMERCIAL', 'SYNTHETIC_FAISCA_PUBLIC',
        'SYNTHETIC_FAISCA_LAB_HOST', 'SYNTHETIC_FAISCA_PUBLIC_GUIDANCE', 'SYNTHETIC_FAISCA_PUBLIC');
    $run = new Run('SYNTHETIC_GATEWAY_TEST', $context);
    $run->transition('EXECUTANDO');
    $intent = new Intent('SYNTHETIC_GATEWAY_INTENT', $run->step('SYNTHETIC_GATEWAY_STEP'),
        'SYNTHETIC_GATEWAY_KEY', 'mock.task.create', 'SYNTHETIC_TEST_ONLY');
    $gateway = new DenyOnlyEffectGateway();
    faiscaDenied('FAISCA_EFFECTS_DISABLED', fn () => $gateway->submit($intent, null, 1000), 'effect_submit_denied');
    faiscaDenied('FAISCA_EFFECTS_DISABLED', fn () => $gateway->status($context, $intent), 'effect_status_denied');
    faiscaAssert($gateway->attemptCount() === 2, 'denied_gateway_counts');

    // Real CLI: no substitute process, fake stdout or skipped PHP dependency.
    foreach ([$request, $empty, $full, $edgeIds] as $valid) {
        $result = faiscaCli(faiscaJson($valid));
        faiscaAssert($result['code'] === 0 && $result['stderr'] === '', 'cli_success_exit');
        faiscaAssert($result['stdout'] === faiscaJson(faiscaExpected($valid)) . "\n", 'cli_success_exact');
    }
    faiscaCliError($body, 'lab_disabled', ['IMAGINE_FAISCA_AGENTS_LAB' => null]);
    faiscaCliError($body, 'lab_disabled', ['IMAGINE_FAISCA_AGENTS_LAB' => '0']);
    faiscaCliError($body, 'invalid_request', [], ['unexpected']);
    foreach (['{', $body . "\n", str_repeat('x', 16385),
        str_replace('"schema":', '"schema":"duplicate","schema":', $body)] as $invalid) {
        faiscaCliError($invalid, 'invalid_request');
    }
    $bad = $request;
    $bad['token'] = 'SYNTHETIC_NEVER_FORWARDED';
    faiscaCliError(faiscaJson($bad), 'invalid_request');
    $bad = $request;
    $bad['messageId'] = '../foreign';
    faiscaCliError(faiscaJson($bad), 'invalid_request');
    foreach (['knowledgeVersion' => 'faisca-editorial-1', 'knowledgeDigest' => str_repeat('0', 64),
        'scope' => 'customer_private', 'tenant' => 'SYNTHETIC_OTHER'] as $key => $value) {
        $bad = $request;
        $bad['context'][$key] = $value;
        faiscaCliError(faiscaJson($bad), 'context_denied');
    }
    foreach (['sourceIds' => ['customer.private'], 'actionIds' => ['execute_sql']] as $key => $value) {
        $bad = $request;
        $bad['context'][$key] = $value;
        faiscaCliError(faiscaJson($bad), 'source_denied');
    }
    faiscaCliError($body, 'unavailable', ['IMAGINE_FAISCA_AGENTS_SCENARIO' => 'unavailable']);
    faiscaCliError($body, 'context_denied', ['IMAGINE_FAISCA_AGENTS_SCENARIO' => 'revoke_before_delivery']);
    faiscaCliError($body, 'unavailable', ['IMAGINE_FAISCA_AGENTS_SCENARIO' => 'external']);
    foreach (['-1', '0200', '2001', '1.1'] as $invalidDelay) {
        faiscaCliError($body, 'unavailable', ['IMAGINE_FAISCA_AGENTS_DELAY_MS' => $invalidDelay]);
    }
    $delayed = faiscaCli($body, ['IMAGINE_FAISCA_AGENTS_SCENARIO' => 'delay', 'IMAGINE_FAISCA_AGENTS_DELAY_MS' => '120']);
    faiscaAssert($delayed['code'] === 0 && $delayed['stderr'] === '' && $delayed['elapsed'] >= 0.1, 'cli_delay_after_execution');
    faiscaAssert($delayed['stdout'] === faiscaJson(faiscaExpected($request)) . "\n", 'cli_delay_exact');
    $malformed = faiscaCli($body, ['IMAGINE_FAISCA_AGENTS_SCENARIO' => 'malformed']);
    faiscaAssert($malformed['code'] === 0 && $malformed['stderr'] === ''
        && $malformed['stdout'] === '{"schema":"faisca.agents.result.v1"' . "\n", 'cli_malformed_real_fault');
    $lost = faiscaCli($body, ['IMAGINE_FAISCA_AGENTS_SCENARIO' => 'exit_after_dispatch']);
    faiscaAssert($lost['code'] === 3 && $lost['stdout'] === '' && $lost['stderr'] === '', 'cli_exit_after_real_dispatch');

    echo 'FAISCA_PUBLIC_BRIDGE_PASS checks=' . $faiscaChecks . ' cli_cases=' . $faiscaCliCases
        . " php_processes_executed=true operational_effects=false real_model=false\n";
} catch (Throwable $error) {
    $label = $error instanceof RuntimeException && preg_match('/\ACHECK_[a-z_]+\z/D', $error->getMessage()) === 1
        ? $error->getMessage() : 'CHECK_unexpected_failure';
    fwrite(STDERR, 'FAISCA_PUBLIC_BRIDGE_FAIL ' . $label . "\n");
    exit(1);
}
