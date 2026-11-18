<?php
declare(strict_types=1);

/** R06 contract checks. CLI cases require this checkout's real PHP executable. */
spl_autoload_register(static function (string $class): void {
    $prefix = 'ImagineAI\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});

use ImagineAI\Contracts\AiSuggestionProvider;
use ImagineAI\Domain\Run;
use ImagineAI\Integration\Faisca\{GroundedConversationBridge, PublicPassageCatalog};
use ImagineAI\Mocks\MockAiSuggestionProvider;

$f06Checks = 0;
$f06CliCases = 0;

function f06Assert(bool $condition, string $name): void
{
    global $f06Checks;
    ++$f06Checks;
    if (!$condition) throw new RuntimeException('CHECK_' . $name);
}

function f06Denied(string $code, callable $operation, string $name): void
{
    try { $operation(); }
    catch (DomainException $error) {
        f06Assert($error->getMessage() === $code, $name . '_code');
        return;
    }
    throw new RuntimeException('CHECK_' . $name . '_not_denied');
}

function f06Json(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

/** Independent wire oracle, with golden digests checked below as a second oracle. */
function f06Fixture(array $catalog, string $answerId = 'goal_presence',
    string $conversationId = 'synthetic_conversation_01', string $messageId = 'synthetic_message_01'): array
{
    $recipe = array_column($catalog['answers'], null, 'id')[$answerId];
    $byId = array_column($catalog['passages'], null, 'id');
    $passages = [];
    $parts = $recipe['prefix'] === '' ? [] : [$recipe['prefix']];
    foreach ($recipe['passageIds'] as $id) {
        $passages[] = [...$byId[$id], 'trust' => 'UNTRUSTED_DATA'];
        $parts[] = $byId[$id]['title'] . ":\n" . $byId[$id]['text'];
    }
    if ($recipe['suffix'] !== '') $parts[] = $recipe['suffix'];
    $text = implode("\n\n", $parts);
    $candidate = [
        'schema' => 'faisca.response.v1', 'conversationId' => $conversationId, 'messageId' => $messageId,
        'locale' => 'pt-BR', 'knowledgeVersion' => 'faisca-editorial-2', 'text' => $text,
        'sources' => array_map(static fn (string $id): array => [
            'id' => 'faisca.passage.' . $id . '@r06-1', 'version' => 'faisca-grounding-1',
        ], $recipe['passageIds']),
        'actions' => array_map(static fn (string $id): array => ['id' => $id], $recipe['actionIds']),
    ];
    $request = [
        'schema' => 'faisca.grounded.request.v1', 'invocationId' => str_repeat('a', 32),
        'requestFingerprint' => str_repeat('b', 64), 'candidateDigest' => hash('sha256', f06Json($candidate)),
        'conversationId' => $conversationId, 'messageId' => $messageId, 'locale' => 'pt-BR',
        'grounding' => [
            'catalogVersion' => 'faisca-grounding-1',
            'catalogDigest' => '205754a9b45e723560276e0f7145b05b1a1d3eb6d87c5dd9e006ce84b9aceed8',
            'answerId' => $answerId, 'passageIds' => $recipe['passageIds'],
        ],
    ];
    $binding = [
        'catalogVersion' => $request['grounding']['catalogVersion'],
        'catalogDigest' => $request['grounding']['catalogDigest'], 'answerId' => $answerId,
        'candidateDigest' => $request['candidateDigest'], 'passageIds' => $recipe['passageIds'],
        'passageDigests' => array_column($passages, 'digest'),
    ];
    $expected = [
        'schema' => 'faisca.grounded.result.v1',
        'execution' => [
            'schema' => 'faisca.agents.result.v1', 'invocationId' => $request['invocationId'],
            'requestFingerprint' => $request['requestFingerprint'], 'candidateDigest' => $request['candidateDigest'],
            'conversationId' => $conversationId, 'messageId' => $messageId, 'locale' => 'pt-BR',
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
        ],
        'grounding' => [...$request['grounding'], 'passages' => $passages, 'text' => $text,
            'bindingDigest' => hash('sha256', f06Json($binding))],
    ];
    return ['request' => $request, 'candidate' => $candidate, 'expected' => $expected, 'recipe' => $recipe];
}

final class F06TestProvider implements AiSuggestionProvider
{
    public int $calls = 0;
    public array $fields = [];
    public ?GroundedConversationBridge $bridge = null;
    public function __construct(private readonly string $mode = 'success') {}
    public function suggest(Run $run, array $fields): array
    {
        ++$this->calls;
        $this->fields = $fields;
        if ($this->mode === 'throw') throw new RuntimeException('SYNTHETIC_PRIVATE_EXCEPTION_DETAILS');
        if ($this->mode === 'mutate_run') {
            try { $run->transition('CONCLUIDA'); } catch (Throwable) { /* Sticky callback guard still denies. */ }
        }
        if ($this->mode === 'revoke') $this->bridge->revokePendingContext();
        if ($this->mode === 'disable_source') $this->bridge->disablePendingSource();
        $result = (new MockAiSuggestionProvider())->suggest($run, $fields);
        if ($this->mode === 'wrong_source') $result['source'] = 'SYNTHETIC_CRM_RESOURCE';
        if ($this->mode === 'wrong_text') $result['text'] = 'SYNTHETIC arbitrary unreviewed proposal';
        if ($this->mode === 'extra') $result['operationalEffects'] = true;
        return $result;
    }
}

/** Real PHP subprocess, array argv, bounded output/time and no inherited credentials. */
function f06Cli(string $body, array $overrides = [], array $arguments = []): array
{
    global $f06CliCases;
    ++$f06CliCases;
    $environment = ['IMAGINE_FAISCA_GROUNDED_LAB' => '1', 'IMAGINE_FAISCA_AGENTS_LAB' => '1',
        'IMAGINE_FAISCA_AGENTS_SCENARIO' => 'success'];
    foreach ($overrides as $key => $value) {
        if ($value === null) unset($environment[$key]);
        else $environment[$key] = $value;
    }
    $command = [PHP_BINARY, '-n', '-d', 'display_errors=0', '-d', 'log_errors=0',
        __DIR__ . '/faisca_grounded_bridge.php', ...$arguments];
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
        if (strlen($stdout) > 32768 || strlen($stderr) > 4096) throw new RuntimeException('CHECK_cli_output_limit');
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

function f06CliError(string $body, string $code, array $environment = [], array $arguments = []): void
{
    $result = f06Cli($body, $environment, $arguments);
    f06Assert($result['code'] === 2 && $result['stderr'] === '', 'cli_error_exit');
    f06Assert($result['stdout'] === f06Json(['schema' => 'faisca.grounded.error.v1', 'code' => $code]) . "\n", 'cli_error_shape');
}

try {
    $policyBytes = file_get_contents(dirname(__DIR__) . '/contracts/faisca/public-context-v1.json');
    $catalogBytes = file_get_contents(dirname(__DIR__) . '/contracts/faisca/public-passages-v1.json');
    f06Assert(is_string($policyBytes) && is_string($catalogBytes)
        && hash('sha256', $catalogBytes) === PublicPassageCatalog::SHA256, 'catalog_exact');
    $catalog = json_decode($catalogBytes, true, 9, JSON_THROW_ON_ERROR);
    f06Assert(count($catalog['passages']) === 21 && count($catalog['answers']) === 11, 'curated_inventory');
    $fixture = f06Fixture($catalog);
    $request = $fixture['request'];
    $body = f06Json($request);
    f06Assert($request['candidateDigest'] === 'b38ca2663b36834914d299c7bf00f9e4e9006ad9fe73e4e14ca9319a78b507a2', 'golden_candidate');
    f06Assert($fixture['expected']['grounding']['bindingDigest'] === 'be0072c3c34244452888a56db09ce67c03fb2c911a9f98eacd2d653b8c5a66eb', 'golden_binding');

    $sourceMap = [
        'src/data/services.mjs' => 'comercial.services@209bd893:faisca-1',
        'src/data/commercial-faq.mjs' => 'comercial.faq@209bd893:faisca-1',
        'src/data/commercial-process.mjs' => 'comercial.process@209bd893:faisca-1',
    ];
    foreach ($catalog['answers'] as $answer) {
        $case = f06Fixture($catalog, $answer['id']);
        $spy = new F06TestProvider();
        $bridge = new GroundedConversationBridge($policyBytes, $catalogBytes, $spy);
        $bridge->prepare(f06Json($case['request']), 1000);
        f06Assert($bridge->inspect()['execution'] === ['phase' => 'prepared', 'runState' => 'EXECUTANDO',
            'suggestions' => 1, 'gatewayAttempts' => 0, 'knowledgeDeliveries' => 1], 'real_legacy_preparation');
        $result = $bridge->deliver(1029);
        f06Assert($result === $case['expected'], 'all_recipes_exact');
        f06Assert($bridge->inspect()['phase'] === 'delivered' && $bridge->inspect()['grounding']['phase'] === 'closed'
            && $bridge->inspect()['grounding']['deliveries'] === 2
            && $bridge->inspect()['execution']['runState'] === 'CONCLUIDA'
            && $bridge->inspect()['execution']['knowledgeDeliveries'] === 2, 'revalidated_both_layers');
        $sourceIds = [];
        foreach ($result['grounding']['passages'] as $passage) {
            if (!in_array($sourceMap[$passage['sourcePath']], $sourceIds, true)) $sourceIds[] = $sourceMap[$passage['sourcePath']];
            f06Assert(strlen($passage['text']) <= 512 && hash('sha256', $passage['text']) === $passage['digest']
                && $passage['trust'] === 'UNTRUSTED_DATA', 'exact_untrusted_passage');
        }
        f06Assert(count($result['grounding']['passages']) <= 3
            && array_sum(array_map(static fn (array $p): int => strlen($p['text']), $result['grounding']['passages'])) <= 1200,
            'bounded_utf8_context');
        $reference = hash('sha256', f06Json(['policy' => $result['execution']['context']['knowledgeDigest'],
            'sourceIds' => $sourceIds, 'actionIds' => $answer['actionIds']]));
        f06Assert($spy->calls === 1 && $spy->fields === [
            'ref' => 'SYNTHETIC_' . strtoupper($case['request']['requestFingerprint']),
            'stage' => 'SYNTHETIC_' . strtoupper($case['request']['candidateDigest']),
            'text' => 'SYNTHETIC_' . strtoupper($reference),
        ], 'original_provider_only_synthetic');
        f06Denied('unavailable', fn () => $bridge->deliver(1029), 'delivery_once');
        f06Denied('unavailable', fn () => $bridge->prepare($body, 1000), 'prepare_once');
        $cli = f06Cli(f06Json($case['request']));
        f06Assert($cli['code'] === 0 && $cli['stderr'] === '' && $cli['stdout'] === f06Json($case['expected']) . "\n", 'cli_all_recipes_exact');
    }

    $edge = f06Fixture($catalog, 'pricing', str_repeat('_', 96), '--------');
    $bridge = new GroundedConversationBridge($policyBytes, $catalogBytes);
    $bridge->prepare(f06Json($edge['request']), 0);
    f06Assert($bridge->deliver(0) === $edge['expected'], 'opaque_id_boundaries');

    $invalidBodies = ['', '{}', '[]', 'null', 'false', '{', $body . "\n", ' ' . $body,
        "\xEF\xBB\xBF" . $body, $body . $body, str_repeat('x', 16385),
        str_replace('"schema":', '"schema":"duplicate","schema":', $body),
        str_replace('"schema"', '"sch\\u0065ma"', $body),
        str_replace('"answerId":', '"answerId":"pricing","answerId":', $body),
    ];
    foreach (array_keys($request) as $key) {
        $bad = $request; unset($bad[$key]); $invalidBodies[] = f06Json($bad);
    }
    foreach (['text', 'candidate', 'token', 'context', 'scenario', 'provider', 'tenant', 'endpoint', '__proto__'] as $key) {
        $bad = $request; $bad[$key] = 'SYNTHETIC_UNTRUSTED'; $invalidBodies[] = f06Json($bad);
    }
    foreach ([['schema', 'faisca.grounded.request.v2'], ['locale', 'en-US'], ['invocationId', str_repeat('A', 32)],
        ['invocationId', str_repeat('a', 31)], ['candidateDigest', str_repeat('g', 64)], ['requestFingerprint', 123],
        ['conversationId', 'short'], ['conversationId', str_repeat('a', 97)], ['messageId', '../synthetic'],
        ['messageId', 'sintético'], ['messageId', "synthetic\nmessage"]] as [$key, $value]) {
        $bad = $request; $bad[$key] = $value; $invalidBodies[] = f06Json($bad);
    }
    foreach ($invalidBodies as $invalid) {
        $spy = new F06TestProvider();
        $sample = new GroundedConversationBridge($policyBytes, $catalogBytes, $spy);
        f06Denied('invalid_request', fn () => $sample->prepare($invalid, 1000), 'closed_wire');
        f06Assert($spy->calls === 0 && $sample->inspect()['grounding']['deliveries'] === 0, 'wire_denied_before_selection');
    }

    $badBindings = [];
    foreach (['catalogVersion' => 'faisca-grounding-2', 'catalogDigest' => str_repeat('0', 64),
        'answerId' => 'unknown', 'sourcePath' => '../private', 'text' => 'SYNTHETIC_INJECTED'] as $key => $value) {
        $bad = $request; $bad['grounding'][$key] = $value; $badBindings[] = $bad;
    }
    foreach ([[], ['faq.precos'], ['onepage.choice', 'onepage.description'], ['onepage.description', 'onepage.description'],
        ['unknown'], ['onepage.description', 'onepage.choice', 'faq.precos', 'faq.resultados'], null, [1]] as $ids) {
        $bad = $request; $bad['grounding']['passageIds'] = $ids; $badBindings[] = $bad;
    }
    foreach (array_keys($request['grounding']) as $key) {
        $bad = $request; unset($bad['grounding'][$key]); $badBindings[] = $bad;
    }
    foreach ([null, [], 'public_comercial'] as $value) {
        $bad = $request; $bad['grounding'] = $value; $badBindings[] = $bad;
    }
    $bad = $request; $bad['candidateDigest'] = str_repeat('c', 64); $badBindings[] = $bad;
    // A legitimate passage from another subject and its valid answer digest cannot replace a recipe.
    $other = f06Fixture($catalog, 'pricing');
    $bad = $other['request']; $bad['grounding']['answerId'] = 'goal_presence'; $badBindings[] = $bad;
    // Nor can arbitrary prose acquire support just by supplying its newly recomputed hash.
    $unsupported = $fixture['candidate']; $unsupported['text'] = 'Resultado garantido e preço inventado.';
    $bad = $request; $bad['candidateDigest'] = hash('sha256', f06Json($unsupported)); $badBindings[] = $bad;
    $wrongCitations = $fixture['candidate']; $wrongCitations['sources'] = $other['candidate']['sources'];
    $bad = $request; $bad['candidateDigest'] = hash('sha256', f06Json($wrongCitations)); $badBindings[] = $bad;
    foreach ($badBindings as $bad) {
        $spy = new F06TestProvider();
        $sample = new GroundedConversationBridge($policyBytes, $catalogBytes, $spy);
        f06Denied('grounding_denied', fn () => $sample->prepare(f06Json($bad), 1000), 'answer_binding_denied');
        f06Assert($spy->calls === 0 && $sample->inspect()['execution']['runState'] === null, 'binding_before_execution');
    }
    foreach ([-1, 4102444701] as $now) {
        $sample = new GroundedConversationBridge($policyBytes, $catalogBytes);
        f06Denied('grounding_denied', fn () => $sample->prepare($body, $now), 'invalid_clock');
    }
    $mutated = $catalog;
    $mutated['passages'][0]['text'] = 'Uma passagem alterada com digest atualizado.';
    $mutated['passages'][0]['digest'] = hash('sha256', $mutated['passages'][0]['text']);
    foreach (['', $catalogBytes . ' ', str_repeat('x', 32769), f06Json($mutated),
        str_replace('faisca.grounding.catalog.v1', 'faisca.grounding.catalog.v2', $catalogBytes)] as $tampered) {
        f06Denied('grounding_denied', fn () => new PublicPassageCatalog($tampered), 'catalog_normative_pin');
    }

    // A deliberately contradictory finite fact fixture is separate from the pinned catalog.
    $fact = ['factKey' => 'synthetic.pricing', 'factValue' => 'proposal_required'];
    $opposite = ['factKey' => 'synthetic.pricing', 'factValue' => 'fixed_price'];
    f06Assert(PublicPassageCatalog::hasDeclaredConflict([$fact, $opposite]), 'declared_fact_conflict');
    f06Assert(!PublicPassageCatalog::hasDeclaredConflict([$fact, $fact]), 'same_declared_fact');
    f06Assert(!PublicPassageCatalog::hasDeclaredConflict([$fact, ['factKey' => 'synthetic.scope', 'factValue' => 'fixed_price']]), 'distinct_declared_facts');
    f06Denied('grounding_denied', fn () => PublicPassageCatalog::hasDeclaredConflict([['factKey' => 'missing']]), 'malformed_declared_fact');

    foreach (['expired', 'backwards', 'revoked', 'disabled'] as $mode) {
        $sample = new GroundedConversationBridge($policyBytes, $catalogBytes);
        $sample->prepare($body, 1000);
        if ($mode === 'revoked') $sample->revokePendingContext();
        if ($mode === 'disabled') $sample->disablePendingSource();
        $now = $mode === 'expired' ? 1030 : ($mode === 'backwards' ? 999 : 1001);
        f06Denied('grounding_denied', fn () => $sample->deliver($now), 'delivery_recheck');
        $state = $sample->inspect();
        f06Assert($state['phase'] === 'failed' && $state['grounding']['phase'] === 'closed'
            && $state['grounding']['deliveries'] === 1 && $state['execution']['runState'] === 'FALHOU'
            && $state['execution']['suggestions'] === 1 && $state['execution']['gatewayAttempts'] === 0, 'failed_delivery_terminal');
        f06Denied('unavailable', fn () => $sample->deliver(1000), 'no_retry_after_denial');
    }
    foreach (['revoke', 'disable_source'] as $mode) {
        $spy = new F06TestProvider($mode);
        $sample = new GroundedConversationBridge($policyBytes, $catalogBytes, $spy);
        $spy->bridge = $sample;
        $sample->prepare($body, 1000);
        f06Denied('grounding_denied', fn () => $sample->deliver(1000), 'callback_revocation');
        f06Assert($spy->calls === 1 && $sample->inspect()['execution']['runState'] === 'FALHOU', 'callback_revocation_terminal');
    }
    foreach (['throw', 'mutate_run', 'wrong_source', 'wrong_text', 'extra'] as $mode) {
        $spy = new F06TestProvider($mode);
        $sample = new GroundedConversationBridge($policyBytes, $catalogBytes, $spy);
        f06Denied('invalid_suggestion', fn () => $sample->prepare($body, 1000), 'unchanged_provider_guard');
        f06Assert($spy->calls === 1 && $sample->inspect()['execution']['runState'] === 'FALHOU'
            && $sample->inspect()['execution']['gatewayAttempts'] === 0, 'provider_failure_terminal');
    }
    $selected = new PublicPassageCatalog($catalogBytes);
    $selected->select('goal_presence', $request['grounding']['passageIds'], 1000);
    $copy = $selected->deliver(1000);
    $copy['passages'][0]['text'] = 'Caller-side mutation cannot rewrite the catalog.';
    f06Assert($selected->deliver(1001)['passages'][0]['text'] !== $copy['passages'][0]['text'], 'selection_is_not_mutable_alias');
    $selected->close();
    f06Denied('grounding_denied', fn () => $selected->deliver(1001), 'closed_catalog');

    foreach (['IMAGINE_FAISCA_GROUNDED_LAB', 'IMAGINE_FAISCA_AGENTS_LAB'] as $flag) {
        f06CliError($body, 'lab_disabled', [$flag => null]);
        f06CliError($body, 'lab_disabled', [$flag => '0']);
    }
    f06CliError($body, 'invalid_request', [], ['unexpected']);
    foreach (['{', $body . "\n", str_repeat('x', 16385),
        str_replace('"schema":', '"schema":"duplicate","schema":', $body)] as $invalid) f06CliError($invalid, 'invalid_request');
    $bad = $request; $bad['token'] = 'SYNTHETIC_NEVER_FORWARDED';
    f06CliError(f06Json($bad), 'invalid_request');
    foreach (array_slice($badBindings, -4) as $bad) f06CliError(f06Json($bad), 'grounding_denied');
    f06CliError($body, 'unavailable', ['IMAGINE_FAISCA_AGENTS_SCENARIO' => 'unavailable']);
    f06CliError($body, 'grounding_denied', ['IMAGINE_FAISCA_AGENTS_SCENARIO' => 'revoke_before_delivery']);
    f06CliError($body, 'grounding_denied', ['IMAGINE_FAISCA_AGENTS_SCENARIO' => 'source_disabled_before_delivery']);
    f06CliError($body, 'unavailable', ['IMAGINE_FAISCA_AGENTS_SCENARIO' => 'external']);
    foreach (['-1', '0200', '2001', '1.1'] as $delay) f06CliError($body, 'unavailable', ['IMAGINE_FAISCA_AGENTS_DELAY_MS' => $delay]);
    $delayed = f06Cli($body, ['IMAGINE_FAISCA_AGENTS_SCENARIO' => 'delay', 'IMAGINE_FAISCA_AGENTS_DELAY_MS' => '120']);
    f06Assert($delayed['code'] === 0 && $delayed['stderr'] === '' && $delayed['elapsed'] >= 0.1
        && $delayed['stdout'] === f06Json($fixture['expected']) . "\n", 'cli_delay_revalidated');
    $malformed = f06Cli($body, ['IMAGINE_FAISCA_AGENTS_SCENARIO' => 'malformed']);
    f06Assert($malformed['code'] === 0 && $malformed['stderr'] === ''
        && $malformed['stdout'] === '{"schema":"faisca.grounded.result.v1"' . "\n", 'cli_malformed_after_real_execution');
    $lost = f06Cli($body, ['IMAGINE_FAISCA_AGENTS_SCENARIO' => 'exit_after_dispatch']);
    f06Assert($lost['code'] === 3 && $lost['stdout'] === '' && $lost['stderr'] === '', 'cli_exit_after_real_dispatch');

    echo 'FAISCA_GROUNDED_BRIDGE_PASS checks=' . $f06Checks . ' cli_cases=' . $f06CliCases
        . " recipes=11 passages=21 php_processes_executed=true operational_effects=false real_model=false\n";
} catch (Throwable $error) {
    $label = $error instanceof RuntimeException && preg_match('/\ACHECK_[a-z_]+\z/D', $error->getMessage()) === 1
        ? $error->getMessage() : 'CHECK_unexpected_failure';
    fwrite(STDERR, 'FAISCA_GROUNDED_BRIDGE_FAIL ' . $label . "\n");
    exit(1);
}
