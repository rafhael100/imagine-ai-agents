<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/src/Integration/OnepageCopyProcessWorker.php';
require_once dirname(__DIR__).'/src/Integration/OnepageResponsesStructuredBoundary.php';

use Imagine\Agents\Integration\OnepageCopyProcessWorker as Worker;
use Imagine\Agents\Integration\OnepageResponsesStructuredBoundary as Boundary;

$checks = 0;
$ok = static function (bool $condition, string $name) use (&$checks): void {
    if (!$condition) throw new RuntimeException('OnePage Responses candidate: '.$name);
    ++$checks;
};
$deny = static function (callable $call, string $name) use ($ok): void {
    try {
        $call();
    } catch (RuntimeException $e) {
        $ok($e->getMessage() === 'ONEPAGE_RESPONSES_BOUNDARY_DENIED' &&
            $e->getPrevious() === null, $name);
        return;
    }
    throw new RuntimeException('Candidate incorrectly accepted: '.$name);
};
$json = static fn(mixed $data): string => json_encode(
    $data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
);
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
$taskJson = $json($task);
$model = 'model-fixture-only'; // An inert string, NEVER an authorized production model.
$request = Boundary::request($taskJson, $model);
$req = json_decode($request, true, 16, JSON_THROW_ON_ERROR);
$ok(array_keys($req) === ['model','store','stream','max_output_tokens','input','text'],
    'closed request with no tools, web search, keys or publication commands');
$ok($req['store'] === false && $req['stream'] === false &&
    $req['max_output_tokens'] === 700, 'no storage/stream and bounded output');
$fmt = $req['text']['format'];
$ok($fmt['type'] === 'json_schema' && $fmt['strict'] === true &&
    $fmt['name'] === 'imagine_sites_ai_copy_v1', 'Responses strict structured output');
$schema = $fmt['schema'];
$ok($schema['additionalProperties'] === false &&
    array_keys($schema['properties']) === [
        'schemaVersion', 'headline', 'description', 'callToAction', 'benefits',
    ] && $schema['required'] === array_keys($schema['properties']),
    'only exact Sites v1 fields: no v4, no HTML output');
$ok(str_contains($request, 'TOTALMENTE FICTÍCIA') &&
    !str_contains($request, 'api.openai.com') &&
    !str_contains($request, $task['taskRef']) &&
    !str_contains($request, $task['briefSha256']),
    'copy-only fake prompt: no task identities, endpoint or private briefing');
$ok(strlen($request) < 4096 && $json($req) === $request,
    'bounded canonical API request, deterministic on identical fixture');
$ok(Boundary::request($taskJson,$model) === $request, 'repeat is pure, no API call');

$copy = [
    'schemaVersion'=>'sites.ai-copy.v1',
    'headline'=>'Bicicletas fictícias em boas mãos',
    'description'=>'Uma demonstração de manutenção sem atendimento ou comércio real.',
    'callToAction'=>'Veja o exemplo',
    'benefits'=>['Revisão fictícia','Apoio demonstrativo'],
];
$response = [
    'id'=>'resp_'.str_repeat('d',24),
    'model'=>$model,
    'status'=>'completed',
    'error'=>null,
    'incomplete_details'=>null,
    'output'=>[[
        'type'=>'message',
        'role'=>'assistant',
        'status'=>'completed',
        'content'=>[[
            'type'=>'output_text',
            'text'=>$json($copy),
            'annotations'=>[],
        ]],
    ]],
    'usage'=>['input_tokens'=>112,'output_tokens'=>84,'total_tokens'=>196],
];
$accepted = Boundary::accept($taskJson,$json($response),$model);
$ok($accepted['executionMode'] === 'recorded-provider-response-only',
    'candidate never claims a real provider call');
$ok($accepted['providerResponseRef'] === $response['id'] &&
    $accepted['providerModel'] === $model,
    'non-secret response ref and explicit fixture model');
$ok($accepted['briefRevision'] === 1 &&
    $accepted['briefSha256'] === $task['briefSha256'] &&
    $accepted['taskSha256'] === hash('sha256',$taskJson),
    'provenance remains tied to exact synthetic task and briefing revision');
$ok($accepted['acceptedCopyJson'] === $json($copy) &&
    $accepted['acceptedCopySha256'] === hash('sha256',$json($copy)),
    'accepted v1 copy bytes and digest preserved');
$ok($accepted['inputTokens'] === 112 && $accepted['outputTokens'] === 84,
    'usage recorded from fake response, never inferred');
$ok(!isset($accepted['canPublish'],$accepted['clientAccepted']),
    'provider has no approval/publication authority');

