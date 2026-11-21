<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/src/Integration/OnepageCopyProcessWorker.php';
use Imagine\Agents\Integration\OnepageCopyProcessWorker as Worker;

$checks = 0;
$ok = static function (bool $condition, string $name) use (&$checks): void {
    if (!$condition) throw new RuntimeException('OnePage worker test: '.$name);
    ++$checks;
};
$deny = static function (string $json, string $name) use ($ok): void {
    try {
        Worker::execute($json);
    } catch (RuntimeException $e) {
        $ok($e->getMessage() === 'ONEPAGE_WORKER_DENIED' &&
            $e->getPrevious() === null, $name);
        return;
    }
    throw new RuntimeException('Unexpected worker acceptance: '.$name);
};
$json = static fn(array $v): string => json_encode($v,
    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$task = [
    'schemaVersion'=>'sites.onepage-agent-task.v1',
    'taskRef'=>'task-'.str_repeat('a',32),
    'correlationRef'=>'corr-'.str_repeat('b',32),
    'agentVersion'=>Worker::VERSION,
    'briefRevision'=>1,
    'briefSha256'=>str_repeat('c',64),
    'copyHints'=>[
        'subject'=>'Serviços demonstrativos de manutenção de bicicletas',
        'audience'=>'Público inteiramente fictício',
        'tone'=>'Clareza e acessibilidade',
    ],
];
$response = Worker::execute($json($task));
$decoded = json_decode($response, true, 8, JSON_THROW_ON_ERROR);
$ok(array_keys($decoded) === [
    'schemaVersion','taskRef','correlationRef','agentVersion',
    'briefRevision','briefSha256','copy',
], 'closed seven-field response');
$ok($decoded['taskRef'] === $task['taskRef'] &&
    $decoded['correlationRef'] === $task['correlationRef'] &&
    $decoded['briefSha256'] === $task['briefSha256'], 'exact bindings');
$ok($decoded['agentVersion'] === Worker::VERSION, 'pinned agent version');
$ok(array_keys($decoded['copy']) === [
    'schemaVersion','headline','description','callToAction','benefits',
], 'closed Sites v1 copy');
$ok($decoded['copy']['schemaVersion'] === 'sites.ai-copy.v1', 'not v4');
$ok(strpos($response, '<') === false, 'no HTML');
$ok(Worker::execute($json($task)) === $response, 'deterministic response');
$changes = [
    ['schemaVersion','sites.onepage-agent-task.v2'],
    ['agentVersion','imagine.onepage-agent.real.v1'],
    ['taskRef','task-other'],
    ['correlationRef','corr-other'],
    ['briefRevision',-1],
    ['briefSha256',str_repeat('z',64)],
    ['copyHints',['subject'=>'customer private content','audience'=>'x','tone'=>'x']],
];
foreach ($changes as [$key,$value]) {
    $wrong=$task; $wrong[$key]=$value;
    $deny($json($wrong), 'reject mutated '.$key);
}
$wrong=$task; $wrong['tenantRef']='synthetic-tenant-a';
$deny($json($wrong),'no tenant authority passed to worker');
$wrong=$task; $wrong['copyHints']['extra']='publication-permission';
$deny($json($wrong),'reject surplus hint fields');
$deny(substr($json($task),0,-1).',"taskRef":"fake"}','reject duplicate key');
$deny(' '.$json($task),'reject noncanonical whitespace');
$deny(str_repeat(' ',2049),'reject payload over 2048 bytes');
$deny('{"schemaVersion":"sites.onepage-agent-task.v1","taskRef":NaN}', 'reject invalid JSON');
echo 'ONEPAGE PROCESS WORKER PASS: '.$checks.' synthetic checks; NO-GO; NO OPENAI REQUEST.'."\n";
