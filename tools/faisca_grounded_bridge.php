<?php
declare(strict_types=1);

/** R06 single-invocation CLI. Public pinned passages; deterministic R05 execution. */
ini_set('display_errors', '0');
ini_set('log_errors', '0');
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new RuntimeException('unavailable');
});

function faiscaGroundedError(string $code): never
{
    if (!in_array($code, ['invalid_request', 'context_denied', 'source_denied',
        'invalid_suggestion', 'lab_disabled', 'grounding_denied', 'unavailable'], true)) $code = 'unavailable';
    echo json_encode(['schema' => 'faisca.grounded.error.v1', 'code' => $code], JSON_THROW_ON_ERROR) . "\n";
    exit(2);
}

try {
    if (PHP_SAPI !== 'cli' || $argc !== 1) faiscaGroundedError('invalid_request');
    if (getenv('IMAGINE_FAISCA_GROUNDED_LAB') !== '1'
        || getenv('IMAGINE_FAISCA_AGENTS_LAB') !== '1') faiscaGroundedError('lab_disabled');
    $scenario = getenv('IMAGINE_FAISCA_AGENTS_SCENARIO');
    if ($scenario === false) $scenario = 'success';
    if (!in_array($scenario, ['success', 'delay', 'unavailable', 'malformed', 'exit_after_dispatch',
        'revoke_before_delivery', 'source_disabled_before_delivery'], true)) faiscaGroundedError('unavailable');
    $delay = getenv('IMAGINE_FAISCA_AGENTS_DELAY_MS');
    if ($delay === false) $delay = $scenario === 'delay' ? '800' : '0';
    if (strlen($delay) > 4 || preg_match('/\A(?:0|[1-9][0-9]{0,3})\z/D', $delay) !== 1
        || (int) $delay > 2000) faiscaGroundedError('unavailable');

    // Fixed imports only. Neither request nor environment can choose source paths.
    $root = dirname(__DIR__);
    $sourceFiles = [
        'src/Domain/MockError.php',
        'src/Domain/TenantContext.php',
        'src/Contracts/AiSuggestionProvider.php',
        'src/Contracts/CrmEffectGateway.php',
        'src/Domain/Run.php',
        'src/Domain/Step.php',
        'src/MockCatalog/MockAgentVersionSnapshot.php',
        'src/MockKnowledge/MockKnowledgeSource.php',
        'src/MockKnowledge/MockKnowledgeCatalog.php',
        'src/MockKnowledge/MockKnowledgeTrace.php',
        'src/MockKnowledge/MockRetrievalGrant.php',
        'src/MockKnowledge/MockKnowledgeRetriever.php',
        'src/MockKnowledge/MockContextEnvelope.php',
        'src/Mocks/MockAiSuggestionProvider.php',
        'src/MockOrchestration/SupervisedCoordinator.php',
        'src/Integration/Faisca/DenyOnlyEffectGateway.php',
        'src/Integration/Faisca/PublicConversationBridge.php',
        'src/Integration/Faisca/PublicPassageCatalog.php',
        'src/Integration/Faisca/GroundedConversationBridge.php',
    ];
    foreach ($sourceFiles as $sourceFile) require_once $root . '/' . $sourceFile;
    $policy = file_get_contents($root . '/contracts/faisca/public-context-v1.json', false, null, 0, 4097);
    $catalog = file_get_contents($root . '/contracts/faisca/public-passages-v1.json', false, null, 0,
        ImagineAI\Integration\Faisca\PublicPassageCatalog::MAX_CATALOG_BYTES + 1);
    if (!is_string($policy) || !is_string($catalog)) faiscaGroundedError('unavailable');
    $bridge = new ImagineAI\Integration\Faisca\GroundedConversationBridge($policy, $catalog);
    $body = stream_get_contents(STDIN, ImagineAI\Integration\Faisca\GroundedConversationBridge::MAX_REQUEST_BYTES + 1);
    if (!is_string($body)) faiscaGroundedError('invalid_request');
    if ($scenario === 'unavailable') faiscaGroundedError('unavailable');
    $bridge->prepare($body, time());
    // Fault fixtures below follow actual selection and the original PHP suggestion.
    if ($scenario === 'exit_after_dispatch') exit(3);
    if ($scenario === 'delay' && (int) $delay > 0) usleep((int) $delay * 1000);
    if ($scenario === 'revoke_before_delivery') $bridge->revokePendingContext();
    if ($scenario === 'source_disabled_before_delivery') $bridge->disablePendingSource();
    $result = $bridge->deliver(time());
    if ($scenario === 'malformed') {
        echo '{"schema":"faisca.grounded.result.v1"' . "\n";
        exit(0);
    }
    echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $error) {
    faiscaGroundedError($error instanceof DomainException ? $error->getMessage() : 'unavailable');
}
