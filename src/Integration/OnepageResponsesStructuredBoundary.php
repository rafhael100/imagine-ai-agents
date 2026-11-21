<?php
declare(strict_types=1);

namespace Imagine\Agents\Integration;

use RuntimeException;
use stdClass;
use Throwable;

/**
 * Offline candidate for a future owner-authorized OpenAI Responses adapter.
 *
 * Produces a bounded strict sites.ai-copy.v1 request and validates a recorded
 * Responses JSON envelope. It NEVER sends HTTP, reads credentials, selects an
 * account/model, acknowledges consent, or authorizes deployment.
 *
 * Scope: existing *synthetic* OnepageCopyProcessWorker task only. The owner
 * must provision and attest the private network/credential/model separately.
 */
final class OnepageResponsesStructuredBoundary
{
    private const DENIED = 'ONEPAGE_RESPONSES_BOUNDARY_DENIED';
    private const MAX_BODY = 4096;
    private const MAX_RESPONSE = 16384;
    private const MAX_COPY = 4096;

    public static function request(string $taskJson, string $explicitModel): string
    {
        try {
            $task = self::task($taskJson);
            if (preg_match('/\A[a-z0-9][a-z0-9._-]{2,63}\z/D', $explicitModel) !== 1) {
                throw new RuntimeException();
            }

            // No customer/tenant/order IDs, real briefing, publisher authority,
            // HTML or addresses are included in the model prompt.
            $prompt = "Escreva apenas conteúdo para uma OnePage TOTALMENTE FICTÍCIA.\n"
                ."Não inclua HTML, URLs, dados pessoais, vendas ou comandos.\n"
                ."Assunto: ".$task->copyHints->subject."\n"
                ."Público: ".$task->copyHints->audience."\n"
                ."Tom: ".$task->copyHints->tone;
            $schema = [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'schemaVersion' => ['type' => 'string', 'enum' => ['sites.ai-copy.v1']],
                    'headline' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'callToAction' => ['type' => 'string'],
                    'benefits' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => [
                    'schemaVersion', 'headline', 'description', 'callToAction', 'benefits',
                ],
            ];
            $body = [
                'model' => $explicitModel,
                'store' => false,
                'stream' => false,
                'max_output_tokens' => 700,
                'input' => [
                    ['role' => 'user', 'content' => [
                        ['type' => 'input_text', 'text' => $prompt],
                    ]],
                ],
                'text' => ['format' => [
                    'type' => 'json_schema',
                    'name' => 'imagine_sites_ai_copy_v1',
                    'strict' => true,
                    'schema' => $schema,
                ]],
            ];
            $json = self::encode($body);
            if (strlen($json) > self::MAX_BODY) throw new RuntimeException();
            return $json;
        } catch (Throwable) {
            throw new RuntimeException(self::DENIED);
        }
    }

