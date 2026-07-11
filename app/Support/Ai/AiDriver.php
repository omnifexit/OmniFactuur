<?php

namespace App\Support\Ai;

/**
 * Abstract base for AI drivers.
 *
 * Concrete drivers adapt a specific AI provider (OpenRouter, Anthropic direct,
 * local Ollama, etc.) into the two operations the host app needs:
 *
 *   - `chatCompletion()` — for the chat assistant. OpenAI chat format, optional
 *     tool-calling support. Drivers MUST translate their provider's tool-call
 *     shape into the OpenAI-style `tool_calls` array on AiChatResponse so the
 *     AiAssistantService orchestration loop stays provider-agnostic.
 *
 *   - `textCompletion()` — for the text generation popup on WYSIWYG editors.
 *     Stateless single-shot prompt → text.
 *
 * Drivers are registered with the host via the module Registry (see
 * App\Providers\DriverRegistryProvider for built-ins and
 * InvoiceShelf\Modules\Registry::registerAiDriver() for module-contributed ones).
 */
abstract class AiDriver
{
    public function __construct(
        protected string $apiKey,
        protected array $config = [],
    ) {}

    /**
     * Perform a chat completion.
     *
     * @param  array<int, array<string, mixed>>  $messages  OpenAI chat format: [['role' => 'user', 'content' => '...'], ...]
     * @param  string  $model  Provider-specific model identifier, e.g. 'openai/gpt-4o'
     * @param  array<int, array<string, mixed>>  $tools  OpenAI tools schema array (empty = no tool calling)
     * @param  array<string, mixed>  $options  Provider-specific options (temperature, max_tokens, etc.)
     *
     * @throws AiException
     */
    abstract public function chatCompletion(
        array $messages,
        string $model,
        array $tools = [],
        array $options = [],
    ): AiChatResponse;

    /**
     * Perform a single-shot text completion.
     *
     * Implementations may route this through chatCompletion() with a single
     * user message — it's a convenience for callers that don't need history.
     *
     * @throws AiException
     */
    abstract public function textCompletion(
        string $prompt,
        string $model,
        array $options = [],
    ): string;

    /**
     * Validate that the configured API key and base URL can reach the provider.
     *
     * Called from admin "Test connection" buttons. Should make a cheap round-trip
     * (list models, short completion, etc.) and throw AiException on failure.
     *
     * @return array<string, mixed> Provider info the UI can display (e.g. echoed model list)
     *
     * @throws AiException
     */
    abstract public function validateConnection(): array;

    /**
     * Optional: return the list of available model identifiers from the provider.
     *
     * Drivers that don't expose a models endpoint can leave this as an empty array.
     * The UI falls back to the `suggested_models` declared in driver metadata.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function listModels(): array
    {
        return [];
    }
}
