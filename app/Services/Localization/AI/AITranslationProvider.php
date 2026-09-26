<?php

namespace App\Services\Localization\AI;

/**
 * An AI backend that can return structured JSON for a chat-style prompt.
 * Prompting, validation and batching live in AITranslationService, so a new provider
 * (Anthropic, Azure OpenAI, ...) only implements the transport.
 */
interface AITranslationProvider
{
    public function name(): string;

    /**
     * Send the prompt and return the decoded JSON object that matches $schema.
     *
     * @return array{data: array, prompt_tokens: int, completion_tokens: int}
     *
     * @throws AITranslationException
     */
    public function complete(string $system, string $user, array $schema, string $model): array;

    /**
     * Check the key and list the models it can use.
     *
     * @return array{ok: bool, models: string[], message: string}
     */
    public function testConnection(): array;
}
