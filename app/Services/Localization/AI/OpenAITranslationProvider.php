<?php

namespace App\Services\Localization\AI;

use App\Services\Localization\TranslationSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI Chat Completions with Structured Outputs (json_schema, strict).
 * The key comes from TranslationSettings and is never logged or echoed back.
 */
class OpenAITranslationProvider implements AITranslationProvider
{
    private const BASE_URL = 'https://api.openai.com/v1';

    public function __construct(private TranslationSettings $settings)
    {
    }

    public function name(): string
    {
        return 'openai';
    }

    public function complete(string $system, string $user, array $schema, string $model): array
    {
        $response = $this->send(fn(PendingRequest $http) => $http->post(self::BASE_URL . '/chat/completions', [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'translations',
                    'strict' => true,
                    'schema' => $schema,
                ],
            ],
        ]));

        $body = $response->json();
        $choice = $body['choices'][0] ?? null;

        if (($choice['finish_reason'] ?? null) === 'length') {
            throw new AITranslationException(AITranslationException::INVALID_RESPONSE, 'The response was cut off (token limit). Use a smaller batch size.');
        }

        if (!empty($choice['message']['refusal'])) {
            throw new AITranslationException(AITranslationException::INVALID_RESPONSE, 'The model refused the request.');
        }

        $content = $choice['message']['content'] ?? null;
        $data = is_string($content) ? json_decode($content, true) : null;

        if (!is_array($data)) {
            throw new AITranslationException(AITranslationException::INVALID_RESPONSE, 'The model did not return valid JSON.');
        }

        return [
            'data' => $data,
            'prompt_tokens' => (int)($body['usage']['prompt_tokens'] ?? 0),
            'completion_tokens' => (int)($body['usage']['completion_tokens'] ?? 0),
        ];
    }

    public function testConnection(): array
    {
        try {
            $response = $this->send(fn(PendingRequest $http) => $http->timeout(20)->get(self::BASE_URL . '/models'));
        } catch (AITranslationException $e) {
            return ['ok' => false, 'models' => [], 'message' => $e->getMessage(), 'type' => $e->type];
        }

        $models = collect($response->json('data', []))
            ->pluck('id')
            ->filter(fn($id) => is_string($id) and preg_match('/^(gpt|o\d|chatgpt)/i', $id)
                and !preg_match('/(audio|realtime|transcribe|tts|image|search|embedding|instruct|codex)/i', $id))
            ->sort()
            ->values()
            ->all();

        return ['ok' => true, 'models' => $models, 'message' => 'Connection successful.', 'type' => null];
    }

    private function send(callable $call): Response
    {
        $key = $this->settings->apiKey();

        if (empty($key)) {
            throw new AITranslationException(AITranslationException::NOT_CONFIGURED, 'No OpenAI API key is saved.');
        }

        try {
            /** @var Response $response */
            $response = $call(
                Http::withToken($key)
                    ->acceptJson()
                    ->timeout((int)$this->settings->get('timeout'))
                    ->connectTimeout(15)
            );
        } catch (ConnectionException $e) {
            $timedOut = str_contains(strtolower($e->getMessage()), 'timed out') || str_contains($e->getMessage(), 'cURL error 28');

            throw new AITranslationException(
                $timedOut ? AITranslationException::TIMEOUT : AITranslationException::NETWORK,
                $timedOut ? 'The request to OpenAI timed out.' : 'Could not reach OpenAI (network error).'
            );
        }

        if ($response->successful()) {
            return $response;
        }

        $status = $response->status();
        $message = $this->clean((string)$response->json('error.message', 'HTTP ' . $status));

        if ($status === 401 or $status === 403) {
            throw new AITranslationException(AITranslationException::AUTH, 'Invalid or unauthorized API key. ' . $message);
        }

        if ($status === 429) {
            $retryAfter = (int)($response->header('retry-after') ?: 0);
            $quota = str_contains(strtolower($message), 'quota');

            // Out of credit behaves like a bad key: retrying won't help.
            throw new AITranslationException(
                $quota ? AITranslationException::AUTH : AITranslationException::RATE_LIMIT,
                ($quota ? 'The OpenAI account has no remaining quota. ' : 'Rate limit reached. ') . $message,
                $retryAfter > 0 ? min($retryAfter, 300) : null
            );
        }

        if ($status >= 500) {
            throw new AITranslationException(AITranslationException::SERVER, 'OpenAI server error (' . $status . ').');
        }

        throw new AITranslationException(AITranslationException::REQUEST, 'OpenAI rejected the request (' . $status . '): ' . $message);
    }

    /** Provider messages can echo part of the key; strip anything that looks like one. */
    private function clean(string $message): string
    {
        $message = preg_replace('/\bsk-[A-Za-z0-9_\-*.]{4,}/', 'sk-…', $message);

        return mb_substr(trim($message), 0, 300);
    }
}