$mutTask = $task; $mutTask['copyHints']['subject']='Dados reais de comprador';
$deny(static fn()=>Boundary::request($json($mutTask),$model),
    'deny real customer task without access to provider');
$deny(static fn()=>Boundary::request($taskJson,''),
    'no implicit model selection');
$deny(static fn()=>Boundary::request($taskJson,'model with space'),
    'reject invalid untrusted model argument');
$deny(static fn()=>Boundary::accept($taskJson,'{',$model),
    'reject malformed API envelope');
$deny(static fn()=>Boundary::accept($taskJson,$json($response),'different-model'),
    'reject wrong model even if envelope is otherwise successful');
$mut=$response; $mut['status']='incomplete';
$deny(static fn()=>Boundary::accept($taskJson,$json($mut),$model),
    'reject incomplete response');
$mut=$response; $mut['error']=['message'=>'fake'];
$deny(static fn()=>Boundary::accept($taskJson,$json($mut),$model),
    'reject provider errors');
$mut=$response; $mut['output'][]=$mut['output'][0];
$deny(static fn()=>Boundary::accept($taskJson,$json($mut),$model),
    'reject multiple messages');
$mut=$response; $mut['output'][0]['content'][0]=[
    'type'=>'refusal', 'refusal'=>'no content',
];
$deny(static fn()=>Boundary::accept($taskJson,$json($mut),$model),
    'reject model refusal instead of content');
$mut=$response; $mut['output'][0]['content'][0]['annotations']=[['type'=>'marker']];
$deny(static fn()=>Boundary::accept($taskJson,$json($mut),$model),
    'reject extra annotations');
$mut=$response; $mut['usage']['total_tokens']=195;
$deny(static fn()=>Boundary::accept($taskJson,$json($mut),$model),
    'reject inconsistent provider usage');
$mut=$response; $mut['usage']['input_tokens']='112';
$deny(static fn()=>Boundary::accept($taskJson,$json($mut),$model),
    'reject usage strings');
$mut=$response; $mut['id']='nonsafe-identifier';
$deny(static fn()=>Boundary::accept($taskJson,$json($mut),$model),
    'reject invalid provider reference');

$badCopy=$copy; $badCopy['published']=true;
$mut=$response; $mut['output'][0]['content'][0]['text']=$json($badCopy);
$deny(static fn()=>Boundary::accept($taskJson,$json($mut),$model),
    'reject model-invented publication flag');
$badCopy=$copy; $badCopy['schemaVersion']='sites.ai-copy.v4';
$mut=$response; $mut['output'][0]['content'][0]['text']=$json($badCopy);
$deny(static fn()=>Boundary::accept($taskJson,$json($mut),$model),
    'reject historical v4');
$badCopy=$copy; $badCopy['headline']='<script>alert(1)</script>';
$mut=$response; $mut['output'][0]['content'][0]['text']=$json($badCopy);
$deny(static fn()=>Boundary::accept($taskJson,$json($mut),$model),
    'reject HTML from model');
$badCopy=$copy; $badCopy['headline']="Compra\u{202E}hoje";
$mut=$response; $mut['output'][0]['content'][0]['text']=$json($badCopy);
$deny(static fn()=>Boundary::accept($taskJson,$json($mut),$model),
    'reject bidi controls');
$badCopy=$copy; $badCopy['benefits']=['a','b','c','d','e'];
$mut=$response; $mut['output'][0]['content'][0]['text']=$json($badCopy);
$deny(static fn()=>Boundary::accept($taskJson,$json($mut),$model),
    'reject oversized benefits list');
$mut=$response; $mut['output'][0]['content'][0]['text']='{"schemaVersion":"sites.ai-copy.v1","headline":"a","headline":"b"}';
$deny(static fn()=>Boundary::accept($taskJson,$json($mut),$model),
    'reject ambiguous duplicate-key JSON');
$mut=$response; $mut['output'][0]['content'][0]['text']=' '.$json($copy);
$deny(static fn()=>Boundary::accept($taskJson,$json($mut),$model),
    'reject noncanonical JSON text');
$mut=$response; $mut['output'][0]['content'][0]['text']=str_repeat('x',4097);
$deny(static fn()=>Boundary::accept($taskJson,$json($mut),$model),
    'reject oversized model output');
$mut=$response; $mut['output'][0]['content'][0]['text']=$json($copy);
$mut['output'][0]['content'][]=['type'=>'output_text','text'=>$json($copy)];
$deny(static fn()=>Boundary::accept($taskJson,$json($mut),$model),
    'reject multi-part model copy');

echo 'ONEPAGE RESPONSES STRUCTURED PASS: '.$checks.
    ' offline assertions; RECORDED FIXTURE ONLY; NO API CALL; NO-GO.'."\n";
