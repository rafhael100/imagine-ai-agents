<?php
declare(strict_types=1);

namespace Imagine\Agents\Integration;

use RuntimeException;
use stdClass;
use Throwable;

/**
 * One bounded fictional OnePage request via a separate process.
 * No provider, HTTP, keys, queue, consent or publication powers.
 */
final class OnepageCopyProcessWorker
{
    public const VERSION = 'imagine.onepage-agent.mock.v1';

    public static function execute(string $taskJson): string
    {
        try {
            if ($taskJson === '' || strlen($taskJson) > 2048 ||
                !mb_check_encoding($taskJson, 'UTF-8')) throw new RuntimeException();
            $task = json_decode($taskJson, false, 8, JSON_THROW_ON_ERROR);
            if (!$task instanceof stdClass ||
                json_encode($task, JSON_THROW_ON_ERROR |
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !== $taskJson) {
                throw new RuntimeException();
            }
            self::keys($task, [
                'schemaVersion','taskRef','correlationRef','agentVersion',
                'briefRevision','briefSha256','copyHints',
            ]);
            if ($task->schemaVersion !== 'sites.onepage-agent-task.v1' ||
                $task->agentVersion !== self::VERSION ||
                !is_string($task->taskRef) ||
                preg_match('/\Atask-[a-f0-9]{32}\z/D', $task->taskRef) !== 1 ||
                !is_string($task->correlationRef) ||
                preg_match('/\Acorr-[a-f0-9]{32}\z/D', $task->correlationRef) !== 1 ||
                !is_int($task->briefRevision) ||
                $task->briefRevision < 1 || $task->briefRevision > 2147483647 ||
                !is_string($task->briefSha256) ||
                preg_match('/\A[a-f0-9]{64}\z/D', $task->briefSha256) !== 1) {
                throw new RuntimeException();
            }
            self::keys($task->copyHints, ['subject','audience','tone']);
            if ($task->copyHints->subject !== 'Serviços demonstrativos de manutenção de bicicletas' ||
                $task->copyHints->audience !== 'Público inteiramente fictício' ||
                $task->copyHints->tone !== 'Clareza e acessibilidade') {
                throw new RuntimeException();
            }
            $response = [
                'schemaVersion' => 'sites.onepage-agent-response.v1',
                'taskRef' => $task->taskRef,
                'correlationRef' => $task->correlationRef,
                'agentVersion' => self::VERSION,
                'briefRevision' => $task->briefRevision,
                'briefSha256' => $task->briefSha256,
                'copy' => [
                    'schemaVersion' => 'sites.ai-copy.v1',
                    'headline' => 'Cuidados fictícios com sua bicicleta',
                    'description' => 'Uma demonstração clara de serviços inventados, sem vendas ou coleta de dados.',
                    'callToAction' => 'Conheça o exemplo',
                    'benefits' => ['Revisão demonstrativa', 'Orientação simulada', 'Atendimento fictício'],
                ],
            ];
            $output = json_encode($response, JSON_THROW_ON_ERROR |
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($output) || strlen($output) > 8192) throw new RuntimeException();
            return $output;
        } catch (Throwable) {
            throw new RuntimeException('ONEPAGE_WORKER_DENIED');
        }
    }

    private static function keys(mixed $object, array $keys): void
    {
        if (!$object instanceof stdClass ||
            count(get_object_vars($object)) !== count($keys) ||
            array_diff(array_keys(get_object_vars($object)), $keys) ||
            array_diff($keys, array_keys(get_object_vars($object)))) {
            throw new RuntimeException();
        }
    }
}