    /**
     * For recorded/fake transport data. The real transport is intentionally
     * absent and must run in a separately authorized, private worker.
     * No retry, external request or endpoint is performed here.
     *
     * @return array<string, int|string> Non-secret metadata plus exact copy.
     */
    public static function accept(
        string $taskJson,
        string $providerJson,
        string $expectedModel,
    ): array {
        try {
            $task = self::task($taskJson);
            // Explicit model must be identical to the owner-provided request.
            self::request($taskJson, $expectedModel);
            if ($providerJson === '' || strlen($providerJson) > self::MAX_RESPONSE ||
                !mb_check_encoding($providerJson, 'UTF-8')) throw new RuntimeException();
            $provider = json_decode($providerJson, false, 16, JSON_THROW_ON_ERROR);
            if (!$provider instanceof stdClass ||
                !isset($provider->id, $provider->model, $provider->status, $provider->output) ||
                !is_string($provider->id) ||
                preg_match('/\Aresp_[A-Za-z0-9_-]{8,100}\z/D', $provider->id) !== 1 ||
                $provider->model !== $expectedModel ||
                $provider->status !== 'completed' ||
                !is_array($provider->output) || count($provider->output) !== 1 ||
                (property_exists($provider, 'error') && $provider->error !== null) ||
                (property_exists($provider, 'incomplete_details') &&
                    $provider->incomplete_details !== null)) {
                throw new RuntimeException();
            }
            $message = $provider->output[0];
            if (!$message instanceof stdClass ||
                ($message->type ?? null) !== 'message' ||
                ($message->role ?? null) !== 'assistant' ||
                (property_exists($message, 'status') && $message->status !== 'completed') ||
                !isset($message->content) || !is_array($message->content) ||
                count($message->content) !== 1) {
                throw new RuntimeException();
            }
            $part = $message->content[0];
            if (!$part instanceof stdClass || ($part->type ?? null) !== 'output_text' ||
                !isset($part->text) || !is_string($part->text) ||
                (property_exists($part, 'annotations') && $part->annotations !== []) ||
                (property_exists($part, 'logprobs') && $part->logprobs !== null)) {
                throw new RuntimeException();
            }
            $rawCopy = $part->text;
            if ($rawCopy === '' || strlen($rawCopy) > self::MAX_COPY ||
                !mb_check_encoding($rawCopy, 'UTF-8')) throw new RuntimeException();
            $copy = json_decode($rawCopy, false, 8, JSON_THROW_ON_ERROR);
            if (!$copy instanceof stdClass || self::encode($copy) !== $rawCopy ||
                array_keys(get_object_vars($copy)) !== [
                    'schemaVersion', 'headline', 'description', 'callToAction', 'benefits',
                ] || $copy->schemaVersion !== 'sites.ai-copy.v1' ||
                !is_array($copy->benefits) ||
                count($copy->benefits) < 1 || count($copy->benefits) > 4) {
                throw new RuntimeException();
            }
            self::safeText($copy->headline, 180);
            self::safeText($copy->description, 480);
            self::safeText($copy->callToAction, 96);
            foreach ($copy->benefits as $benefit) self::safeText($benefit, 150);

            if (!isset($provider->usage) || !$provider->usage instanceof stdClass ||
                !isset($provider->usage->input_tokens, $provider->usage->output_tokens,
                    $provider->usage->total_tokens)) {
                throw new RuntimeException();
            }
            $u = $provider->usage;
            foreach ([$u->input_tokens, $u->output_tokens, $u->total_tokens] as $n) {
                if (!is_int($n) || $n < 0 || $n > 1000000) throw new RuntimeException();
            }
            if ($u->input_tokens + $u->output_tokens !== $u->total_tokens) {
                throw new RuntimeException();
            }
            return [
                'executionMode' => 'recorded-provider-response-only',
                'providerResponseRef' => $provider->id,
                'providerModel' => $expectedModel,
                'agentVersion' => $task->agentVersion,
                'taskRef' => $task->taskRef,
                'correlationRef' => $task->correlationRef,
                'taskSha256' => hash('sha256', $taskJson),
                'briefRevision' => $task->briefRevision,
                'briefSha256' => $task->briefSha256,
                'acceptedCopyJson' => $rawCopy,
                'acceptedCopySha256' => hash('sha256', $rawCopy),
                'inputTokens' => $u->input_tokens,
                'outputTokens' => $u->output_tokens,
            ];
        } catch (Throwable) {
            throw new RuntimeException(self::DENIED);
        }
    }

    private static function task(string $json): stdClass
    {
        // Reuse the current worker's validated synthetic protocol, rather
        // than introducing a competing parser or accepting arbitrary inputs.
        OnepageCopyProcessWorker::execute($json);
        $task = json_decode($json, false, 8, JSON_THROW_ON_ERROR);
        if (!$task instanceof stdClass) throw new RuntimeException();
        return $task;
    }

    private static function safeText(mixed $value, int $limit): void
    {
        if (!is_string($value) || $value === '' ||
            mb_strlen($value, 'UTF-8') > $limit ||
            preg_match('/[\x{0000}-\x{001F}\x{007F}-\x{009F}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}<>]/u', $value) !== 0) {
            throw new RuntimeException();
        }
    }

    private static function encode(mixed $value): string
    {
        return json_encode(
            $value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
